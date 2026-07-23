<?php

namespace App\Services;

use App\Models\DemoFeatureMoment;
use App\Models\DemoFrame;
use App\Models\DemoSession;
use App\Models\DemoTranscriptSegment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DemoMediaIngestionService
{
    public function process(DemoSession $session, int $frameIntervalSeconds = 5): array
    {
        $session->update([
            'processing_status' => 'processing',
            'processing_notes' => 'Processing started. Extracting transcript segments and demo screenshots.',
        ]);

        try {
            $transcriptCount = $this->ingestTranscript($session);
            $whisperSegments = 0;

            if ($transcriptCount === 0) {
                $session->update([
                    'processing_notes' => 'Transcribing audio with Whisper. This can take a while for longer videos.',
                ]);
                $whisperSegments = $this->transcribeVideo($session);
                $transcriptCount = $whisperSegments;
            }

            $session->update([
                'processing_notes' => 'Transcript ready with ' . $transcriptCount . ' segment(s). Extracting screenshots from the video.',
            ]);
            $frameCount = $this->extractFrames($session, $frameIntervalSeconds);

            $session->update([
                'processing_notes' => 'Extracted ' . $frameCount . ' screenshot(s). Running OCR on a temporary enlarged copy of each frame.',
            ]);
            $ocrCount = $this->ocrFrames($session);

            $session->update([
                'processing_notes' => 'OCR completed for ' . $ocrCount . ' screenshot(s). Filtering blank/loading screenshots.',
            ]);
            $blankFrameCount = $this->rejectBlankFrames($session);

            $session->update([
                'processing_notes' => 'Excluded ' . $blankFrameCount . ' mostly blank/loading screenshot(s). Building searchable feature moments.',
            ]);
            $momentCount = $this->createFeatureMoments($session);

            $session->update([
                'processing_status' => 'processed',
                'processing_notes' => 'Processed for chat use. ' . $blankFrameCount . ' mostly blank/loading screenshot(s) were excluded. Review and approve demo moments before using screenshots in chat answers.',
            ]);

            return [
                'transcript_segments' => $transcriptCount,
                'whisper_segments' => $whisperSegments,
                'frames' => $frameCount,
                'ocr_frames' => $ocrCount,
                'blank_frames_rejected' => $blankFrameCount,
                'feature_moments' => $momentCount,
            ];
        } catch (\Throwable $exception) {
            $session->update([
                'processing_status' => 'failed',
                'processing_notes' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function approveMoments(DemoSession $session): int
    {
        return DemoFeatureMoment::query()
            ->where('demo_session_id', $session->id)
            ->update(['approval_status' => 'approved']);
    }

    private function ingestTranscript(DemoSession $session): int
    {
        DemoTranscriptSegment::query()->where('demo_session_id', $session->id)->delete();

        if (! $session->transcript_storage_path) {
            return 0;
        }

        $path = Storage::path($session->transcript_storage_path);

        if (! is_file($path)) {
            return 0;
        }

        $text = file_get_contents($path) ?: '';
        $segments = $this->parseGeneratedTranscriptJson($text);

        if ($segments === []) {
            $segments = $this->parseTimedTranscript($text);
        }

        if ($segments === []) {
            $segments = [[
                'start_ms' => 0,
                'end_ms' => null,
                'speaker_label' => null,
                'transcript_text' => $this->cleanTranscriptText($text),
            ]];
        }

        foreach ($segments as $segment) {
            if (trim($segment['transcript_text']) === '') {
                continue;
            }

            DemoTranscriptSegment::create([
                'demo_session_id' => $session->id,
                'start_ms' => $segment['start_ms'],
                'end_ms' => $segment['end_ms'],
                'speaker_label' => $segment['speaker_label'],
                'transcript_text' => $segment['transcript_text'],
            ]);
        }

        return DemoTranscriptSegment::query()->where('demo_session_id', $session->id)->count();
    }

    private function transcribeVideo(DemoSession $session): int
    {
        $python = $this->executablePath('python', 'Python is required before local Whisper transcription can run.');
        $scriptPath = base_path('tools/whisper/transcribe.py');

        if (! is_file($scriptPath)) {
            throw new RuntimeException('Whisper transcription script is missing from tools/whisper/transcribe.py.');
        }

        if (! $session->video_storage_path) {
            throw new RuntimeException('No video file is attached to this demo session.');
        }

        $videoPath = Storage::path($session->video_storage_path);

        if (! is_file($videoPath)) {
            throw new RuntimeException('Video file could not be found in storage.');
        }

        $outputDirectory = Storage::path('demo-media/transcripts/generated');

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0775, true);
        }

        $outputPath = $outputDirectory . DIRECTORY_SEPARATOR . 'session-' . $session->id . '.json';
        $process = new Process([$python, $scriptPath, $videoPath, $outputPath, env('WHISPER_MODEL', 'tiny')], base_path());
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Whisper transcription failed: ' . Str::limit($process->getErrorOutput() ?: $process->getOutput(), 1200));
        }

        $payload = json_decode(file_get_contents($outputPath) ?: '', true);
        $segments = is_array($payload['segments'] ?? null) ? $payload['segments'] : [];

        foreach ($segments as $segment) {
            $text = $this->cleanTranscriptText((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            DemoTranscriptSegment::create([
                'demo_session_id' => $session->id,
                'start_ms' => (int) ($segment['start_ms'] ?? 0),
                'end_ms' => (int) ($segment['end_ms'] ?? 0),
                'speaker_label' => null,
                'transcript_text' => $text,
            ]);
        }

        $session->update(['transcript_storage_path' => 'demo-media/transcripts/generated/session-' . $session->id . '.json']);

        return DemoTranscriptSegment::query()->where('demo_session_id', $session->id)->count();
    }

    private function extractFrames(DemoSession $session, int $frameIntervalSeconds): int
    {
        $ffmpeg = $this->executablePath('ffmpeg', 'FFmpeg is required before demo videos can be processed. Install FFmpeg and make sure ffmpeg.exe is available on PATH.');

        if (! $session->video_storage_path) {
            throw new RuntimeException('No video file is attached to this demo session.');
        }

        $inputPath = Storage::path($session->video_storage_path);

        if (! is_file($inputPath)) {
            throw new RuntimeException('Video file could not be found in storage.');
        }

        DemoFrame::query()->where('demo_session_id', $session->id)->delete();

        $frameDirectory = 'demo-media/frames/session-' . $session->id;
        $absoluteFrameDirectory = Storage::path($frameDirectory);

        if (! is_dir($absoluteFrameDirectory)) {
            mkdir($absoluteFrameDirectory, 0775, true);
        }

        $pattern = $absoluteFrameDirectory . DIRECTORY_SEPARATOR . 'frame-%05d.jpg';
        $process = new Process([
            $ffmpeg,
            '-y',
            '-i',
            $inputPath,
            '-vf',
            'fps=1/' . max(1, $frameIntervalSeconds),
            '-q:v',
            '3',
            $pattern,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFmpeg frame extraction failed: ' . Str::limit($process->getErrorOutput() ?: $process->getOutput(), 800));
        }

        $files = collect(glob($absoluteFrameDirectory . DIRECTORY_SEPARATOR . 'frame-*.jpg') ?: [])
            ->sort()
            ->values();

        foreach ($files as $index => $file) {
            DemoFrame::create([
                'demo_session_id' => $session->id,
                'timestamp_ms' => $index * max(1, $frameIntervalSeconds) * 1000,
                'image_storage_path' => $frameDirectory . '/' . basename($file),
                'review_status' => 'unreviewed',
            ]);
        }

        return $files->count();
    }

    private function ocrFrames(DemoSession $session): int
    {
        $tesseract = $this->optionalExecutablePath('tesseract');

        if (! $tesseract) {
            return 0;
        }

        $count = 0;

        DemoFrame::query()
            ->where('demo_session_id', $session->id)
            ->orderBy('timestamp_ms')
            ->each(function (DemoFrame $frame) use ($tesseract, &$count) {
                $path = Storage::path($frame->image_storage_path);

                if (! is_file($path)) {
                    return;
                }

                $ocrPath = $this->preprocessFrameForOcr($path) ?: $path;
                $process = new Process([$tesseract, $ocrPath, 'stdout', '-l', 'eng', '--psm', '6']);
                $process->setTimeout(90);
                $process->run();

                if ($ocrPath !== $path && is_file($ocrPath)) {
                    @unlink($ocrPath);
                }

                if (! $process->isSuccessful()) {
                    return;
                }

                $ocrText = $this->cleanTranscriptText($process->getOutput());
                $uiStructure = $this->uiStructure($ocrText);
                $frame->update([
                    'ocr_text' => $ocrText,
                    'cleaned_ui_text' => $uiStructure['cleaned_text'],
                    'detected_ui_terms' => $this->detectedUiTerms($uiStructure['cleaned_text']),
                    'ui_structure' => $uiStructure,
                    'screen_summary' => $this->screenSummary($uiStructure),
                ]);
                $count++;
            });

        return $count;
    }

    private function preprocessFrameForOcr(string $path): ?string
    {
        if (! function_exists('imagecreatefromjpeg') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $source = @imagecreatefromjpeg($path);

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);

            return null;
        }

        $scale = 3;
        $targetWidth = $width * $scale;
        $targetHeight = $height * $scale;
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagefilter($target, IMG_FILTER_GRAYSCALE);
        imagefilter($target, IMG_FILTER_CONTRAST, -35);
        imagefilter($target, IMG_FILTER_BRIGHTNESS, 8);
        imagefilter($target, IMG_FILTER_SMOOTH, -4);

        $tmpPath = storage_path('app/demo-media/ocr-temp/frame-' . md5($path . microtime(true)) . '.jpg');
        $tmpDir = dirname($tmpPath);

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        imagejpeg($target, $tmpPath, 92);
        imagedestroy($source);
        imagedestroy($target);

        return is_file($tmpPath) ? $tmpPath : null;
    }

    public function rejectBlankFrames(?DemoSession $session = null): int
    {
        $query = DemoFrame::query()
            ->where('review_status', '!=', 'rejected')
            ->orderBy('demo_session_id')
            ->orderBy('timestamp_ms');

        if ($session) {
            $query->where('demo_session_id', $session->id);
        }

        $count = 0;

        $query->each(function (DemoFrame $frame) use (&$count) {
            $path = Storage::path($frame->image_storage_path);

            if (! is_file($path)) {
                return;
            }

            $uiStructure = is_array($frame->ui_structure) ? $frame->ui_structure : [];

            if (! $this->isMostlyBlankLoadingFrame($path, $uiStructure, (string) $frame->cleaned_ui_text)) {
                return;
            }

            $frame->update([
                'review_status' => 'rejected',
                'screen_summary' => null,
                'detected_ui_terms' => [],
            ]);

            $count++;
        });

        return $count;
    }

    public function rebuildFrameOcrSummaries(?DemoSession $session = null): int
    {
        $query = DemoFrame::query()
            ->whereNotNull('ocr_text')
            ->orderBy('demo_session_id')
            ->orderBy('timestamp_ms');

        if ($session) {
            $query->where('demo_session_id', $session->id);
        }

        $count = 0;

        $query->each(function (DemoFrame $frame) use (&$count) {
            $uiStructure = $this->uiStructure((string) $frame->ocr_text);

            $frame->update([
                'cleaned_ui_text' => $uiStructure['cleaned_text'],
                'detected_ui_terms' => $this->detectedUiTerms($uiStructure['cleaned_text']),
                'ui_structure' => $uiStructure,
                'screen_summary' => $this->screenSummary($uiStructure),
            ]);

            $count++;
        });

        return $count;
    }

    /**
     * @param array<string, mixed> $uiStructure
     */
    private function isMostlyBlankLoadingFrame(string $path, array $uiStructure, string $cleanedText): bool
    {
        $stats = $this->frameVisualStats($path);

        if ($stats === null) {
            return false;
        }

        $usefulUiCount = collect(['menus', 'labels', 'actions', 'columns'])
            ->sum(fn (string $bucket) => count((array) ($uiStructure[$bucket] ?? [])));
        $cleanedTextLength = strlen(trim($cleanedText));

        if (Str::contains(Str::lower($cleanedText), ['loading', 'please wait'])) {
            return $stats['center_white_ratio'] >= 0.72 && $usefulUiCount <= 4;
        }

        return $stats['center_white_ratio'] >= 0.88
            && $stats['center_edge_ratio'] <= 0.018
            && $stats['center_color_ratio'] <= 0.08
            && $usefulUiCount <= 3
            && $cleanedTextLength <= 180;
    }

    /**
     * @return null|array{center_white_ratio: float, center_edge_ratio: float, center_color_ratio: float}
     */
    private function frameVisualStats(string $path): ?array
    {
        if (! function_exists('imagecreatefromjpeg') || ! function_exists('getimagesize')) {
            return null;
        }

        $size = @getimagesize($path);

        if (! $size) {
            return null;
        }

        [$width, $height] = $size;
        $image = @imagecreatefromjpeg($path);

        if (! $image) {
            return null;
        }

        $step = max(4, (int) floor(min($width, $height) / 90));
        $startX = (int) floor($width * 0.14);
        $endX = (int) floor($width * 0.96);
        $startY = (int) floor($height * 0.17);
        $endY = (int) floor($height * 0.92);
        $total = 0;
        $white = 0;
        $colorful = 0;
        $edges = 0;
        $edgeSamples = 0;

        for ($y = $startY; $y < $endY; $y += $step) {
            for ($x = $startX; $x < $endX; $x += $step) {
                [$r, $g, $b] = $this->rgbAt($image, $x, $y);
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $luma = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

                $total++;

                if ($r >= 238 && $g >= 238 && $b >= 238 && ($max - $min) <= 18) {
                    $white++;
                }

                if (($max - $min) >= 45 && $luma < 235) {
                    $colorful++;
                }

                if ($x + $step < $endX) {
                    [$r2, $g2, $b2] = $this->rgbAt($image, $x + $step, $y);
                    $luma2 = ($r2 * 0.299) + ($g2 * 0.587) + ($b2 * 0.114);
                    $edgeSamples++;

                    if (abs($luma - $luma2) >= 34) {
                        $edges++;
                    }
                }

                if ($y + $step < $endY) {
                    [$r2, $g2, $b2] = $this->rgbAt($image, $x, $y + $step);
                    $luma2 = ($r2 * 0.299) + ($g2 * 0.587) + ($b2 * 0.114);
                    $edgeSamples++;

                    if (abs($luma - $luma2) >= 34) {
                        $edges++;
                    }
                }
            }
        }

        imagedestroy($image);

        if ($total === 0 || $edgeSamples === 0) {
            return null;
        }

        return [
            'center_white_ratio' => $white / $total,
            'center_edge_ratio' => $edges / $edgeSamples,
            'center_color_ratio' => $colorful / $total,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgbAt(\GdImage $image, int $x, int $y): array
    {
        $rgb = imagecolorat($image, $x, $y);

        return [
            ($rgb >> 16) & 0xFF,
            ($rgb >> 8) & 0xFF,
            $rgb & 0xFF,
        ];
    }

    private function createFeatureMoments(DemoSession $session): int
    {
        DemoFeatureMoment::query()->where('demo_session_id', $session->id)->delete();

        $frames = DemoFrame::query()
            ->where('demo_session_id', $session->id)
            ->where('review_status', '!=', 'rejected')
            ->orderBy('timestamp_ms')
            ->get();

        $segments = DemoTranscriptSegment::query()
            ->where('demo_session_id', $session->id)
            ->orderBy('start_ms')
            ->get();

        if ($segments->isEmpty()) {
            $segments = $frames->map(function (DemoFrame $frame) {
                return (object) [
                    'start_ms' => $frame->timestamp_ms,
                    'end_ms' => $frame->timestamp_ms + 5000,
                    'transcript_text' => $frame->screen_summary ?: $frame->cleaned_ui_text ?: 'Demo screenshot captured at ' . round($frame->timestamp_ms / 1000, 1) . ' seconds.',
                ];
            });
        }

        foreach ($segments as $segment) {
            $nearestFrames = $frames
                ->filter(fn (DemoFrame $frame) => abs($frame->timestamp_ms - (int) $segment->start_ms) <= 8000)
                ->take(3)
                ->values();

            $screenEvidence = $nearestFrames
                ->map(fn (DemoFrame $frame) => $frame->screen_summary ?: $frame->cleaned_ui_text)
                ->filter()
                ->implode(' ');
            $combinedText = trim($segment->transcript_text . ' ' . $screenEvidence);

            if ($combinedText === '') {
                continue;
            }

            DemoFeatureMoment::create([
                'demo_session_id' => $session->id,
                'product_id' => $session->product_id,
                'feature_name' => $this->featureName($session->title, $combinedText),
                'business_problem' => $this->businessProblem($combinedText),
                'module_name' => $this->moduleName($combinedText),
                'start_ms' => $segment->start_ms,
                'end_ms' => $segment->end_ms,
                'summary' => Str::limit($combinedText, 1200, ''),
                'benefits' => $this->benefits($combinedText),
                'frame_ids' => $nearestFrames->pluck('id')->values()->all(),
                'approval_status' => 'unreviewed',
            ]);
        }

        return DemoFeatureMoment::query()->where('demo_session_id', $session->id)->count();
    }

    private function parseGeneratedTranscriptJson(string $text): array
    {
        $payload = json_decode($text, true);

        if (! is_array($payload) || ! is_array($payload['segments'] ?? null)) {
            return [];
        }

        return collect($payload['segments'])
            ->map(function (array $segment) {
                $body = $this->cleanTranscriptText((string) ($segment['text'] ?? ''));

                if ($body === '') {
                    return null;
                }

                return [
                    'start_ms' => (int) ($segment['start_ms'] ?? 0),
                    'end_ms' => isset($segment['end_ms']) ? (int) $segment['end_ms'] : null,
                    'speaker_label' => null,
                    'transcript_text' => $body,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function parseTimedTranscript(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $blocks = preg_split("/\n{2,}/", trim($text)) ?: [];
        $segments = [];

        foreach ($blocks as $block) {
            if (! preg_match('/(?<start>\d{1,2}:\d{2}:\d{2}[,.]\d{1,3})\s*-->\s*(?<end>\d{1,2}:\d{2}:\d{2}[,.]\d{1,3})/u', $block, $match)) {
                continue;
            }

            $lines = collect(explode("\n", $block))
                ->reject(fn (string $line) => preg_match('/^\s*\d+\s*$/', $line))
                ->reject(fn (string $line) => str_contains($line, '-->'))
                ->values();

            $body = $this->cleanTranscriptText($lines->implode(' '));

            $segments[] = [
                'start_ms' => $this->timestampToMs($match['start']),
                'end_ms' => $this->timestampToMs($match['end']),
                'speaker_label' => null,
                'transcript_text' => $body,
            ];
        }

        return $segments;
    }

    private function timestampToMs(string $timestamp): int
    {
        [$hours, $minutes, $seconds] = explode(':', str_replace(',', '.', $timestamp));

        return ((int) $hours * 3600 + (int) $minutes * 60 + (float) $seconds) * 1000;
    }

    private function cleanTranscriptText(string $text): string
    {
        $text = preg_replace('/\d{1,2}:\d{2}:\d{2}[,.]\d{1,3}\s*-->\s*\d{1,2}:\d{2}:\d{2}[,.]\d{1,3}/u', ' ', $text);
        $text = preg_replace('/\bSpeaker\s+\d+\b/i', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    private function featureName(string $sessionTitle, string $text): string
    {
        $module = $this->moduleName($text);

        return $module ?: Str::limit($sessionTitle, 120, '');
    }

    private function moduleName(string $text): ?string
    {
        $normalized = Str::lower(Str::ascii($text));
        $modules = [
            'Registration Management' => ['registration', 'register', 'social security number'],
            'Contributions Management' => ['contribution', 'filing', 'insurable earnings'],
            'Benefits Management' => ['benefit', 'claim', 'eligibility', 'entitlement'],
            'Compliance Management' => ['compliance', 'delinquency', 'arrears', 'lawsuit'],
            'Payments Management' => ['payment', 'cheque', 'bank file', 'disbursement'],
            'Self-Service' => ['portal', 'self-service', 'online application'],
            'Accounting and Reconciliation' => ['general ledger', 'gl ', 'reconciliation', 'accounting'],
        ];

        foreach ($modules as $module => $signals) {
            if (collect($signals)->contains(fn (string $signal) => str_contains($normalized, $signal))) {
                return $module;
            }
        }

        return null;
    }

    private function businessProblem(string $text): ?string
    {
        $normalized = Str::lower(Str::ascii($text));

        return match (true) {
            str_contains($normalized, 'delinquen') || str_contains($normalized, 'arrear') => 'Tracking and resolving employer delinquency or arrears.',
            str_contains($normalized, 'eligib') || str_contains($normalized, 'entitlement') => 'Determining eligibility, entitlement, and benefit amounts.',
            str_contains($normalized, 'contribution') => 'Managing contribution filing, validation, payment, and corrections.',
            str_contains($normalized, 'registration') => 'Registering and maintaining accurate social security records.',
            str_contains($normalized, 'payment') => 'Controlling benefit or contribution payment processing.',
            default => null,
        };
    }

    private function benefits(string $text): array
    {
        $benefits = [];
        $normalized = Str::lower(Str::ascii($text));

        if (str_contains($normalized, 'configur')) {
            $benefits[] = 'Supports configurable rules and parameters.';
        }

        if (str_contains($normalized, 'workflow') || str_contains($normalized, 'approval')) {
            $benefits[] = 'Supports controlled workflow and approval steps.';
        }

        if (str_contains($normalized, 'report')) {
            $benefits[] = 'Provides reporting and traceability.';
        }

        if (str_contains($normalized, 'self-service') || str_contains($normalized, 'portal')) {
            $benefits[] = 'Extends service delivery through self-service channels.';
        }

        return $benefits;
    }

    private function detectedUiTerms(string $ocrText): array
    {
        $terms = ['save', 'approve', 'submit', 'search', 'status', 'workflow', 'report', 'payment', 'registration', 'contribution', 'benefit'];
        $normalized = Str::lower(Str::ascii($ocrText));

        return collect($terms)
            ->filter(fn (string $term) => str_contains($normalized, $term))
            ->values()
            ->all();
    }

    /**
     * @return array{cleaned_text: string, menus: array<int, string>, labels: array<int, string>, actions: array<int, string>, columns: array<int, string>, ignored_examples: array<int, string>}
     */
    private function uiStructure(string $ocrText): array
    {
        $lines = collect(preg_split('/[\r\n|]+/', $ocrText) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s+/', ' ', $line) ?? ''))
            ->filter(fn (string $line) => $line !== '' && strlen($line) >= 2)
            ->map(fn (string $line) => trim($line, " \t\n\r\0\x0B:;,."))
            ->filter()
            ->unique()
            ->values();

        $ignored = [];

        $structural = $lines
            ->reject(function (string $line) use (&$ignored) {
                if ($this->looksLikeOcrNoise($line) || $this->looksLikeTransactionalValue($line)) {
                    $ignored[] = $line;

                    return true;
                }

                return false;
            })
            ->map(fn (string $line) => $this->cleanOcrUiLine($line))
            ->filter()
            ->filter(fn (string $line) => $this->looksLikeUiLabel($line))
            ->reject(function (string $line) use (&$ignored) {
                if ($this->looksLikeGarbledOcr($line)) {
                    $ignored[] = $line;

                    return true;
                }

                return false;
            })
            ->values();

        $actions = $structural
            ->filter(fn (string $line) => preg_match('/\b(add|approve|back|cancel|clear|close|delete|edit|export|finish|new|next|post|preview|print|process|reject|review|save|search|select|submit|update|upload|view)\b/i', $line))
            ->take(25)
            ->values()
            ->all();

        $menus = $structural
            ->filter(fn (string $line) => preg_match('/\b(accounting|administration|benefits|claims|compliance|configuration|contributions|dashboard|employer|finance|home|inquiry|management|master data|payments|policy|registration|reports|security|self-service|setup|transactions|workflow)\b/i', $line))
            ->take(40)
            ->values()
            ->all();

        $columns = $structural
            ->filter(fn (string $line) => preg_match('/\b(amount|balance|code|date|description|earnings|employee|employer|from|id|name|number|period|rate|reference|status|to|total|type)\b/i', $line))
            ->take(40)
            ->values()
            ->all();

        $labels = $structural
            ->reject(fn (string $line) => in_array($line, $actions, true) || in_array($line, $menus, true) || in_array($line, $columns, true))
            ->take(80)
            ->values()
            ->all();

        return [
            'cleaned_text' => $structural->take(160)->implode(' | '),
            'menus' => $menus,
            'labels' => $labels,
            'actions' => $actions,
            'columns' => $columns,
            'ignored_examples' => collect($ignored)->take(30)->values()->all(),
        ];
    }

    private function screenSummary(array $structure): ?string
    {
        $parts = [];

        if (! empty($structure['menus'])) {
            $parts[] = 'Menu labels and navigation: ' . implode(', ', array_slice($structure['menus'], 0, 10));
        }

        if (! empty($structure['labels'])) {
            $parts[] = 'Screen labels: ' . implode(', ', array_slice($structure['labels'], 0, 10));
        }

        if (! empty($structure['columns'])) {
            $parts[] = 'Columns and fields: ' . implode(', ', array_slice($structure['columns'], 0, 10));
        }

        if (! empty($structure['actions'])) {
            $parts[] = 'Available actions: ' . implode(', ', array_slice($structure['actions'], 0, 8));
        }

        if ($parts === []) {
            return null;
        }

        $evidenceCount = collect(['menus', 'labels', 'columns', 'actions'])
            ->sum(fn (string $key) => count($structure[$key] ?? []));

        return $evidenceCount < 2 ? null : Str::limit(implode('. ', $parts) . '.', 900, '');
    }

    private function looksLikeUiLabel(string $line): bool
    {
        $ascii = Str::ascii($line);

        if (strlen($ascii) > 90 || strlen($ascii) < 3) {
            return false;
        }

        if (preg_match('/[{}<>~_=]{1,}|https?:\/\/|www\.|@|[^\w\s.,:\/()&-]{1,}/', $ascii)) {
            return false;
        }

        $tokens = collect(preg_split('/[^a-z0-9]+/i', Str::lower($ascii)) ?: [])
            ->filter(fn (string $token) => $token !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return false;
        }

        $lettersOnly = preg_replace('/[^a-z]/i', '', $ascii) ?? '';
        $letterRatio = strlen($lettersOnly) / max(1, strlen(preg_replace('/\s+/', '', $ascii) ?? ''));

        if ($letterRatio < 0.68) {
            return false;
        }

        $shortTokens = $tokens->filter(fn (string $token) => strlen($token) <= 2)->count();

        if ($shortTokens > 0 && $shortTokens >= max(1, (int) floor($tokens->count() * 0.45))) {
            return false;
        }

        $knownTokens = $this->knownUiTokens();

        if ($tokens->contains(fn (string $token) => $knownTokens->contains($token))) {
            return true;
        }

        return $tokens->filter(fn (string $token) => strlen($token) >= 4)->count() >= 2
            && preg_match('/^[A-Z][A-Za-z0-9&\/() -]+$/', trim($ascii)) === 1;
    }

    private function looksLikeOcrNoise(string $line): bool
    {
        $normalized = Str::lower(Str::ascii($line));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return true;
        }

        foreach ([
            'copyright',
            'all rights reserved',
            'all right reserved',
            'interact inc',
            '2interact inc',
            '2026 interact',
            'social security administration system ssas user manual',
            'social security administration system ssas',
        ] as $noise) {
            if (str_contains($normalized, $noise)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeGarbledOcr(string $line): bool
    {
        $ascii = trim(Str::ascii($line));
        $lettersOnly = preg_replace('/[^a-z]/i', '', $ascii) ?? '';

        if (strlen($lettersOnly) < 4) {
            return true;
        }

        $tokens = collect(preg_split('/[^a-z0-9]+/i', Str::lower($ascii)) ?: [])
            ->filter(fn (string $token) => $token !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return true;
        }

        $knownTokens = collect([
            ...$this->knownUiTokens()->all(),
        ]);

        $knownCount = $tokens->filter(fn (string $token) => $knownTokens->contains($token))->count();
        $unknownLongCount = $tokens->filter(fn (string $token) => strlen($token) >= 4 && ! $knownTokens->contains($token))->count();
        $veryShortCount = $tokens->filter(fn (string $token) => strlen($token) <= 2)->count();

        if ($knownCount === 0 && $unknownLongCount > 0) {
            return true;
        }

        if ($knownCount > 0 && $unknownLongCount >= $knownCount && $veryShortCount > 0) {
            return true;
        }

        if (preg_match('/[^\w\s.,:\/()&-]{2,}/', $ascii)) {
            return true;
        }

        return false;
    }

    private function cleanOcrUiLine(string $line): string
    {
        $line = trim(Str::ascii($line));
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        $line = preg_replace('/\b(Copyright|All rights? reserved|Interact Inc)\b.*$/i', '', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B:;,.-");

        return trim($line);
    }

    private function knownUiTokens(): Collection
    {
        return collect([
            'account', 'accounting', 'action', 'add', 'administration', 'adjustment', 'amount', 'application',
            'approve', 'award', 'balance', 'benefit', 'benefits', 'button', 'cancel', 'cash', 'claim', 'claims',
            'code', 'column', 'compliance', 'configuration', 'contribution', 'contributions', 'credit',
            'dashboard', 'date', 'delete', 'delinquency', 'description', 'details', 'earnings', 'edit',
            'employee', 'employer', 'export', 'field', 'filing', 'final', 'finance', 'form', 'forms', 'from',
            'home', 'id', 'inquiry', 'intercountry', 'job', 'label', 'load', 'management', 'menu', 'name',
            'new', 'number', 'page', 'payment', 'payments', 'penalty', 'period', 'policy', 'post', 'posting',
            'preview', 'print', 'process', 'rate', 'receivable', 'receivables', 'receipt', 'reference',
            'registration', 'report', 'reports', 'request', 'review', 'save', 'search', 'security', 'select',
            'self', 'service', 'setup', 'status', 'submit', 'tab', 'to', 'total', 'transaction', 'transactions',
            'type', 'unit', 'units', 'union', 'update', 'upload', 'view', 'workflow',
        ]);
    }

    private function looksLikeTransactionalValue(string $line): bool
    {
        $ascii = trim(Str::ascii($line));

        if ($ascii === '') {
            return true;
        }

        if (preg_match('/^\d+([.,]\d+)?$/', $ascii)) {
            return true;
        }

        if (preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4}$/', $ascii)) {
            return true;
        }

        if (preg_match('/^[A-Z]{1,4}[-\s]?\d{3,}$/i', $ascii)) {
            return true;
        }

        if (preg_match('/^\$?\d{1,3}(,\d{3})*(\.\d{2})?$/', $ascii)) {
            return true;
        }

        if (preg_match('/^\+?\d[\d\s().-]{6,}$/', $ascii)) {
            return true;
        }

        if (preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $ascii) && ! preg_match('/\b(Self Service|Contribution|Benefit|Employer|Employee|Payment|Registration|Management|Filing|Period|Status|Search|Total|Amount|Date|Type|Code)\b/i', $ascii)) {
            return true;
        }

        return false;
    }

    private function executablePath(string $name, string $message): string
    {
        $path = $this->optionalExecutablePath($name);

        if (! $path) {
            throw new RuntimeException($message);
        }

        return $path;
    }

    private function optionalExecutablePath(string $name): ?string
    {
        $configured = match ($name) {
            'ffmpeg' => env('FFMPEG_PATH'),
            'tesseract' => env('TESSERACT_PATH'),
            'python' => env('PYTHON_PATH'),
            default => null,
        };

        if ($configured && is_file($configured)) {
            return $configured;
        }

        $bundled = match ($name) {
            'ffmpeg' => base_path('tools/ffmpeg/bin/ffmpeg.exe'),
            'tesseract' => base_path('tools/tesseract/tesseract.exe'),
            default => null,
        };

        if ($bundled && is_file($bundled)) {
            return $bundled;
        }

        $finder = new ExecutableFinder();

        return $finder->find($name);
    }
}
