@extends('sls.layouts.app')

@section('title', 'Demo Media Intelligence - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Demo Media Intelligence')

@push('head')
    <style>
        .legacy-page { display: grid; gap: 14px; }
        .legacy-page > header,
        .legacy-page > main { padding: 0; }
        .legacy-page > header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--card-shadow);
            padding: 14px;
        }
        .legacy-page > main { display: grid; gap: 14px; }
        .legacy-page .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .legacy-page .filters,
        .legacy-page .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .legacy-page label { color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Sales Enablement Assets</p>
                <h1>Demo Media Intelligence</h1>
            </div>
            <a class="button" href="{{ url('/sls') }}">Dashboard</a>
        </header>

        <main>
            @if (session('status'))
                <section class="panel" style="margin-bottom: 1rem;">
                    <p class="eyebrow">Uploaded</p>
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            <section class="grid">
                <article class="panel">
                    <p class="eyebrow">Demo Sessions</p>
                    <h2>{{ $sessionCount }}</h2>
                    <p class="muted">Videos and transcript packages registered for processing.</p>
                </article>
                <article class="panel">
                    <p class="eyebrow">Indexed Frames</p>
                    <h2>{{ $frameCount }}</h2>
                    <p class="muted">Timestamped screenshots with OCR and detected UI terms.</p>
                </article>
                <article class="panel">
                    <p class="eyebrow">Feature Moments</p>
                    <h2>{{ $featureMomentCount }}</h2>
                    <p class="muted">Reusable product moments for proposals, RFP answers, emails, and short videos.</p>
                </article>
            </section>

            <section class="panel" style="margin-bottom: 1rem;">
                <p class="eyebrow">Processing Tools</p>
                <h2>Video ingestion readiness</h2>
                <div class="badge-row">
                    <span class="badge {{ $ffmpegReady ? 'ready' : 'pending' }}">FFmpeg frame extraction: {{ $ffmpegReady ? 'ready' : 'not installed' }}</span>
                    <span class="badge {{ $whisperReady ? 'ready' : 'pending' }}">Whisper transcription: {{ $whisperReady ? 'ready' : 'pending' }}</span>
                    <span class="badge {{ $tesseractReady ? 'ready' : 'pending' }}">Tesseract OCR: {{ $tesseractReady ? 'ready' : 'pending' }}</span>
                </div>
                <p class="muted" style="margin-top: .65rem;">Whisper generates timestamped transcript segments from the MP4 when no transcript is uploaded. OCR improves screen-text search once Tesseract is installed.</p>
            </section>

            <section class="panel" style="margin-bottom: 1rem;">
                <p class="eyebrow">Demo Video Intake</p>
                <h2>Register Demo Videos</h2>
                <form method="post" action="{{ route('sls.demo-media.store') }}" enctype="multipart/form-data" class="stack">
                    @csrf
                    <div class="form-grid">
                        <label>
                            Optional title override
                            <input id="demo-title" name="title" value="{{ old('title') }}" placeholder="Leave blank to use each video filename">
                        </label>
                        <label>
                            Product
                            <select name="product_id">
                                <option value="">Not assigned</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Language
                            <select name="language_code" required>
                                <option value="en" @selected(old('language_code', 'en') === 'en')>English</option>
                                <option value="fr" @selected(old('language_code') === 'fr')>French</option>
                                <option value="es" @selected(old('language_code') === 'es')>Spanish</option>
                                <option value="pt" @selected(old('language_code') === 'pt')>Portuguese</option>
                                <option value="nl" @selected(old('language_code') === 'nl')>Dutch</option>
                                <option value="ar" @selected(old('language_code') === 'ar')>Arabic</option>
                            </select>
                        </label>
                    </div>
                    <div class="form-grid">
                        <label>
                            Video files
                            <input id="demo-video-files" type="file" name="video_files[]" accept=".mp4,.mov,.avi,.mkv,.webm,video/*" multiple required>
                        </label>
                        <label>
                            Transcript file
                            <input type="file" name="transcript_file" accept=".txt,.vtt,.srt,.csv,.md,text/*">
                        </label>
                        <button class="button" type="submit">Register videos</button>
                    </div>
                    <label style="display: flex; align-items: center; gap: .5rem;">
                        <input type="checkbox" name="process_after_upload" value="1" checked style="width: auto; min-height: auto;">
                        Start sequential processing after upload
                    </label>
                    <p id="selected-video-list" class="muted">Select up to 5 MP4/MOV/WebM files. Titles will default to the attached filenames.</p>
                    @if ($errors->any())
                        <p class="muted">{{ $errors->first() }}</p>
                    @else
                        <p class="muted">For now, 3 to 15 minute clips are fine. OCR is the slowest step, so a five-video batch may take a while; the system processes the registered sessions one by one. If you attach a transcript here, it is used for the first selected video only.</p>
                    @endif
                </form>
            </section>

            <section class="panel">
                <p class="eyebrow">Processing Pipeline</p>
                <h2>How Demo Videos Become Reusable Sales Assets</h2>
                <div class="workflow">
                    <article class="card">
                        <h3>1. Register media</h3>
                        <p class="muted">Store video, transcript, product, language, and session metadata.</p>
                    </article>
                    <article class="card">
                        <h3>2. Extract frames</h3>
                        <p class="muted">Capture screenshots every few seconds with exact timestamps.</p>
                    </article>
                    <article class="card">
                        <h3>3. Align transcript</h3>
                        <p class="muted">Match spoken explanation to nearby frames using transcript timestamps.</p>
                    </article>
                    <article class="card">
                        <h3>4. OCR and classify</h3>
                        <p class="muted">Read screen text, identify modules, workflows, business problems, and benefits.</p>
                    </article>
                    <article class="card">
                        <h3>5. Reuse assets</h3>
                        <p class="muted">Pull screenshots and summaries into RFP responses, sales emails, and 3-minute videos.</p>
                    </article>
                </div>
                <p class="muted">Processing uses FFmpeg for frame extraction and Tesseract OCR when available. Approved feature moments become searchable by the chatbox so answers can include relevant screenshots.</p>
            </section>

            <section class="panel" style="margin-top: 1rem;">
                <p class="eyebrow">Registered Demo Sessions</p>
                <h2>Current Library</h2>
                @if ($demoSessions->isEmpty())
                    <p class="muted">No demo sessions have been registered yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Frames</th>
                                <th>Transcript segments</th>
                                <th>Feature moments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($demoSessions as $session)
                                @php
                                    $status = $session->processing_status ?? 'draft';
                                    $hasTranscript = $session->transcript_segments_count > 0;
                                    $hasFrames = $session->frames_count > 0;
                                    $hasMoments = $session->feature_moments_count > 0;
                                    $progress = match ($status) {
                                        'failed' => 100,
                                        'processed' => 100,
                                        'queued' => 12,
                                        'uploaded' => 15,
                                        'processing' => $hasMoments ? 88 : ($hasFrames ? 68 : ($hasTranscript ? 42 : 25)),
                                        default => $hasMoments ? 90 : ($hasFrames ? 65 : ($hasTranscript ? 40 : 10)),
                                    };
                                    $stage = match ($status) {
                                        'failed' => 'Failed',
                                        'processed' => 'Ready for review',
                                        'queued' => 'Queued for sequential processing',
                                        'uploaded' => 'Uploaded, waiting to process',
                                        'processing' => $hasMoments ? 'Building searchable moments' : ($hasFrames ? 'Running OCR and filtering screenshots' : ($hasTranscript ? 'Extracting screenshots' : 'Transcribing audio')),
                                        default => 'Registered',
                                    };
                                @endphp
                                <tr>
                                    <td><a href="{{ route('sls.demo-media.show', $session) }}">{{ $session->title }}</a></td>
                                    <td>{{ $session->product?->name ?? 'Not assigned' }}</td>
                                    <td class="status-cell">
                                        <div class="progress-wrap">
                                            <div class="progress-meta">
                                                <span>{{ $stage }}</span>
                                                <span>{{ $progress }}%</span>
                                            </div>
                                            <div class="progress-bar" aria-label="Processing progress">
                                                <div class="progress-fill {{ $status }}" style="--progress: {{ $progress }}%;"></div>
                                            </div>
                                            @if ($session->processing_notes)
                                                <p class="status-note">{{ $session->processing_notes }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $session->frames_count }}</td>
                                    <td>{{ $session->transcript_segments_count }}</td>
                                    <td>{{ $session->feature_moments_count }}</td>
                                    <td>
                                        <div class="inline-actions">
                                            <a class="button" href="{{ route('sls.demo-media.show', $session) }}">Review</a>
                                            <form method="post" action="{{ route('sls.demo-media.process', $session) }}">
                                                @csrf
                                                <button class="button" type="submit">Process media</button>
                                            </form>
                                            @if ($session->feature_moments_count > 0)
                                                <form method="post" action="{{ route('sls.demo-media.approve-moments', $session) }}">
                                                    @csrf
                                                    <button class="button" type="submit">Approve for chat</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </main>
        <script>
            @if ($demoSessions->contains(fn ($session) => in_array($session->processing_status, ['queued', 'uploaded', 'processing'], true)))
                setTimeout(() => window.location.reload(), 10000);
            @endif

            const videoInput = document.getElementById('demo-video-files');
            const titleInput = document.getElementById('demo-title');
            const selectedList = document.getElementById('selected-video-list');

            videoInput?.addEventListener('change', () => {
                const files = Array.from(videoInput.files || []);

                if (files.length > 5) {
                    selectedList.textContent = 'Please select no more than 5 videos at a time.';
                    videoInput.value = '';
                    return;
                }

                if (files.length === 1 && titleInput && titleInput.value.trim() === '') {
                    titleInput.value = files[0].name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
                }

                selectedList.textContent = files.length
                    ? files.map((file, index) => `${index + 1}. ${file.name}`).join(' | ')
                    : 'Select up to 5 MP4/MOV/WebM files. Titles will default to the attached filenames.';
            });
        </script>
    </div>
@endsection