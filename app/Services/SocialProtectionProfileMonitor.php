<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryUpdate;
use App\Models\CountryUpdateOpportunity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialProtectionProfileMonitor
{
    private const BASE_URL = 'https://www.social-protection.org/gimi/gess/ShowCountryProfile.action?iso=';

    private const DIGITIZATION_TERMS = [
        'digital',
        'digitization',
        'digitalization',
        'digitisation',
        'digitalisation',
        'e-government',
        'ict',
        'information system',
        'management information system',
        'mis',
        'registry',
        'single registry',
        'social registry',
        'beneficiary registry',
        'database',
        'platform',
        'interoperability',
        'integrated system',
        'payment system',
        'electronic payment',
        'mobile money',
        'biometric',
        'national id',
        'automation',
        'modernization',
        'modernisation',
        'online',
        'portal',
        'case management',
    ];

    public function run(array $countryKeys = [], int $limit = 0, bool $dryRun = false): array
    {
        $countries = $this->countries($countryKeys, $limit);
        $checked = 0;
        $signals = 0;
        $errors = [];

        foreach ($countries as $country) {
            $checked++;
            $run = $this->startRun($country);
            $url = $this->profileUrl($country);

            if ($dryRun) {
                $this->finishRun($run, 'dry_run', $url, 0);
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 1G-SLS social protection profile monitor',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://www.social-protection.org/',
                ])
                    ->timeout((int) config('country_intelligence.http_timeout', 25))
                    ->connectTimeout(10)
                    ->withOptions([
                        'verify' => (bool) config('country_intelligence.verify_ssl', true),
                    ])
                    ->get($url);

                if (! $response->successful()) {
                    throw new \RuntimeException('HTTP ' . $response->status());
                }

                $plainText = $this->plainText($response->body());
                $snippets = $this->digitizationSnippets($plainText);

                $country->forceFill([
                    'social_protection_profile_url' => $url,
                    'social_protection_profile_checked_at' => now(),
                    'social_protection_profile_last_success_at' => now(),
                    'social_protection_profile_last_error' => null,
                ])->save();

                if ($snippets !== []) {
                    $signals++;
                    $this->storeDigitizationOpportunity($country, $url, $snippets);
                }

                $this->finishRun($run, 'completed', $url, count($snippets) > 0 ? 1 : 0);
            } catch (\Throwable $exception) {
                $message = Str::limit($exception->getMessage(), 500, '');
                $errors[] = $country->iso_code . ': ' . $message;

                $country->forceFill([
                    'social_protection_profile_url' => $url,
                    'social_protection_profile_checked_at' => now(),
                    'social_protection_profile_last_error' => $message,
                ])->save();

                $this->finishRun($run, 'failed', $url, 0, $message);
            }
        }

        return [
            'checked' => $checked,
            'digitization_signals' => $signals,
            'errors' => $errors,
        ];
    }

    public function ensureProfileUrls(): int
    {
        $updated = 0;

        Country::query()
            ->whereNotNull('iso_code')
            ->where('iso_code', '<>', '')
            ->get()
            ->each(function (Country $country) use (&$updated) {
                $url = $this->profileUrl($country);

                if ($country->social_protection_profile_url !== $url) {
                    $country->forceFill(['social_protection_profile_url' => $url])->save();
                    $updated++;
                }
            });

        return $updated;
    }

    private function countries(array $countryKeys, int $limit)
    {
        $keys = collect($countryKeys)
            ->map(fn ($key) => Str::upper(trim((string) $key)))
            ->filter()
            ->values();

        return Country::query()
            ->whereNotNull('iso_code')
            ->where('iso_code', '<>', '')
            ->when($keys->isNotEmpty(), function ($query) use ($keys) {
                $query->where(function ($inner) use ($keys) {
                    $inner->whereIn('iso_code', $keys)
                        ->orWhereIn('name', $keys)
                        ->orWhereIn(\Illuminate\Support\Facades\DB::raw('UPPER(name)'), $keys);
                });
            })
            ->orderBy('name')
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->get();
    }

    private function profileUrl(Country $country): string
    {
        return self::BASE_URL . Str::upper((string) $country->iso_code);
    }

    private function startRun(Country $country): CountryMonitorRun
    {
        return CountryMonitorRun::create([
            'country_id' => $country->id,
            'focus' => 'social_protection_profile',
            'started_at' => now(),
            'sources_checked' => [],
            'items_found' => 0,
            'status' => 'running',
        ]);
    }

    private function finishRun(CountryMonitorRun $run, string $status, string $url, int $itemsFound, ?string $error = null): void
    {
        $run->update([
            'finished_at' => now(),
            'sources_checked' => [$url],
            'items_found' => $itemsFound,
            'status' => $status,
            'error_message' => $error,
        ]);
    }

    private function plainText(string $html): string
    {
        $withoutScripts = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($withoutScripts), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function digitizationSnippets(string $text): array
    {
        $lower = Str::lower($text);
        $snippets = [];

        foreach (self::DIGITIZATION_TERMS as $term) {
            $position = strpos($lower, $term);

            if ($position === false) {
                continue;
            }

            $start = max(0, $position - 220);
            $snippet = trim(substr($text, $start, 520));
            $snippet = preg_replace('/\s+/', ' ', $snippet) ?? $snippet;

            if ($snippet !== '') {
                $snippets[] = $snippet;
            }

            if (count($snippets) >= 4) {
                break;
            }
        }

        return array_values(array_unique($snippets));
    }

    private function storeDigitizationOpportunity(Country $country, string $url, array $snippets): void
    {
        $summary = 'ILO Social Protection country profile references digitization, systems, registries, online services, payments, ID, or related modernization signals. '
            . implode(' ', array_map(fn ($snippet) => 'Context: ' . $snippet, $snippets));

        $update = CountryUpdate::updateOrCreate(
            [
                'country_id' => $country->id,
                'source_url' => $url,
                'source_name' => 'ILO Social Protection Platform',
            ],
            [
                'title' => 'Digitization signal in social protection profile - ' . $country->name,
                'title_english' => 'Digitization signal in social protection profile - ' . $country->name,
                'title_original' => 'Digitization signal in social protection profile - ' . $country->name,
                'publication_date' => Carbon::today(),
                'retrieved_at' => now(),
                'summary' => Str::limit($summary, 4000, ''),
                'relevance_score' => 7.5,
                'review_status' => 'new',
            ]
        );

        CountryUpdateOpportunity::updateOrCreate(
            [
                'country_update_id' => $update->id,
                'product_id' => null,
            ],
            [
                'issue_area' => 'social_protection_digitization',
                'opportunity_stage' => 'candidate',
                'issue_summary' => Str::limit($summary, 4000, ''),
                'product_alignment' => 'Potential opportunity for social protection digitization, registry, case-management, payment, identity, data integration, or service-delivery modernization work.',
                'suggested_email' => null,
                'knowledge_chunk_ids' => [],
                'demo_feature_moment_ids' => [],
                'demo_frame_ids' => [],
            ]
        );
    }
}
