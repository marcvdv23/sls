<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\BankDomainGuessService;
use App\Services\CountryIntelligenceMonitor;
use App\Services\DemoMediaIngestionService;
use App\Services\KnowledgeChunkClassifier;
use App\Services\IntelligenceSourceCheckerService;
use App\Services\IloSocialProtectionProjectDiscoveryService;
use App\Services\SourceContactExtractionService;
use App\Services\SerpApiSourceDiscoveryService;
use App\Services\SocialProtectionProfileMonitor;
use App\Services\TenderAwardLookupService;
use App\Services\TenderDocumentProcessor;
use App\Services\TitleTranslationService;
use App\Services\UniversityMarketCrawlerService;
use App\Services\UniversitySurveyCrawlerService;
use App\Models\MarketCrawler;
use App\Models\MarketOrganizationContact;
use App\Models\SlsOperationRun;
use App\Models\UniversitySurveyTarget;
use App\Models\DemoSession;
use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryUpdate;
use App\Models\IntelligenceSource;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\CountryUpdateClassifier;
use App\Support\CountryUpdateDedupeRules;
use App\Support\CountryUpdateNoiseRules;
use App\Support\TitleLanguage;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sls:backup-local', function () {
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $scriptPath = base_path($isWindows ? 'scripts/backup-1g-sls.ps1' : 'scripts/backup-1g-sls.sh');

    if (! is_file($scriptPath)) {
        $this->error('Backup script was not found: ' . $scriptPath);

        return 1;
    }

    $command = $isWindows
        ? ['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $scriptPath]
        : ['bash', $scriptPath];

    $process = new \Symfony\Component\Process\Process($command, base_path());
    $process->setTimeout(900);
    $process->run();

    $output = trim($process->getOutput());
    $errorOutput = trim($process->getErrorOutput());

    if (! $process->isSuccessful()) {
        $this->error('Backup failed: ' . ($errorOutput ?: $output));

        return 1;
    }

    $this->info($output ?: 'Backup completed.');

    return 0;
})->purpose('Create a local database and project-file backup for 1G-SLS');

Artisan::command('sls:seed-intelligence-sources {--region= : Optional region name such as latin_america}', function () {
    $wantedRegion = Str::of((string) $this->option('region'))->lower()->replace('_', ' ')->trim()->toString();
    $createdOrUpdated = 0;

    collect(config('country_intelligence.development_partner_sources', []))
        ->when($wantedRegion !== '', fn ($sources) => $sources->filter(fn () => $wantedRegion === 'global'))
        ->each(function (array $source) use (&$createdOrUpdated) {
            IntelligenceSource::updateOrCreate([
                'country_iso' => null,
                'domain' => $source['domain'] ?? null,
                'url' => $source['url'] ?? null,
            ], [
                'name' => $source['name'] ?? $source['domain'] ?? 'Unnamed donor source',
                'source_class' => 'donor_tender_portal',
                'focus' => 'tenders',
                'access_method' => $source['access_method'] ?? null,
                'connector' => $source['connector'] ?? null,
                'procurement_portal_type' => $source['procurement_portal_type'] ?? 'international_donor',
                'is_enabled' => true,
            ]);
            $createdOrUpdated++;
        });

    collect(array_replace_recursive(
        config('country_intelligence.monitored_countries', []),
        config('country_intelligence.countries', [])
    ))
        ->filter(function (array $countryConfig) use ($wantedRegion) {
            return $wantedRegion === '' || Str::lower((string) ($countryConfig['region'] ?? '')) === $wantedRegion;
        })
        ->each(function (array $countryConfig, string $iso) use (&$createdOrUpdated) {
            foreach ($countryConfig['sources'] ?? [] as $source) {
                $sourceText = Str::lower(implode(' ', [
                    $source['category'] ?? '',
                    $source['name'] ?? '',
                    $source['domain'] ?? '',
                    $source['url'] ?? '',
                ]));
                $sourceClass = $source['source_class'] ?? match (true) {
                    Str::contains($sourceText, ['tender', 'procurement', 'rfp', 'gojep', 'ppc.gov', 'sicoes', 'comprar', 'compras', 'sicop', 'sercop', 'guatecompras', 'honducompras', 'panamacompra', 'contrataciones', 'seace', 'compra']) => 'central_tender_portal',
                    Str::contains($sourceText, ['social security', 'socialsecurity', 'seguro social', 'seguridad social', 'seguros sociales', 'prevision social', 'prevision', 'previdencia', 'pensiones', 'pension board', 'national insurance', 'ipres', 'cnss', 'nssf', 'anses', 'gestora', 'aps.gob', 'inss', 'imss', 'issste', 'iess', 'igss', 'ihss', 'onp', 'bps', 'ivss', 'colpensiones', 'spensiones']) => 'social_security_admin',
                    Str::contains($sourceText, ['ministry', 'ministere', 'ministerio', 'labour', 'labor', 'trabajo', 'government', 'gobierno', 'gazette']) => 'government',
                    default => 'local_media',
                };

                IntelligenceSource::updateOrCreate([
                    'country_iso' => strtoupper((string) $iso),
                    'domain' => $source['domain'] ?? null,
                    'url' => $source['url'] ?? null,
                ], [
                    'region' => $countryConfig['region'] ?? null,
                    'name' => $source['name'] ?? $source['domain'] ?? 'Unnamed source',
                    'source_class' => $sourceClass,
                    'focus' => $source['focus'] ?? ($sourceClass === 'central_tender_portal' ? 'tenders' : 'news'),
                    'access_method' => $source['access_method'] ?? null,
                    'connector' => $source['connector'] ?? $source['type'] ?? null,
                    'procurement_portal_type' => $source['procurement_portal_type'] ?? ($sourceClass === 'central_tender_portal' ? 'national' : 'not_applicable'),
                    'is_enabled' => true,
                ]);
                $createdOrUpdated++;
            }
        });

    $this->info('Seeded or updated ' . $createdOrUpdated . ' intelligence source record(s).');
})->purpose('Seed or update the intelligence source registry from country_intelligence.php');

Artisan::command('sls:check-intelligence-sources {--source= : Check one source id} {--source-class= : Restrict to a source class such as government} {--limit=25 : Max unknown/not-checked sources to check}', function (IntelligenceSourceCheckerService $checker) {
    $sourceId = (string) $this->option('source');

    if ($sourceId !== '') {
        $source = IntelligenceSource::query()->findOrFail((int) $sourceId);
        $result = $checker->check($source);
        $this->info('Checked SRC-' . str_pad((string) $source->id, 4, '0', STR_PAD_LEFT) . ' ' . $source->name . ': ' . ($result['ok'] ? 'OK' : 'Issue') . ', useful links/feeds ' . $result['items_found']);

        if ($result['error']) {
            $this->warn($result['error']);
        }

        return;
    }

    $results = $checker->checkUnknownSources(
        limit: (int) $this->option('limit'),
        sourceClass: $this->option('source-class') ? (string) $this->option('source-class') : null,
    );

    foreach ($results as $result) {
        $this->line('SRC-' . str_pad((string) $result['source_id'], 4, '0', STR_PAD_LEFT) . ' ' . $result['source_name'] . ': ' . ($result['ok'] ? 'OK' : 'Issue') . ', useful links/feeds ' . $result['items_found']);

        if ($result['error']) {
            $this->warn('  ' . $result['error']);
        }
    }

    $this->info('Checked ' . count($results) . ' source(s).');
})->purpose('Check unknown/not-checked intelligence sources with the generic website/feed crawler');

Artisan::command('sls:discover-country-sources {--region=Africa : Region to discover sources for} {--countries=0 : Limit number of countries for a test run} {--queries=8 : Search queries per country} {--results=8 : Search results per query} {--dry-run : Show candidates without saving}', function (SerpApiSourceDiscoveryService $discovery) {
    $result = $discovery->discover(
        region: (string) $this->option('region'),
        countryLimit: (int) $this->option('countries'),
        queriesPerCountry: (int) $this->option('queries'),
        resultsPerQuery: (int) $this->option('results'),
        dryRun: (bool) $this->option('dry-run'),
    );

    $this->info('SerpAPI country source discovery');
    $this->line('Region: ' . $this->option('region'));
    $this->line('Countries selected: ' . $result['countries']);
    $this->line('Queries prepared/sent: ' . $result['queries']);
    $this->line('Candidates found: ' . $result['candidates']);
    $this->line('Created: ' . $result['created'] . ', updated: ' . $result['updated']);

    foreach (array_slice($result['items'], 0, 30) as $item) {
        $this->line('');
        $this->line('[' . $item['country_iso'] . '] ' . $item['country'] . ' - ' . $item['name']);
        $this->line('  ' . $item['source_class'] . ' | confidence ' . $item['confidence'] . ' | ' . $item['domain']);
        $this->line('  ' . $item['url']);
        $this->line('  Query: ' . $item['query']);
    }

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }
})->purpose('Use SerpAPI to discover official social security, labour ministry, civil service, and procurement sources by country');

Artisan::command('sls:discover-social-security-sources {--region=Africa : Region to discover sources for} {--countries=0 : Limit number of countries for a test run} {--queries=8 : Search queries per country} {--results=8 : Search results per query} {--dry-run : Show candidates without saving}', function (SerpApiSourceDiscoveryService $discovery) {
    $this->call('sls:discover-country-sources', [
        '--region' => (string) $this->option('region'),
        '--countries' => (int) $this->option('countries'),
        '--queries' => (int) $this->option('queries'),
        '--results' => (int) $this->option('results'),
        '--dry-run' => (bool) $this->option('dry-run'),
    ]);
})->purpose('Alias for sls:discover-country-sources');

Artisan::command('sls:guess-bank-domains {--limit=50 : Maximum bank records to process} {--region= : Optional region such as Africa or Asia} {--country=* : Optional country ISO/name, repeatable} {--no-retry : Skip rows previously marked domain_guess_not_found} {--dry-run : Check and report without saving website fields} {--pause=1 : Seconds to pause between banks} {--confidence=70 : Minimum confidence required to save} {--checks=12 : Maximum top-ranked domain guesses to verify per bank}', function (BankDomainGuessService $service) {
    $result = $service->run(
        limit: (int) $this->option('limit'),
        region: $this->option('region') ? (string) $this->option('region') : null,
        countries: (array) $this->option('country'),
        retryPreviousNotFound: ! (bool) $this->option('no-retry'),
        dryRun: (bool) $this->option('dry-run'),
        pauseSeconds: max(0, (int) $this->option('pause')),
        minimumConfidence: max(1, min(100, (int) $this->option('confidence'))),
        maxChecksPerBank: max(1, (int) $this->option('checks')),
    );

    $this->info('Bank domain guessing finished.');
    $this->line('Eligible banks without website/domain: ' . $result['eligible']);
    $this->line('Processed: ' . $result['processed']);
    $this->line('Ready for crawl: ' . $result['ready_for_crawl']);
    $this->line('Domain guess not found: ' . $result['domain_guess_not_found']);
    $this->line('Guesses prepared: ' . $result['guesses']);
    $this->line('Guesses checked: ' . $result['checks']);

    foreach (array_slice($result['items'], 0, 25) as $index => $item) {
        $line = ($index + 1) . '/' . $result['processed'] . ' ' . $item['name'] . ' - ' . $item['status'] . ' - ' . $item['checked'] . '/' . $item['guesses'] . ' guess(es)';

        if ($item['best_domain']) {
            $line .= ' - ' . $item['best_domain'] . ' (' . $item['confidence'] . '%)';
        }

        $this->line($line);
    }

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }

    return 0;
})->purpose('Guess and verify official bank domains using bank-specific domain rules');

Artisan::command('sls:run-operation {operation_run_id}', function (BankDomainGuessService $bankDomainGuesser) {
    $run = SlsOperationRun::query()->findOrFail((int) $this->argument('operation_run_id'));

    if ($run->operation_key !== 'bank_domain_guesser') {
        $run->update([
            'status' => 'failed',
            'error_message' => 'Unknown operation key: ' . $run->operation_key,
            'finished_at' => now(),
        ]);

        $this->error('Unknown operation key: ' . $run->operation_key);

        return 1;
    }

    $parameters = $run->parameters ?? [];
    $items = [];

    $run->update([
        'status' => 'running',
        'started_at' => now(),
        'error_message' => null,
        'items' => [],
        'summary' => [
            'eligible' => 0,
            'processed' => 0,
            'ready_for_crawl' => 0,
            'domain_guess_not_found' => 0,
            'guesses' => 0,
            'checks' => 0,
        ],
    ]);

    try {
        $result = $bankDomainGuesser->run(
            limit: (int) ($parameters['limit'] ?? 50),
            region: filled($parameters['region'] ?? null) ? (string) $parameters['region'] : null,
            countries: (array) ($parameters['countries'] ?? []),
            retryPreviousNotFound: (bool) ($parameters['retry_previous_not_found'] ?? true),
            dryRun: (bool) ($parameters['dry_run'] ?? true),
            pauseSeconds: max(0, (int) ($parameters['pause'] ?? 1)),
            minimumConfidence: max(1, min(100, (int) ($parameters['confidence'] ?? 70))),
            maxChecksPerBank: max(1, (int) ($parameters['checks'] ?? 12)),
            onItem: function (array $item, array $summary) use ($run, &$items) {
                $items[] = array_merge($item, [
                    'attempted_at' => now()->toDateTimeString(),
                ]);

                $run->forceFill([
                    'summary' => $summary,
                    'items' => $items,
                    'processed_count' => (int) ($summary['processed'] ?? count($items)),
                    'total_count' => (int) ($summary['eligible'] ?? 0),
                    'success_count' => (int) ($summary['ready_for_crawl'] ?? 0),
                    'failure_count' => (int) ($summary['domain_guess_not_found'] ?? 0),
                ])->save();
            },
        );

        $run->update([
            'status' => 'completed',
            'summary' => $result,
            'items' => $result['items'],
            'processed_count' => (int) $result['processed'],
            'total_count' => (int) $result['eligible'],
            'success_count' => (int) $result['ready_for_crawl'],
            'failure_count' => (int) $result['domain_guess_not_found'],
            'finished_at' => now(),
        ]);

        $this->info('Operation run #' . $run->id . ' completed.');

        return 0;
    } catch (Throwable $exception) {
        $run->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);

        $this->error($exception->getMessage());

        return 1;
    }
})->purpose('Run a saved SLS operation and record progress for the web runner');

Artisan::command('sls:crawler-discovery-pilot {crawler_id} {--country= : Optional country name or ISO code} {--organizations=10 : Number of organizations} {--calls=10 : Maximum SerpAPI calls} {--results=5 : Results per query} {--batch-id= : Existing market_crawler_runs batch row to update} {--organization-ids= : Comma-separated staged organization IDs}', function (UniversityMarketCrawlerService $service) {
    $crawler = MarketCrawler::query()->findOrFail((int) $this->argument('crawler_id'));
    $crawlerProfile = $service->profile($crawler);
    $batchRun = $this->option('batch-id') ? \App\Models\MarketCrawlerRun::query()->find((int) $this->option('batch-id')) : null;
    $organizationIds = collect(explode(',', (string) $this->option('organization-ids')))
        ->map(fn ($id) => (int) trim($id))
        ->filter()
        ->unique()
        ->values()
        ->all();

    try {
        $result = $service->runDiscoveryPilot(
            crawler: $crawler,
            country: $this->option('country') ? (string) $this->option('country') : null,
            organizationLimit: (int) $this->option('organizations'),
            maxCalls: (int) $this->option('calls'),
            resultsPerQuery: (int) $this->option('results'),
            organizationIds: $organizationIds,
        );
    } catch (\Throwable $exception) {
        $batchRun?->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);

        throw $exception;
    }

    $batchStatus = $result['errors'] === [] ? 'completed' : 'warning';
    $batchMessage = 'Organizations selected: ' . $result['organizations']
        . '; SerpAPI calls made: ' . $result['queries']
        . '; URLs updated: ' . $result['urls_updated'];

    if ((int) $result['queries'] === 0 && (int) $result['organizations'] > 0 && $result['errors'] === []) {
        $batchMessage .= '. No calls were needed because the selected records already had main website URLs.';
    }

    $batchRun?->update([
        'status' => $batchStatus,
        'items_found' => (int) $result['queries'],
        'urls_updated' => (int) $result['urls_updated'],
        'result_payload' => [
            'organizations' => $result['organizations'],
            'queries' => $result['queries'],
            'urls_updated' => $result['urls_updated'],
            'errors' => $result['errors'],
            'summary' => $batchMessage,
        ],
        'error_message' => $result['errors'] === [] ? null : implode(' | ', array_slice($result['errors'], 0, 3)),
        'response_excerpt' => $batchMessage,
        'finished_at' => now(),
    ]);

    $this->info(ucfirst($crawlerProfile['singular']) . ' discovery pilot finished.');
    $this->line('Organizations selected: ' . $result['organizations']);
    $this->line('SerpAPI queries sent/prepared: ' . $result['queries']);
    $this->line('URLs updated: ' . $result['urls_updated']);

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }

    return 0;
})->purpose('Run a crawler SerpAPI discovery pilot and log each call/result');

Artisan::command('sls:crawler-crawl-pilot {crawler_id} {--country= : Optional country name or ISO code} {--organizations=10 : Number of organizations} {--pages=6 : Maximum pages per organization} {--batch-id= : Existing market_crawler_runs batch row to update} {--organization-ids= : Comma-separated staged organization IDs}', function (UniversityMarketCrawlerService $service) {
    $crawler = MarketCrawler::query()->findOrFail((int) $this->argument('crawler_id'));
    $crawlerProfile = $service->profile($crawler);
    $batchRun = $this->option('batch-id') ? \App\Models\MarketCrawlerRun::query()->find((int) $this->option('batch-id')) : null;
    $organizationIds = collect(explode(',', (string) $this->option('organization-ids')))
        ->map(fn ($id) => (int) trim($id))
        ->filter()
        ->unique()
        ->values()
        ->all();

    try {
        $result = $service->runCrawlPilot(
            crawler: $crawler,
            country: $this->option('country') ? (string) $this->option('country') : null,
            organizationLimit: (int) $this->option('organizations'),
            pageLimit: (int) $this->option('pages'),
            organizationIds: $organizationIds,
        );
    } catch (\Throwable $exception) {
        $batchRun?->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);

        throw $exception;
    }

    $batchStatus = $result['errors'] === [] ? 'completed' : 'warning';
    $batchMessage = 'Organizations selected: ' . $result['organizations']
        . '; pages checked: ' . $result['pages_checked']
        . '; URL fields updated: ' . $result['urls_updated']
        . '; contacts found: ' . $result['contacts_found'];

    $batchRun?->update([
        'status' => $batchStatus,
        'items_found' => (int) $result['pages_checked'],
        'urls_updated' => (int) $result['urls_updated'],
        'contacts_found' => (int) $result['contacts_found'],
        'result_payload' => [
            'organizations' => $result['organizations'],
            'pages_checked' => $result['pages_checked'],
            'urls_updated' => $result['urls_updated'],
            'contacts_found' => $result['contacts_found'],
            'errors' => $result['errors'],
            'summary' => $batchMessage,
        ],
        'error_message' => $result['errors'] === [] ? null : implode(' | ', array_slice($result['errors'], 0, 3)),
        'response_excerpt' => $batchMessage,
        'finished_at' => now(),
    ]);

    $this->info(ucfirst($crawlerProfile['singular']) . ' crawl pilot finished.');
    $this->line('Organizations selected: ' . $result['organizations']);
    $this->line('Pages checked: ' . $result['pages_checked']);
    $this->line('URL fields updated: ' . $result['urls_updated']);
    $this->line('Contacts found: ' . $result['contacts_found']);

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }

    return 0;
})->purpose('Run a direct crawler pilot and log each page result');

Artisan::command('sls:clean-crawler-contacts {--limit=5000 : Maximum crawler contact records to scan}', function (UniversityMarketCrawlerService $service) {
    $scanned = 0;
    $updated = 0;

    MarketOrganizationContact::query()
        ->whereNotNull('email')
        ->where('email', '<>', '')
        ->orderByDesc('id')
        ->limit(max(1, (int) $this->option('limit')))
        ->get()
        ->each(function (MarketOrganizationContact $contact) use ($service, &$scanned, &$updated) {
            $scanned++;
            [$personName, $jobTitle, $status] = $service->normalizeCapturedContactIdentity(
                $contact->person_name,
                $contact->job_title,
                (string) $contact->email
            );

            $changes = [];

            if ($contact->person_name !== $personName) {
                $changes['person_name'] = $personName;
            }

            if ($contact->job_title !== $jobTitle) {
                $changes['job_title'] = $jobTitle;
            }

            if ($status !== 'published' && $contact->verification_status !== $status) {
                $changes['verification_status'] = $status;
            }

            if ($changes !== []) {
                $contact->update($changes);
                $updated++;
            }
        });

    $this->info("Crawler contact cleanup scanned {$scanned} records and updated {$updated}.");

    return 0;
})->purpose('Clean bad crawler contact names and mark generic role emails for name research');

Artisan::command('sls:resolve-crawler-contact-names {--limit=200 : Maximum unresolved contacts to revisit}', function (UniversityMarketCrawlerService $service) {
    $result = $service->resolveContactNamesFromSourcePages((int) $this->option('limit'));

    $this->info('Crawler contact name resolver');
    $this->line('Contacts scanned: ' . $result['scanned']);
    $this->line('Source pages checked: ' . $result['pages_checked']);
    $this->line('Names resolved: ' . $result['resolved']);

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }

    return 0;
})->purpose('Revisit original source pages to resolve missing names for crawler email contacts');

Artisan::command('sls:intelligence-monitor {countries?* : Country names or ISO codes} {--focus=social_security : Intelligence focus key} {--region= : Restrict to a region such as Caribbean or Africa} {--max=10 : Maximum stored items per country} {--cycle= : Monitor a rotating batch of this many configured countries} {--slot= : Cycle slot number for this run} {--slots-per-day= : Number of cycle slots per day} {--plan : Show selected countries without searching} {--dry-run : Search without saving}', function (CountryIntelligenceMonitor $monitor) {
    if ((bool) $this->option('plan')) {
        $countries = $monitor->plan(
            countryKeys: (array) $this->argument('countries'),
            cycleSize: $this->option('cycle') === null ? null : (int) $this->option('cycle'),
            cycleSlot: $this->option('slot') === null ? null : (int) $this->option('slot'),
            slotsPerDay: $this->option('slots-per-day') === null ? null : (int) $this->option('slots-per-day'),
            region: $this->option('region') === null ? null : (string) $this->option('region'),
        );

        $this->info($countries->count() . ' selected country profile(s) for focus [' . $this->option('focus') . '].');
        foreach ($countries as $isoCode => $countryConfig) {
            $this->line($isoCode . ' - ' . $countryConfig['name'] . ' (' . ($countryConfig['region'] ?? 'region not set') . ')');
        }

        return;
    }

    $results = $monitor->run(
        countryKeys: (array) $this->argument('countries'),
        maxResults: (int) $this->option('max'),
        dryRun: (bool) $this->option('dry-run'),
        cycleSize: $this->option('cycle') === null ? null : (int) $this->option('cycle'),
        cycleSlot: $this->option('slot') === null ? null : (int) $this->option('slot'),
        slotsPerDay: $this->option('slots-per-day') === null ? null : (int) $this->option('slots-per-day'),
        focus: (string) $this->option('focus'),
        region: $this->option('region') === null ? null : (string) $this->option('region'),
    );

    foreach ($results as $isoCode => $result) {
        $this->line('');
        $this->info($result['country'] . ' [' . $isoCode . ']');
        $this->line('Focus: ' . $result['focus']);
        $this->line('Sources checked: ' . implode(', ', $result['sources_checked']));
        $this->line('Relevant items found: ' . $result['items_found']);

        foreach ($result['items'] as $item) {
            $this->line('- ' . $item['title']);
            $this->line('  ' . $item['source_url']);
        }
    }

    if ($results === []) {
        $this->warn('No configured country matched the requested input.');
    }
})->purpose('Search and store social security, pension, labour ministry, procurement, and tender updates for monitored countries');

Artisan::command('sls:monitor-social-protection-profiles {countries?* : Country names or ISO codes} {--limit=0 : Maximum countries to check} {--ensure-urls : Only populate country profile URLs without fetching pages} {--dry-run : Show selected profiles without fetching pages}', function (SocialProtectionProfileMonitor $monitor) {
    if ((bool) $this->option('ensure-urls')) {
        $updated = $monitor->ensureProfileUrls();
        $this->info('Social Protection profile URLs updated: ' . $updated);

        return 0;
    }

    $result = $monitor->run(
        countryKeys: (array) $this->argument('countries'),
        limit: (int) $this->option('limit'),
        dryRun: (bool) $this->option('dry-run'),
    );

    $this->info('Social Protection country profile monitor');
    $this->line('Profiles checked: ' . $result['checked']);
    $this->line('Digitization signals highlighted: ' . $result['digitization_signals']);

    foreach (array_slice($result['errors'], 0, 20) as $error) {
        $this->warn($error);
    }

    return 0;
})->purpose('Check ILO Social Protection country profiles and highlight digitization references as opportunities');

Artisan::command('sls:discover-ilo-social-protection-projects {countries?* : Country names or ISO codes} {--region= : Restrict to a region such as Europe or Africa} {--limit=0 : Maximum countries to search} {--queries=3 : SerpAPI queries per country} {--results=5 : Search results per query} {--dry-run : Search without saving}', function (IloSocialProtectionProjectDiscoveryService $discovery) {
    $result = $discovery->discover(
        countryKeys: (array) $this->argument('countries'),
        region: $this->option('region') ?: null,
        countryLimit: (int) $this->option('limit'),
        queriesPerCountry: (int) $this->option('queries'),
        resultsPerQuery: (int) $this->option('results'),
        dryRun: (bool) $this->option('dry-run'),
    );

    $this->info('ILO Social Protection project discovery finished.');
    $this->line('Countries searched: ' . $result['countries']);
    $this->line('SerpAPI queries: ' . $result['queries']);
    $this->line('Project pages found: ' . $result['found']);
    $this->line('Updates stored: ' . $result['stored']);

    foreach (array_slice($result['items'], 0, 10) as $item) {
        $this->line('- ' . $item['country'] . ': ' . $item['title']);
        $this->line('  ' . $item['source_url']);
    }

    foreach (array_slice($result['errors'], 0, 10) as $error) {
        $this->warn($error);
    }

    return $result['errors'] === [] ? 0 : 1;
})->purpose('Discover ILO Social Protection Contribution.action project pages via SerpAPI indexed search');

Artisan::command('sls:global-tender-sweep {--focus=hrms_tenders : Tender focus key} {--max=80 : Maximum stored items for the global sweep} {--dry-run : Search without saving}', function (CountryIntelligenceMonitor $monitor) {
    $focus = (string) $this->option('focus');
    $countryResults = $monitor->run(
        maxResults: (int) $this->option('max'),
        dryRun: (bool) $this->option('dry-run'),
        focus: $focus,
        region: 'africa_asia_caribbean_latin_america_north_america_europe',
    );
    $items = collect($countryResults)->flatMap(fn (array $result) => collect($result['items'] ?? [])->map(fn (array $item) => $item + ['country' => $result['country'] ?? null]));
    $sourcesChecked = collect($countryResults)->flatMap(fn (array $result) => $result['sources_checked'] ?? [])->filter()->unique()->values()->all();

    $this->info('Global donor/aggregator tender sweep');
    $this->line('Focus: ' . $focus);
    $this->line('Countries checked: ' . count($countryResults));
    $this->line('Sources checked: ' . implode(', ', $sourcesChecked));
    $this->line('Relevant items found: ' . $items->count());

    foreach ($items->take(30) as $item) {
        $this->line('');
        $this->line('[' . ($item['country_iso'] ?? 'ZZ') . '] ' . ($item['country'] ?? 'Global / Unassigned'));
        $this->line('- ' . ($item['title_english'] ?: $item['title']));
        $this->line('  ' . $item['source_url']);
    }
})->purpose('Search donor banks, aid portals, TED, DevelopmentAid, and tender aggregators globally by topic, then assign hits to countries');

Artisan::command('sls:global-news-sweep {--max=120 : Maximum stored social-security news items for the global sweep} {--dry-run : Search without saving}', function (CountryIntelligenceMonitor $monitor) {
    $countryResults = $monitor->run(
        maxResults: (int) $this->option('max'),
        dryRun: (bool) $this->option('dry-run'),
        focus: 'social_security',
        region: 'africa_asia_caribbean_latin_america_north_america_europe',
    );
    $items = collect($countryResults)->flatMap(fn (array $result) => collect($result['items'] ?? [])->map(fn (array $item) => $item + ['country' => $result['country'] ?? null]));
    $sourcesChecked = collect($countryResults)->flatMap(fn (array $result) => $result['sources_checked'] ?? [])->filter()->unique()->values()->all();

    $this->info('Global pension/social-security news sweep');
    $this->line('Focus: social_security');
    $this->line('Countries checked: ' . count($countryResults));
    $this->line('Sources checked: ' . implode(', ', $sourcesChecked));
    $this->line('Relevant items found: ' . $items->count());

    foreach ($items->take(30) as $item) {
        $this->line('');
        $this->line('[' . ($item['country_iso'] ?? '') . '] ' . ($item['country'] ?? 'Unassigned'));
        $this->line('- ' . ($item['title_english'] ?: $item['title']));
        $this->line('  ' . $item['source_url']);
    }
})->purpose('Search global news aggregators for pension and social-security stories, then assign hits to countries');

Artisan::command('sls:translate-country-update-titles {--limit=50 : Maximum records to translate} {--id=* : Specific country update id, repeatable} {--dry-run : Show translations without saving}', function (TitleTranslationService $translator) {
    $ids = collect((array) $this->option('id'))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();
    $limit = max(1, (int) $this->option('limit'));
    $dryRun = (bool) $this->option('dry-run');

    $query = CountryUpdate::query()
        ->with('country')
        ->when($ids->isNotEmpty(), fn ($builder) => $builder->whereIn('id', $ids))
        ->latest('retrieved_at')
        ->limit($ids->isNotEmpty() ? $ids->count() : max($limit * 20, 2000));

    $checked = 0;
    $translatedTitles = 0;
    $translatedSummaries = 0;
    $failed = 0;

    foreach ($query->get() as $update) {
        $originalTitle = trim((string) ($update->title_original ?: $update->title));
        $currentEnglish = trim((string) $update->title_english);
        $summary = trim((string) $update->summary);
        $currentEnglishSummary = trim((string) ($update->summary_english ?? ''));
        $isGeneratedEnglishSummary = Str::contains(Str::lower($summary), [
            '[social security intelligence]',
            '[hrms tender intelligence]',
            '[erms tender intelligence]',
            '[ebpc tender intelligence]',
        ]);
        $needsTitle = $originalTitle !== ''
            && ! TitleLanguage::isUsableEnglishTitle($currentEnglish, $originalTitle)
            && TitleLanguage::looksNonEnglish($originalTitle);
        $needsSummary = $summary !== ''
            && $currentEnglishSummary === ''
            && ! $isGeneratedEnglishSummary
            && TitleLanguage::looksNonEnglish($summary);

        if (! $needsTitle && ! $needsSummary) {
            continue;
        }

        $checked++;

        if ($ids->isEmpty() && $checked > $limit) {
            break;
        }

        $changes = [];

        if ($needsTitle) {
            $candidate = $translator->toEnglish(
                $originalTitle,
                (string) $update->source_url,
                (string) ($update->country?->name ?? ''),
                (string) $update->source_name
            );

            if (TitleLanguage::isUsableEnglishTitle($candidate, $originalTitle)) {
                $translatedTitles++;
                $changes['title_english'] = Str::limit($candidate, 500, '');
                $this->line('INT-' . str_pad((string) $update->id, 5, '0', STR_PAD_LEFT) . ' title: ' . $candidate);
            } else {
                $failed++;
                $this->warn('No title translation kept for INT-' . str_pad((string) $update->id, 5, '0', STR_PAD_LEFT) . ': ' . $originalTitle);
                $changes['title_english'] = '';
            }
        }

        if ($needsSummary) {
            $candidate = $translator->summaryToEnglish(
                $summary,
                (string) $update->source_url,
                (string) ($update->country?->name ?? ''),
                (string) $update->source_name
            );

            if (TitleLanguage::isUsableEnglishTitle($candidate, $summary)) {
                $translatedSummaries++;
                $changes['summary_english'] = $candidate;
                $this->line('INT-' . str_pad((string) $update->id, 5, '0', STR_PAD_LEFT) . ' summary: ' . Str::limit($candidate, 180));
            } else {
                $failed++;
                $this->warn('No summary translation kept for INT-' . str_pad((string) $update->id, 5, '0', STR_PAD_LEFT) . '.');
            }
        }

        if (! $dryRun && $changes !== []) {
            $update->forceFill($changes)->save();
        }
    }

    $this->info('Checked ' . $checked . ' update(s). Titles translated: ' . $translatedTitles . '. Summaries translated: ' . $translatedSummaries . '. Pending: ' . $failed . '.');
})->purpose('Translate country update titles and summaries where English text is blank or copied from a non-English original');

Artisan::command('sls:extract-source-contacts {updateId? : Optional country update id} {--limit=25 : Maximum source records to scan when no id is supplied}', function (SourceContactExtractionService $extractor) {
    $updateId = $this->argument('updateId');

    $query = CountryUpdate::query()
        ->with('country')
        ->whereNotNull('source_url')
        ->latest('retrieved_at');

    if ($updateId) {
        $query->whereKey((int) $updateId);
    } else {
        $query->limit(max(1, (int) $this->option('limit')));
    }

    $processed = 0;
    $created = 0;
    $updated = 0;

    foreach ($query->get() as $update) {
        $this->info('Scanning update #' . $update->id . ': ' . ($update->title_english ?: $update->title));
        $result = $extractor->extractForUpdate($update);
        $processed++;
        $created += $result['contacts_created'];
        $updated += $result['contacts_updated'];
        $this->line('  Documents checked: ' . $result['documents_checked']);
        $this->line('  Email references found: ' . $result['contacts_found']);
        $this->line('  Created: ' . $result['contacts_created'] . ', updated: ' . $result['contacts_updated']);

        foreach ($result['errors'] as $error) {
            $this->warn('  ' . $error);
        }
    }

    $this->info('Done. Processed ' . $processed . ' source update(s). Created ' . $created . ', updated ' . $updated . ' contact reference(s).');
})->purpose('Read tender/source PDFs and pages, extract email addresses, and store contact references with context');

Artisan::command('sls:check-tender-awards {updateId? : Optional country update id} {--limit=25 : Maximum unchecked tender records to scan when no id is supplied}', function (TenderAwardLookupService $awardLookup) {
    $updateId = $this->argument('updateId');

    $query = CountryUpdate::query()
        ->with('country')
        ->whereNotNull('source_url')
        ->where(function ($builder) {
            $builder->where('title', 'like', '%tender%')
                ->orWhere('title', 'like', '%bid%')
                ->orWhere('title', 'like', '%procurement%')
                ->orWhere('title', 'like', '%request for%')
                ->orWhere('summary', 'like', '%tender%')
                ->orWhere('summary', 'like', '%procurement%');
        })
        ->orderByRaw('award_checked_at IS NULL DESC')
        ->latest('retrieved_at');

    if ($updateId) {
        $query->whereKey((int) $updateId);
    } else {
        $query->limit(max(1, (int) $this->option('limit')));
    }

    $processed = 0;
    $awarded = 0;

    foreach ($query->get() as $update) {
        $this->info('Checking update #' . $update->id . ': ' . ($update->title_english ?: $update->title));
        $result = $awardLookup->check($update);
        $processed++;

        if ($result['status'] === 'awarded') {
            $awarded++;
            $this->line('  Awarded: ' . $result['title']);
            $this->line('  ' . $result['url']);
        } else {
            $this->line('  Award status: ' . $result['status']);
            $this->line('  ' . $result['context']);
        }
    }

    $this->info('Done. Checked ' . $processed . ' tender(s). Award records found: ' . $awarded . '.');
})->purpose('Check whether stored tender/RFP records have matching award or contract-award records in the same source family');

Artisan::command('sls:process-tender-documents {--limit=20 : Maximum relevant tender records to process}', function (TenderDocumentProcessor $processor) {
    $result = $processor->process((int) $this->option('limit'));

    $this->info('Processed ' . $result['processed'] . ' relevant tender record(s).');
    $this->line('Documents archived: ' . $result['documents_archived']);
    $this->line('Contacts created: ' . $result['contacts_created']);
    $this->line('Contacts updated: ' . $result['contacts_updated']);
    $this->line('Award records found: ' . $result['awards_found']);
})->purpose('Automatically archive relevant tender documents, extract email contacts, and check for award records');

Artisan::command('sls:crawl-university-survey-contacts {targetIds?* : Optional university target ids} {--limit=10 : Maximum pending targets to crawl} {--pages=12 : Maximum pages per university}', function (UniversitySurveyCrawlerService $crawler) {
    $targetIds = collect($this->argument('targetIds'))->filter()->map(fn ($id) => (int) $id)->filter()->values();
    $limit = max(1, (int) $this->option('limit'));
    $pages = max(3, (int) $this->option('pages'));

    $targets = UniversitySurveyTarget::query()
        ->when($targetIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $targetIds))
        ->when($targetIds->isEmpty(), fn ($query) => $query
            ->where('status', '!=', 'disabled')
            ->where(function ($nested) {
                $nested->whereNull('next_crawl_at')->orWhere('next_crawl_at', '<=', now());
            }))
        ->orderByRaw("FIELD(status, 'pending', 'error', 'crawled')")
        ->orderBy('next_crawl_at')
        ->limit($targetIds->isEmpty() ? $limit : 500)
        ->get();

    if ($targets->isEmpty()) {
        $this->warn('No university survey targets are due for crawling.');

        return;
    }

    foreach ($targets as $target) {
        $this->line('Crawling #' . $target->id . ' ' . $target->name . ' - ' . $target->website_url);
        $result = $crawler->crawlTarget($target, $pages);
        $this->line('  Pages checked: ' . $result['pages_checked']);
        $this->line('  Contacts found/updated this run: ' . $result['contacts_found']);
        $this->line('  Published email patterns: ' . implode(', ', $result['patterns']));

        foreach ($result['errors'] as $error) {
            $this->error('  ' . $error);
        }
    }
})->purpose('Crawl public university websites for survey outreach contacts with source traceability');

Artisan::command('sls:classify-knowledge-chunks {--only-missing : Only classify chunks without a content type}', function (KnowledgeChunkClassifier $classifier) {
    $query = KnowledgeChunk::query()->with('sourceDocument');

    if ((bool) $this->option('only-missing')) {
        $query->whereNull('content_type');
    }

    $count = 0;

    $query->orderBy('id')->chunkById(200, function ($chunks) use ($classifier, &$count) {
        foreach ($chunks as $chunk) {
            $classification = $classifier->classify(
                (string) $chunk->chunk_title,
                (string) $chunk->chunk_text,
                $chunk->sourceDocument?->source_type,
            );

            $chunk->forceFill($classification)->save();
            $count++;
        }
    });

    $this->info($count . ' knowledge chunk(s) classified.');
})->purpose('Classify existing knowledge chunks by answer content type and business area');

Artisan::command('sls:process-demo-media {sessionId : Demo session id}', function (DemoMediaIngestionService $ingestion) {
    $session = DemoSession::findOrFail((int) $this->argument('sessionId'));
    $result = $ingestion->process($session);

    $this->info('Processed demo session: ' . $session->title);
    $this->line('Frames: ' . $result['frames']);
    $this->line('Transcript segments: ' . $result['transcript_segments']);
    $this->line('OCR frames: ' . $result['ocr_frames']);
    $this->line('Blank/loading frames rejected: ' . ($result['blank_frames_rejected'] ?? 0));
    $this->line('Feature moments: ' . $result['feature_moments']);
})->purpose('Extract screenshots, OCR, transcript segments, and searchable demo moments for chat');

Artisan::command('sls:reject-blank-demo-frames {sessionId? : Optional demo session id}', function (DemoMediaIngestionService $ingestion) {
    $sessionId = $this->argument('sessionId');
    $session = $sessionId ? DemoSession::findOrFail((int) $sessionId) : null;
    $count = $ingestion->rejectBlankFrames($session);

    $this->info($count . ' mostly blank/loading demo screenshot(s) rejected.');
})->purpose('Reject mostly blank or loading-state demo screenshots so chat will not show them');

Artisan::command('sls:process-demo-media-batch {sessionIds : Comma-separated demo session ids}', function (DemoMediaIngestionService $ingestion) {
    $ids = collect(explode(',', (string) $this->argument('sessionIds')))
        ->map(fn (string $id) => (int) trim($id))
        ->filter()
        ->values();

    if ($ids->isEmpty()) {
        $this->warn('No demo session ids were provided.');

        return;
    }

    foreach ($ids as $id) {
        $session = DemoSession::find($id);

        if (! $session) {
            $this->warn('Demo session not found: ' . $id);

            continue;
        }

        $this->info('Processing demo session ' . $session->id . ': ' . $session->title);

        try {
            $result = $ingestion->process($session);
            $this->line('Frames: ' . $result['frames']);
            $this->line('Transcript segments: ' . $result['transcript_segments']);
            $this->line('OCR frames: ' . $result['ocr_frames']);
            $this->line('Blank/loading frames rejected: ' . ($result['blank_frames_rejected'] ?? 0));
            $this->line('Feature moments: ' . $result['feature_moments']);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Failed: ' . $exception->getMessage());
        }
    }
})->purpose('Process multiple demo media sessions sequentially');

Artisan::command('sls:cleanup-cross-country-duplicates {--apply : Mark wrong-country source-domain duplicates as rejected}', function () {
    $normalizeDomain = function (?string $domain): ?string {
        $domain = Str::lower(trim((string) $domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/\/.*$/', '', (string) $domain);
        $domain = preg_replace('/:\d+$/', '', (string) $domain);
        $domain = preg_replace('/^www\./', '', (string) $domain);

        return filled($domain) ? $domain : null;
    };

    $hostFromUrl = function (?string $url) use ($normalizeDomain): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = parse_url(Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://' . $url, PHP_URL_HOST);

        return $normalizeDomain($host);
    };

    $hostMatchesDomain = function (string $host, string $domain): bool {
        return $host === $domain || Str::endsWith($host, '.' . $domain);
    };

    $countriesByIso = Country::query()
        ->whereNotNull('iso_code')
        ->get(['id', 'name', 'iso_code'])
        ->keyBy(fn (Country $country) => Str::upper((string) $country->iso_code));

    $domainsByIso = collect();

    collect(array_replace_recursive(
        config('country_intelligence.monitored_countries', []),
        config('country_intelligence.countries', [])
    ))->each(function (array $countryConfig, string $iso) use (&$domainsByIso, $normalizeDomain): void {
        $iso = Str::upper($iso);

        foreach ($countryConfig['sources'] ?? [] as $source) {
            $domain = $normalizeDomain($source['domain'] ?? $source['url'] ?? null);

            if ($domain) {
                $domainsByIso->push(['iso' => $iso, 'domain' => $domain]);
            }
        }
    });

    if (Schema::hasTable('intelligence_sources')) {
        IntelligenceSource::query()
            ->where('is_enabled', true)
            ->whereNotNull('country_iso')
            ->whereNotNull('domain')
            ->get(['country_iso', 'domain'])
            ->each(function (IntelligenceSource $source) use (&$domainsByIso, $normalizeDomain): void {
                $domain = $normalizeDomain($source->domain);

                if ($domain) {
                    $domainsByIso->push([
                        'iso' => Str::upper((string) $source->country_iso),
                        'domain' => $domain,
                    ]);
                }
            });
    }

    $domainsByIso = $domainsByIso
        ->filter(fn (array $row) => filled($row['iso'] ?? null) && filled($row['domain'] ?? null))
        ->unique(fn (array $row) => $row['iso'] . '|' . $row['domain'])
        ->sortByDesc(fn (array $row) => strlen($row['domain']))
        ->values();

    $candidates = collect();
    $scanned = 0;

    CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', 'unreviewed')
        ->whereNotNull('source_url')
        ->orderBy('id')
        ->chunkById(500, function ($updates) use (&$candidates, &$scanned, $hostFromUrl, $hostMatchesDomain, $domainsByIso, $countriesByIso): void {
            foreach ($updates as $update) {
                $scanned++;
                $host = $hostFromUrl($update->source_url);
                $itemIso = Str::upper((string) $update->country?->iso_code);

                if (! $host || ! $itemIso) {
                    continue;
                }

                $owner = $domainsByIso->first(fn (array $row) => $hostMatchesDomain($host, $row['domain']));

                if (! $owner || $owner['iso'] === $itemIso) {
                    continue;
                }

                $ownerCountry = $countriesByIso->get($owner['iso']);

                $candidates->push([
                    'id' => $update->id,
                    'source_url' => $update->source_url,
                    'host' => $host,
                    'country_iso' => $itemIso,
                    'country_name' => $update->country?->name,
                    'owner_iso' => $owner['iso'],
                    'owner_name' => $ownerCountry?->name,
                    'title' => $update->title_english ?: $update->title,
                ]);
            }
        });

    $this->info('Scanned unreviewed rows with source URLs: ' . $scanned);
    $this->info('Wrong-country source-domain rows found: ' . $candidates->count());

    if ($candidates->isEmpty()) {
        return 0;
    }

    $candidates
        ->groupBy('host')
        ->sortByDesc(fn ($rows) => $rows->count())
        ->take(15)
        ->each(function ($rows, string $host): void {
            $first = $rows->first();
            $this->line(sprintf(
                '%s: %d row(s), belongs to %s, examples filed under %s',
                $host,
                $rows->count(),
                trim(($first['owner_name'] ?? 'unknown') . ' (' . ($first['owner_iso'] ?? '?') . ')'),
                $rows->pluck('country_iso')->unique()->take(8)->implode(', ')
            ));
        });

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to mark these rows as rejected.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($candidates->chunk(500) as $chunk) {
        foreach ($chunk as $candidate) {
            $updated += CountryUpdate::query()
                ->whereKey($candidate['id'])
                ->where('review_status', 'unreviewed')
                ->update([
                    'review_status' => 'rejected',
                    'rejection_reason_code' => 'wrong_country_source_domain',
                    'rejection_reason' => 'Rejected by cleanup: source domain ' . $candidate['host']
                        . ' belongs to ' . ($candidate['owner_name'] ?? $candidate['owner_iso'])
                        . ', not ' . ($candidate['country_name'] ?? $candidate['country_iso']) . '.',
                    'rejected_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    $this->info('Rejected wrong-country duplicate rows: ' . $updated);

    return 0;
})->purpose('Reject unreviewed items filed under the wrong country because their source domain belongs to another country');

Artisan::command('sls:cleanup-stale-news-items {--days= : Override the configured non-tender news publication-age limit} {--apply : Mark stale non-tender news items as rejected}', function () {
    $settingValue = null;

    try {
        if (Schema::hasTable('crawler_settings')) {
            $settingValue = DB::table('crawler_settings')
                ->where('setting_key', 'news_recent_publication_days')
                ->value('setting_value');
        }
    } catch (Throwable) {
        $settingValue = null;
    }

    $maxAgeDays = (int) ($this->option('days') ?: $settingValue ?: config('country_intelligence.news_recent_publication_days', 90));

    if ($maxAgeDays <= 0) {
        $this->warn('Stale-news cleanup is disabled because the age limit is 0.');

        return 0;
    }

    $cutoff = now()->subDays($maxAgeDays)->startOfDay();
    $hasTenderSignal = function (string $text): bool {
        return Str::contains(Str::lower($text), [
            'tender',
            'procurement',
            'rfp',
            'request for proposal',
            'request for expression of interest',
            'expression of interest',
            'request for bids',
            'invitation for bids',
            'bid',
            'proposal',
            'contract',
            'consulting services',
            'goods',
            'works',
            'world bank procurement',
        ]);
    };

    $candidates = collect();
    $scanned = 0;

    CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', 'unreviewed')
        ->whereNotNull('publication_date')
        ->whereDate('publication_date', '<', $cutoff->toDateString())
        ->orderBy('publication_date')
        ->orderBy('id')
        ->chunkById(500, function ($updates) use (&$candidates, &$scanned, $hasTenderSignal): void {
            foreach ($updates as $update) {
                $scanned++;
                $text = implode(' ', [
                    $update->title,
                    $update->title_english,
                    $update->summary,
                    $update->summary_english,
                    $update->source_name,
                    $update->source_url,
                ]);

                if ($hasTenderSignal($text)) {
                    continue;
                }

                $candidates->push([
                    'id' => $update->id,
                    'publication_date' => optional($update->publication_date)->toDateString(),
                    'country' => trim(($update->country?->name ?? 'Unknown') . ' (' . ($update->country?->iso_code ?? '?') . ')'),
                    'source_name' => $update->source_name,
                    'title' => $update->title_english ?: $update->title,
                ]);
            }
        });

    $this->info('Publication cutoff: before ' . $cutoff->toDateString() . ' (' . $maxAgeDays . ' day limit)');
    $this->info('Scanned old unreviewed rows: ' . $scanned);
    $this->info('Stale non-tender news rows found: ' . $candidates->count());

    $candidates
        ->sortByDesc('publication_date')
        ->take(20)
        ->each(function (array $candidate): void {
            $this->line(sprintf(
                '#%d | %s | %s | %s | %s',
                $candidate['id'],
                $candidate['publication_date'] ?? 'no date',
                $candidate['country'],
                $candidate['source_name'],
                Str::limit((string) $candidate['title'], 120)
            ));
        });

    if ($candidates->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to reject stale non-tender news items.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($candidates->chunk(500) as $chunk) {
        $updated += CountryUpdate::query()
            ->whereIn('id', $chunk->pluck('id')->all())
            ->where('review_status', 'unreviewed')
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'stale_news_publication_date',
                'rejection_reason' => 'Rejected by cleanup: non-tender news item older than the configured publication freshness window of ' . $maxAgeDays . ' days.',
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
    }

    $this->info('Rejected stale non-tender news rows: ' . $updated);

    return 0;
})->purpose('Reject old unreviewed non-tender news items based on the configured publication-date freshness window');

Artisan::command('sls:cleanup-bad-aggregator-titles {--all-statuses : Include approved/processed active rows, not only unreviewed} {--apply : Mark bad aggregator-title rows as rejected}', function () {
    $looksBad = function (?string $value): bool {
        $title = trim((string) $value);

        if ($title === '') {
            return false;
        }

        if (filter_var($title, FILTER_VALIDATE_URL)) {
            return true;
        }

        $compact = preg_replace('/\s+/', '', $title) ?? $title;

        return strlen($compact) >= 40
            && $compact === $title
            && preg_match('/^[A-Za-z0-9_-]+$/', $compact) === 1;
    };

    $candidates = collect();
    $scanned = 0;

    CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->when(! $this->option('all-statuses'), fn ($query) => $query->where('review_status', 'unreviewed'))
        ->when($this->option('all-statuses'), fn ($query) => $query->where('review_status', '!=', 'rejected'))
        ->where(function ($query) {
            $query->where('source_name', 'like', '%Google News RSS%')
                ->orWhere('source_name', 'like', '%Bing News RSS%')
                ->orWhere('source_url', 'like', '%news.google.%')
                ->orWhere('source_url', 'like', '%bing.com/news%');
        })
        ->orderBy('id')
        ->chunkById(500, function ($updates) use (&$candidates, &$scanned, $looksBad): void {
            foreach ($updates as $update) {
                $scanned++;

                if (! $looksBad($update->title) && ! $looksBad($update->title_english) && ! $looksBad($update->title_original)) {
                    continue;
                }

                $candidates->push([
                    'id' => $update->id,
                    'country' => trim(($update->country?->name ?? 'Unknown') . ' (' . ($update->country?->iso_code ?? '?') . ')'),
                    'publication_date' => optional($update->publication_date)->toDateString(),
                    'retrieved_at' => optional($update->retrieved_at)->toDateTimeString(),
                    'source_name' => $update->source_name,
                    'title' => $update->title_english ?: $update->title ?: $update->title_original,
                ]);
            }
        });

    $this->info('Scanned news aggregator rows: ' . $scanned);
    $this->info('Bad aggregator-title rows found: ' . $candidates->count());

    $candidates
        ->take(30)
        ->each(function (array $candidate): void {
            $this->line(sprintf(
                '#%d | %s | published %s | retrieved %s | %s | %s',
                $candidate['id'],
                $candidate['country'],
                $candidate['publication_date'] ?? 'no date',
                $candidate['retrieved_at'] ?? 'no retrieval date',
                $candidate['source_name'],
                Str::limit((string) $candidate['title'], 120)
            ));
        });

    if ($candidates->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to reject bad aggregator-title rows.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($candidates->chunk(500) as $chunk) {
        $updated += CountryUpdate::query()
            ->whereIn('id', $chunk->pluck('id')->all())
            ->where('review_status', '!=', 'rejected')
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'bad_aggregator_title',
                'rejection_reason' => 'Rejected by cleanup: news aggregator returned an opaque token or URL instead of a readable story title.',
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
    }

    $this->info('Rejected bad aggregator-title rows: ' . $updated);

    return 0;
})->purpose('Reject unreviewed Google/Bing news aggregator rows whose title is an opaque token or URL');

Artisan::command('sls:cleanup-static-profile-updates {--apply : Mark active static profile/listing rows as rejected}', function () {
    $candidates = collect();
    $scanned = 0;

    CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', '!=', 'rejected')
        ->whereNotNull('source_url')
        ->orderBy('id')
        ->chunkById(500, function ($updates) use (&$candidates, &$scanned): void {
            foreach ($updates as $update) {
                $scanned++;

                if (! CountryUpdateNoiseRules::isStaticReferenceUrl((string) $update->source_url)) {
                    continue;
                }

                $candidates->push([
                    'id' => $update->id,
                    'country' => trim(($update->country?->name ?? 'Unknown') . ' (' . ($update->country?->iso_code ?? '?') . ')'),
                    'publication_date' => $update->publication_date?->toDateString(),
                    'retrieved_at' => $update->retrieved_at?->toDateTimeString(),
                    'source_name' => $update->source_name,
                    'source_url' => $update->source_url,
                    'title' => $update->title_english ?: $update->title ?: $update->title_original,
                ]);
            }
        });

    $this->info('Active rows with source URLs scanned: ' . $scanned);
    $this->info('Static profile/listing rows found: ' . $candidates->count());

    $candidates
        ->take(40)
        ->each(function (array $candidate): void {
            $this->line(sprintf(
                '#%d | %s | published %s | retrieved %s | %s | %s | %s',
                $candidate['id'],
                $candidate['country'],
                $candidate['publication_date'] ?? 'no date',
                $candidate['retrieved_at'] ?? 'no retrieval date',
                $candidate['source_name'],
                Str::limit((string) $candidate['title'], 80),
                Str::limit((string) $candidate['source_url'], 120)
            ));
        });

    if ($candidates->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to reject static profile/listing rows.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($candidates->chunk(500) as $chunk) {
        $updated += CountryUpdate::query()
            ->whereIn('id', $chunk->pluck('id')->all())
            ->where('review_status', '!=', 'rejected')
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'static_reference_page',
                'rejection_reason' => 'Rejected by cleanup: source URL points to a static country profile/listing page instead of a current story or tender.',
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
    }

    $this->info('Rejected static profile/listing rows: ' . $updated);

    return 0;
})->purpose('Reject active captures that point to static country profile/listing pages');

Artisan::command('sls:cleanup-aggregator-country-mismatches {--apply : Mark active news aggregator rows as rejected when their real title/source text does not mention the assigned country}', function () {
    $countryConfigs = collect(config('country_intelligence.monitored_countries', []))
        ->merge(config('country_intelligence.countries', []));

    $countryNames = function (Country $country) use ($countryConfigs): \Illuminate\Support\Collection {
        $config = $countryConfigs->get((string) $country->iso_code, []);

        return collect([
            $country->name,
            $country->iso_code,
        ])
            ->merge($config['search_names'] ?? [])
            ->merge(config('country_intelligence.localized_country_names.' . $country->iso_code, []))
            ->map(fn ($name) => Str::lower(trim((string) $name)))
            ->filter(fn (string $name) => strlen($name) >= 4)
            ->unique()
            ->values();
    };

    $realItemText = function (CountryUpdate $update): string {
        return Str::lower(implode(' ', array_filter([
            $update->title,
            $update->title_english,
            $update->title_original,
            $update->source_name,
            $update->source_url,
        ])));
    };

    $candidates = collect();
    $scanned = 0;

    CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', '!=', 'rejected')
        ->where(function ($query) {
            $query->where('source_name', 'like', '%Google News%')
                ->orWhere('source_name', 'like', '%Bing News%');
        })
        ->orderBy('id')
        ->chunkById(500, function ($updates) use (&$candidates, &$scanned, $countryNames, $realItemText): void {
            foreach ($updates as $update) {
                $scanned++;

                if (! $update->country) {
                    continue;
                }

                $text = $realItemText($update);
                $names = $countryNames($update->country);

                if ($names->contains(fn (string $name) => Str::contains($text, $name))) {
                    continue;
                }

                $candidates->push([
                    'id' => $update->id,
                    'country' => trim($update->country->name . ' (' . ($update->country->iso_code ?? '?') . ')'),
                    'publication_date' => $update->publication_date?->toDateString(),
                    'retrieved_at' => $update->retrieved_at?->toDateTimeString(),
                    'source_name' => $update->source_name,
                    'source_url' => $update->source_url,
                    'title' => $update->title_english ?: $update->title ?: $update->title_original,
                ]);
            }
        });

    $this->info('Active news aggregator rows scanned: ' . $scanned);
    $this->info('Country-mismatch rows found: ' . $candidates->count());

    $candidates
        ->take(60)
        ->each(function (array $candidate): void {
            $this->line(sprintf(
                '#%d | %s | published %s | retrieved %s | %s | %s | %s',
                $candidate['id'],
                $candidate['country'],
                $candidate['publication_date'] ?? 'no date',
                $candidate['retrieved_at'] ?? 'no retrieval date',
                $candidate['source_name'],
                Str::limit((string) $candidate['title'], 100),
                Str::limit((string) $candidate['source_url'], 110)
            ));
        });

    if ($candidates->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to reject country-mismatch news aggregator rows.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($candidates->chunk(500) as $chunk) {
        $updated += CountryUpdate::query()
            ->whereIn('id', $chunk->pluck('id')->all())
            ->where('review_status', '!=', 'rejected')
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'aggregator_country_mismatch',
                'rejection_reason' => 'Rejected by cleanup: news aggregator row was assigned to a country that is not mentioned in the real title, source name, or source URL.',
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
    }

    $this->info('Rejected country-mismatch news aggregator rows: ' . $updated);

    return 0;
})->purpose('Reject Google/Bing news rows whose real source text does not mention the assigned country');

Artisan::command('sls:cleanup-title-duplicates {--apply : Mark older active duplicate rows as rejected}', function () {
    $updates = CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', '!=', 'rejected')
        ->where(function ($query) {
            $query->whereNotNull('title')
                ->orWhereNotNull('title_english')
                ->orWhereNotNull('title_original');
        })
        ->orderByDesc('retrieved_at')
        ->orderByDesc('id')
        ->get();

    $groups = $updates
        ->mapToGroups(function (CountryUpdate $update) {
            $sourceFingerprint = $update->source_fingerprint
                ?: CountryUpdateDedupeRules::sourceFingerprint((string) $update->source_url);
            $keys = collect();

            if ($sourceFingerprint) {
                $keys->push('source-fingerprint|' . $update->country_id . '|' . $sourceFingerprint);
            }

            return $keys
                ->merge(CountryUpdateDedupeRules::semanticDuplicateKeys($update))
                ->unique()
                ->mapWithKeys(fn (string $key) => [$key => $update])
                ->all();
        })
        ->filter(fn ($rows) => $rows->count() > 1);

    $duplicates = collect();
    $duplicateIds = collect();

    $groups->each(function ($rows) use (&$duplicates, &$duplicateIds): void {
        $keeper = $rows
            ->sortByDesc(fn (CountryUpdate $update) => sprintf(
                '%010d-%010d',
                $update->retrieved_at?->timestamp ?? 0,
                $update->id
            ))
            ->first();

        $rows
            ->where('id', '!=', $keeper->id)
            ->each(function (CountryUpdate $duplicate) use (&$duplicates, &$duplicateIds, $keeper): void {
                if ($duplicateIds->contains($duplicate->id)) {
                    return;
                }

                $duplicateIds->push($duplicate->id);
                $duplicates->push([
                    'id' => $duplicate->id,
                    'keep_id' => $keeper->id,
                    'country' => trim(($duplicate->country?->name ?? 'Unknown') . ' (' . ($duplicate->country?->iso_code ?? '?') . ')'),
                    'publication_date' => $duplicate->publication_date?->toDateString(),
                    'retrieved_at' => $duplicate->retrieved_at?->toDateTimeString(),
                    'source_name' => $duplicate->source_name,
                    'title' => $duplicate->title_english ?: $duplicate->title ?: $duplicate->title_original,
                ]);
            });
    });

    $this->info('Active rows scanned: ' . $updates->count());
    $this->info('Duplicate groups found: ' . $groups->count());
    $this->info('Older duplicate rows found: ' . $duplicates->count());

    $duplicates
        ->take(40)
        ->each(function (array $duplicate): void {
            $this->line(sprintf(
                '#%d duplicate of #%d | %s | published %s | retrieved %s | %s | %s',
                $duplicate['id'],
                $duplicate['keep_id'],
                $duplicate['country'],
                $duplicate['publication_date'] ?? 'no date',
                $duplicate['retrieved_at'] ?? 'no retrieval date',
                $duplicate['source_name'],
                Str::limit((string) $duplicate['title'], 120)
            ));
        });

    if ($duplicates->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to reject older duplicate rows.');

        return 0;
    }

    $now = now();
    $updated = 0;

    foreach ($duplicates->chunk(500) as $chunk) {
        $updated += CountryUpdate::query()
            ->whereIn('id', $chunk->pluck('id')->all())
            ->where('review_status', '!=', 'rejected')
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'duplicate_title_source',
                'rejection_reason' => 'Rejected by cleanup: duplicate source URL/fingerprint or duplicate normalized story title for the same country/date. Newest matching row was kept.',
                'rejected_at' => $now,
                'updated_at' => $now,
            ]);
    }

    $this->info('Rejected older duplicate rows: ' . $updated);

    return 0;
})->purpose('Reject older active duplicates by canonical source URL or normalized story title/date/country');

Artisan::command('sls:repair-news-aggregator-urls {--id= : Repair one country update id} {--limit=100 : Maximum rows to inspect} {--apply : Save repaired URLs and titles}', function () {
    $isGoogleNewsRssArticleUrl = function (string $url): bool {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return Str::contains($host, 'news.google.')
            && Str::contains($path, '/rss/articles/');
    };

    $resolvedAggregatorUrlLooksUseful = function (string $url) use ($isGoogleNewsRssArticleUrl): bool {
        return filter_var($url, FILTER_VALIDATE_URL)
            && ! $isGoogleNewsRssArticleUrl($url);
    };

    $plainText = fn (string $value): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

    $titleLooksBad = function (?string $value): bool {
        $title = trim((string) $value);

        if ($title === '') {
            return false;
        }

        if (filter_var($title, FILTER_VALIDATE_URL)) {
            return true;
        }

        $compact = preg_replace('/\s+/', '', $title) ?? $title;

        return strlen($compact) >= 40
            && $compact === $title
            && preg_match('/^[A-Za-z0-9_-]+$/', $compact) === 1;
    };

    $titleFromHtml = function (string $html) use ($plainText): string {
        if ($html === '' || preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) !== 1) {
            return '';
        }

        return Str::limit($plainText($match[1] ?? ''), 500, '');
    };

    $titleFromUrl = function (string $url) use ($isGoogleNewsRssArticleUrl): string {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $slug = trim((string) preg_replace('/\.[a-z0-9]{2,5}$/i', '', basename($path)));

        if ($slug === '' || $slug === '/' || $isGoogleNewsRssArticleUrl($url)) {
            return '';
        }

        return Str::headline(str_replace(['-', '_'], ' ', $slug));
    };

    $firstPublisherUrlFromHtml = function (string $html) use ($resolvedAggregatorUrlLooksUseful): string {
        if ($html === '' || preg_match_all('/https?:\\\\?\/\\\\?\/[^"\'<>\s]+/i', $html, $matches) !== 1) {
            return '';
        }

        foreach ($matches[0] as $candidate) {
            $candidate = stripslashes(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $candidate = rtrim($candidate, '.,);]');
            $host = Str::lower((string) parse_url($candidate, PHP_URL_HOST));

            if ($resolvedAggregatorUrlLooksUseful($candidate) && ! Str::contains($host, ['google.', 'gstatic.com'])) {
                return $candidate;
            }
        }

        return '';
    };

    $normalizeFingerprintUrl = function (string $url): string {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return Str::lower(rtrim($url, "/ \t\n\r\0\x0B"));
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? 'https'));
        $host = preg_replace('/^www\./', '', Str::lower((string) $parts['host'])) ?: Str::lower((string) $parts['host']);
        $path = rtrim('/' . ltrim((string) ($parts['path'] ?? ''), '/'), '/') ?: '/';
        $queryString = '';

        if (filled($parts['query'] ?? null)) {
            parse_str((string) $parts['query'], $query);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'oc', 'cid'] as $key) {
                unset($query[$key]);
            }
            ksort($query);
            $queryString = http_build_query($query);
        }

        return $scheme . '://' . $host . $path . ($queryString !== '' ? '?' . $queryString : '');
    };

    $fingerprint = fn (string $url): ?string => ($normalized = $normalizeFingerprintUrl($url)) === '' ? null : hash('sha256', $normalized);

    $resolve = function (string $url) use ($firstPublisherUrlFromHtml, $resolvedAggregatorUrlLooksUseful, $titleFromHtml): array {
        $request = Http::timeout(12)
            ->connectTimeout(5)
            ->accept('*/*')
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 1G-SLS intelligence monitor',
            ])
            ->withOptions([
                'allow_redirects' => [
                    'max' => 8,
                    'track_redirects' => true,
                ],
            ]);

        try {
            $response = $request->withoutVerifying()->get($url);
        } catch (\Throwable) {
            return ['url' => '', 'title' => ''];
        }

        $body = (string) $response->body();
        $finalUrl = (string) ($response->handlerStats()['url'] ?? '');

        if (! $resolvedAggregatorUrlLooksUseful($finalUrl)) {
            $finalUrl = $firstPublisherUrlFromHtml($body);
        }

        return [
            'url' => $finalUrl,
            'title' => $titleFromHtml($body),
        ];
    };

    $query = CountryUpdate::query()
        ->where('source_url', 'like', '%news.google.%')
        ->orderByDesc('retrieved_at')
        ->orderByDesc('id');

    if (filled($this->option('id'))) {
        $query->whereKey((int) $this->option('id'));
    } else {
        $query->where('review_status', '!=', 'rejected')
            ->limit(max(1, (int) $this->option('limit')));
    }

    $updates = $query->get();
    $repairs = collect();

    foreach ($updates as $update) {
        if (! $isGoogleNewsRssArticleUrl((string) $update->source_url)) {
            continue;
        }

        $resolved = $resolve((string) $update->source_url);

        if (! $resolvedAggregatorUrlLooksUseful($resolved['url'] ?? '')) {
            continue;
        }

        $newTitle = '';
        if ($titleLooksBad($update->title) || $titleLooksBad($update->title_english) || $titleLooksBad($update->title_original)) {
            $newTitle = trim((string) ($resolved['title'] ?: $titleFromUrl($resolved['url'])));
        }

        $repairs->push([
            'id' => $update->id,
            'old_url' => $update->source_url,
            'new_url' => $resolved['url'],
            'old_title' => $update->title_english ?: $update->title,
            'new_title' => $newTitle,
        ]);
    }

    $this->info('Google News RSS wrapper rows inspected: ' . $updates->count());
    $this->info('Repairable rows found: ' . $repairs->count());

    $repairs->take(30)->each(function (array $repair): void {
        $this->line(sprintf(
            '#%d | %s -> %s | title: %s%s',
            $repair['id'],
            Str::limit($repair['old_url'], 70),
            Str::limit($repair['new_url'], 90),
            Str::limit((string) $repair['old_title'], 60),
            $repair['new_title'] !== '' ? ' -> ' . Str::limit($repair['new_title'], 80) : ''
        ));
    });

    if ($repairs->isEmpty()) {
        return 0;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run only. Re-run with --apply to save repaired source URLs and titles.');

        return 0;
    }

    $updated = 0;
    $now = now();

    foreach ($repairs as $repair) {
        $payload = [
            'source_url' => $repair['new_url'],
            'source_fingerprint' => $fingerprint($repair['new_url']),
            'updated_at' => $now,
        ];

        if ($repair['new_title'] !== '') {
            $payload['title'] = $repair['new_title'];
            $payload['title_english'] = $repair['new_title'];
            $payload['title_original'] = $repair['new_title'];
        }

        $updated += CountryUpdate::query()
            ->whereKey($repair['id'])
            ->update($payload);
    }

    $this->info('Repaired Google News RSS wrapper rows: ' . $updated);

    return 0;
})->purpose('Resolve stored Google News RSS wrapper URLs to final publisher URLs and repair token titles');

Artisan::command('sls:review-desk-audit {--since=2026-06-01 : Count items retrieved on or after this date} {--page-size=100 : Number of default Review Desk rows to inspect}', function () {
    $since = Carbon::parse((string) $this->option('since'))->startOfDay();
    $pageSize = max(25, min(500, (int) $this->option('page-size')));

    $this->info('Review Desk audit since ' . $since->toDateString());

    $allSince = CountryUpdate::query()
        ->whereNotNull('retrieved_at')
        ->where('retrieved_at', '>=', $since);

    $this->line('');
    $this->info('Rows retrieved since cutoff, by review status:');
    (clone $allSince)
        ->selectRaw("review_status, COUNT(*) AS rows_count, MIN(id) AS first_serial, MAX(id) AS last_serial, MIN(retrieved_at) AS oldest_retrieved, MAX(retrieved_at) AS newest_retrieved")
        ->groupBy('review_status')
        ->orderByDesc('rows_count')
        ->get()
        ->each(fn ($row) => $this->line(sprintf(
            '%s: %d row(s), serials %s-%s, retrieved %s to %s',
            $row->review_status,
            $row->rows_count,
            $row->first_serial,
            $row->last_serial,
            $row->oldest_retrieved,
            $row->newest_retrieved
        )));

    $totalSince = (clone $allSince)->count();
    $activeSince = (clone $allSince)->where('review_status', '!=', 'rejected')->count();
    $rejectedSince = (clone $allSince)->where('review_status', 'rejected')->count();

    $this->line('');
    $this->info('Summary:');
    $this->line('Total retrieved since cutoff: ' . $totalSince);
    $this->line('Active in Review Desk by default: ' . $activeSince);
    $this->line('Hidden because rejected/dropped: ' . $rejectedSince);

    $this->line('');
    $this->info('Retrieved month x review status:');
    (clone $allSince)
        ->selectRaw("DATE_FORMAT(retrieved_at, '%Y-%m') AS retrieved_month, review_status, COUNT(*) AS rows_count")
        ->groupByRaw("DATE_FORMAT(retrieved_at, '%Y-%m'), review_status")
        ->orderByDesc('retrieved_month')
        ->orderBy('review_status')
        ->get()
        ->each(fn ($row) => $this->line(sprintf('%s | %s | %d', $row->retrieved_month, $row->review_status, $row->rows_count)));

    $this->line('');
    $this->info('Rejected rows since cutoff, by reason:');
    (clone $allSince)
        ->where('review_status', 'rejected')
        ->selectRaw("COALESCE(rejection_reason_code, 'no_reason') AS reason_code, COUNT(*) AS rows_count")
        ->groupByRaw("COALESCE(rejection_reason_code, 'no_reason')")
        ->orderByDesc('rows_count')
        ->get()
        ->each(fn ($row) => $this->line(sprintf('%s: %d', $row->reason_code, $row->rows_count)));

    $activeRows = CountryUpdate::query()
        ->with('country:id,name,iso_code')
        ->where('review_status', '!=', 'rejected')
        ->orderByDesc('retrieved_at')
        ->orderByDesc('publication_date')
        ->limit(5000)
        ->get()
        ->map(function (CountryUpdate $update) {
            $update->inferred_focus = CountryUpdateClassifier::inferFocus($update);

            return $update;
        })
        ->unique(fn (CountryUpdate $update) => filled($update->source_url) ? Str::lower($update->source_url) : 'update:' . $update->id)
        ->sortBy([
            fn (CountryUpdate $update) => -1 * ($update->retrieved_at?->timestamp ?? 0),
            fn (CountryUpdate $update) => -1 * ($update->publication_date?->timestamp ?? 0),
            fn (CountryUpdate $update) => match (true) {
                CountryUpdateClassifier::isTender($update) && $update->inferred_focus === 'social_security' => 0,
                CountryUpdateClassifier::isTender($update) && $update->inferred_focus === 'hrms_tenders' => 1,
                CountryUpdateClassifier::isTender($update) && $update->inferred_focus === 'erms_tenders' => 2,
                CountryUpdateClassifier::isTender($update) && $update->inferred_focus === 'ebpc_tenders' => 3,
                default => 4,
            },
        ])
        ->values();

    $firstPage = $activeRows->take($pageSize);

    $this->line('');
    $this->info('Default Review Desk first ' . $firstPage->count() . ' rows, by publication month:');
    $firstPage
        ->groupBy(fn (CountryUpdate $update) => $update->publication_date?->format('Y-m') ?: 'no_publication_date')
        ->sortKeysDesc()
        ->each(fn ($rows, string $month) => $this->line($month . ': ' . $rows->count()));

    $this->line('');
    $this->info('Default Review Desk first ' . $firstPage->count() . ' rows, by retrieved month:');
    $firstPage
        ->groupBy(fn (CountryUpdate $update) => $update->retrieved_at?->format('Y-m') ?: 'no_retrieved_date')
        ->sortKeysDesc()
        ->each(fn ($rows, string $month) => $this->line($month . ': ' . $rows->count()));

    $this->line('');
    $this->info('Most recent default Review Desk rows:');
    $firstPage
        ->take(25)
        ->each(function (CountryUpdate $update): void {
            $this->line(sprintf(
                '#%05d | retrieved %s | published %s | %s | %s | %s',
                $update->id,
                $update->retrieved_at?->toDateTimeString() ?: 'not captured',
                $update->publication_date?->toDateString() ?: 'not captured',
                $update->country?->iso_code ?: '?',
                $update->source_name ?: 'unknown source',
                Str::limit((string) ($update->title_english ?: $update->title), 120)
            ));
        });

    $activeRetrievedSince = $activeRows->filter(fn (CountryUpdate $update) => $update->retrieved_at && $update->retrieved_at->greaterThanOrEqualTo($since));

    $this->line('');
    $this->info('Active default Review Desk rows retrieved since cutoff: ' . $activeRetrievedSince->count());
    $activeRetrievedSince
        ->groupBy(fn (CountryUpdate $update) => $update->publication_date?->format('Y-m') ?: 'no_publication_date')
        ->sortKeysDesc()
        ->each(fn ($rows, string $month) => $this->line('published ' . $month . ': ' . $rows->count()));

    return 0;
})->purpose('Explain how many recent retrieved stories exist and why they do or do not appear in the default Review Desk');

Artisan::command('sls:worker-health-check {--focus=sector_tenders : Monitor focus to check} {--minutes=45 : Alert if no completed run in this many minutes}', function () {
    $focus = (string) $this->option('focus');
    $thresholdMinutes = max(5, (int) $this->option('minutes'));
    $alertEmail = trim((string) env('SLS_WORKER_ALERT_EMAIL', ''));
    $latestRun = CountryMonitorRun::query()
        ->with('country')
        ->where('focus', $focus)
        ->where('status', 'completed')
        ->latest('finished_at')
        ->first();

    $latestFinishedAt = $latestRun?->finished_at;
    $isStale = ! $latestFinishedAt || $latestFinishedAt->lt(now()->subMinutes($thresholdMinutes));
    $cacheKey = 'sls-worker-health-alerted-' . $focus;

    if (! $isStale) {
        Cache::forget($cacheKey);
        $this->info('Worker healthy. Latest ' . $focus . ' run finished at ' . $latestFinishedAt?->toDateTimeString() . ' for ' . ($latestRun->country?->name ?? 'unknown country') . '.');

        return;
    }

    $message = '1G-SLS worker health alert' . PHP_EOL . PHP_EOL
        . 'No completed [' . $focus . '] worker run has been recorded within ' . $thresholdMinutes . ' minutes.' . PHP_EOL
        . 'Latest completed run: ' . ($latestFinishedAt?->toDateTimeString() ?? 'none recorded') . PHP_EOL
        . 'Latest country: ' . ($latestRun?->country?->name ?? 'none') . PHP_EOL
        . 'Check the iMac LaunchAgent and the Windows 1G-SLS server at ' . config('app.url') . PHP_EOL;

    if ($alertEmail === '') {
        $this->warn($message);
        $this->warn('Set SLS_WORKER_ALERT_EMAIL and real MAIL_* SMTP settings to send this by email.');

        return;
    }

    if (Cache::has($cacheKey)) {
        $this->warn('Worker is stale, but an alert was already sent recently.');

        return;
    }

    Mail::raw($message, function ($mail) use ($alertEmail, $focus) {
        $mail->to($alertEmail)->subject('1G-SLS worker alert: ' . $focus . ' not running');
    });

    Cache::put($cacheKey, true, now()->addHours(2));
    $this->warn('Worker stale. Alert sent to ' . $alertEmail . '.');
})->purpose('Alert if the iMac/worker has stopped triggering intelligence monitor runs');

$crawlerSetting = function (string $key, mixed $default = null): mixed {
    try {
        if (! Schema::hasTable('crawler_settings')) {
            return $default;
        }

        $value = DB::table('crawler_settings')->where('setting_key', $key)->value('setting_value');

        return filled($value) ? $value : $default;
    } catch (\Throwable) {
        return $default;
    }
};

Schedule::command('sls:backup-local')
    ->name('sls-local-backup-daily')
    ->dailyAt((string) $crawlerSetting('daily_backup_time', env('SLS_DAILY_BACKUP_TIME', '03:00')))
    ->timezone((string) $crawlerSetting('daily_backup_timezone', env('SLS_DAILY_BACKUP_TIMEZONE', 'America/Chicago')))
    ->withoutOverlapping()
    ->onOneServer();

$crawlerTimeList = function (string $key, array $default) use ($crawlerSetting): array {
    return collect(explode(',', (string) $crawlerSetting($key, implode(',', $default))))
        ->map(fn (string $time) => trim($time))
        ->filter(fn (string $time) => preg_match('/^\d{2}:\d{2}$/', $time) === 1)
        ->values()
        ->all();
};

$dailySlots = $crawlerTimeList('social_security_daily_slots', config('country_intelligence.daily_slots', ['06:15']));
$scheduledMaxResults = (int) $crawlerSetting('scheduled_max_results', config('country_intelligence.scheduled_max_results', config('country_intelligence.default_max_results')));

$scheduleCountryMonitor = function (string $runTime, array $options, string $name): void {
    Schedule::call(function () use ($options) {
        app(CountryIntelligenceMonitor::class)->run(
            countryKeys: [],
            maxResults: (int) $options['max'],
            dryRun: false,
            cycleSize: $options['cycle'] ?? null,
            cycleSlot: $options['slot'] ?? null,
            slotsPerDay: $options['slots_per_day'] ?? null,
            focus: $options['focus'] ?? 'social_security',
            region: $options['region'] ?? null,
        );
    })
        ->name($name)
        ->dailyAt($runTime)
        ->withoutOverlapping()
        ->onOneServer();
};

foreach ($dailySlots as $slotIndex => $runTime) {
    $scheduleCountryMonitor($runTime, [
        'cycle' => (int) $crawlerSetting('daily_batch_size', config('country_intelligence.daily_batch_size')),
        'region' => (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
        'slot' => $slotIndex,
        'slots_per_day' => count($dailySlots),
        'max' => $scheduledMaxResults,
        'focus' => 'social_security',
    ], 'sls-country-news-' . $slotIndex);
}

Schedule::call(function () {
    app(SocialProtectionProfileMonitor::class)->run();
})
    ->name('sls-social-protection-profile-weekly')
    ->weeklyOn((int) $crawlerSetting('social_protection_profile_weekly_day', 1), (string) $crawlerSetting('social_protection_profile_weekly_time', config('country_intelligence.social_protection_profile_weekly_time', '04:10')))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () {
    $crawlerSetting = function (string $key, mixed $default = null): mixed {
        try {
            if (! Schema::hasTable('crawler_settings')) {
                return $default;
            }

            $value = DB::table('crawler_settings')->where('setting_key', $key)->value('setting_value');

            return filled($value) ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    };

    app(IloSocialProtectionProjectDiscoveryService::class)->discover(
        region: (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
        countryLimit: (int) $crawlerSetting('ilo_social_protection_project_weekly_country_limit', config('country_intelligence.ilo_social_protection_project_weekly_country_limit', 25)),
        queriesPerCountry: (int) $crawlerSetting('ilo_social_protection_project_queries_per_country', config('country_intelligence.ilo_social_protection_project_queries_per_country', 3)),
        resultsPerQuery: (int) $crawlerSetting('ilo_social_protection_project_results_per_query', config('country_intelligence.ilo_social_protection_project_results_per_query', 5)),
    );
})
    ->name('sls-ilo-social-protection-projects-weekly')
    ->weeklyOn((int) $crawlerSetting('ilo_social_protection_project_weekly_day', 2), (string) $crawlerSetting('ilo_social_protection_project_weekly_time', config('country_intelligence.ilo_social_protection_project_weekly_time', '04:35')))
    ->withoutOverlapping()
    ->onOneServer();

$hrmsTenderSlots = $crawlerTimeList('hrms_tender_slots', config('country_intelligence.hrms_tender_slots', []));

foreach ($hrmsTenderSlots as $slotIndex => $runTime) {
    $scheduleCountryMonitor($runTime, [
        'cycle' => 1,
        'region' => (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
        'slot' => $slotIndex,
        'slots_per_day' => count($hrmsTenderSlots),
        'max' => $scheduledMaxResults,
        'focus' => 'hrms_tenders',
    ], 'sls-country-hrms-tenders-' . $slotIndex);
}

foreach (['erms_tenders' => 5, 'ebpc_tenders' => 10] as $focus => $minuteOffset) {
    foreach ($hrmsTenderSlots as $slotIndex => $runTime) {
        $staggeredRunTime = Carbon::createFromFormat('H:i', $runTime)
            ->addMinutes($minuteOffset)
            ->format('H:i');

        $scheduleCountryMonitor($staggeredRunTime, [
            'cycle' => 1,
            'region' => (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
            'slot' => $slotIndex,
            'slots_per_day' => count($hrmsTenderSlots),
            'max' => $scheduledMaxResults,
            'focus' => $focus,
        ], 'sls-country-' . $focus . '-' . $slotIndex);
    }
}

$sectorTenderSlots = $crawlerTimeList('sector_tender_slots', config('country_intelligence.sector_tender_slots', []));

foreach ($sectorTenderSlots as $slotIndex => $runTime) {
    $scheduleCountryMonitor($runTime, [
        'cycle' => 1,
        'region' => (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
        'slot' => $slotIndex,
        'slots_per_day' => count($sectorTenderSlots),
        'max' => $scheduledMaxResults,
        'focus' => 'sector_tenders',
    ], 'sls-country-sector-tenders-' . $slotIndex);
}

$scheduleGlobalTenderSweep = function (string $focus, string $runTime, string $name) use ($crawlerSetting): void {
    Schedule::call(function () use ($focus, $crawlerSetting) {
        app(CountryIntelligenceMonitor::class)->run(
            maxResults: (int) $crawlerSetting('global_tender_sweep_max_results', 80),
            dryRun: false,
            focus: $focus,
            region: (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
        );
    })
        ->name($name)
        ->dailyAt($runTime)
        ->withoutOverlapping()
        ->onOneServer();
};

$scheduleGlobalTenderSweep('hrms_tenders', (string) $crawlerSetting('global_hrms_tender_sweep_time', env('SLS_GLOBAL_HRMS_TENDER_SWEEP_TIME', '02:35')), 'sls-global-hrms-tender-sweep');

$scheduleGlobalTenderSweep('erms_tenders', (string) $crawlerSetting('global_erms_tender_sweep_time', env('SLS_GLOBAL_ERMS_TENDER_SWEEP_TIME', '03:05')), 'sls-global-erms-tender-sweep');

$scheduleGlobalTenderSweep('ebpc_tenders', (string) $crawlerSetting('global_ebpc_tender_sweep_time', env('SLS_GLOBAL_EBPC_TENDER_SWEEP_TIME', '03:15')), 'sls-global-ebpc-tender-sweep');

$scheduleGlobalTenderSweep('social_security', (string) $crawlerSetting('global_social_tender_sweep_time', env('SLS_GLOBAL_SOCIAL_TENDER_SWEEP_TIME', '02:55')), 'sls-global-social-security-tender-sweep');

Schedule::call(function () {
    $crawlerSetting = function (string $key, mixed $default = null): mixed {
        try {
            if (! Schema::hasTable('crawler_settings')) {
                return $default;
            }

            $value = DB::table('crawler_settings')->where('setting_key', $key)->value('setting_value');

            return filled($value) ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    };

    app(CountryIntelligenceMonitor::class)->run(
        maxResults: (int) $crawlerSetting('global_social_news_max_results', 120),
        dryRun: false,
        focus: 'social_security',
        region: (string) $crawlerSetting('scheduled_region_scope', 'africa_asia_caribbean_latin_america_north_america_europe'),
    );
})
    ->name('sls-global-social-security-news-sweep')
    ->dailyAt((string) $crawlerSetting('global_social_news_sweep_time', env('SLS_GLOBAL_SOCIAL_NEWS_SWEEP_TIME', '03:20')))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () use ($crawlerSetting) {
    app(TenderDocumentProcessor::class)->process((int) $crawlerSetting('tender_document_process_limit', env('SLS_TENDER_DOCUMENT_PROCESS_LIMIT', 20)));
})
    ->name('sls-process-tender-documents')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () use ($crawlerSetting) {
    Artisan::call('sls:translate-country-update-titles', [
        '--limit' => (int) $crawlerSetting('title_translation_backfill_limit', env('SLS_TITLE_TRANSLATION_BACKFILL_LIMIT', 100)),
    ]);
})
    ->name('sls-title-translation-backfill')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () use ($crawlerSetting) {
    Artisan::call('sls:clean-crawler-contacts', [
        '--limit' => (int) $crawlerSetting('crawler_contact_clean_limit', env('SLS_CRAWLER_CONTACT_CLEAN_LIMIT', 5000)),
    ]);
})
    ->name('sls-clean-crawler-contacts')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () use ($crawlerSetting) {
    Artisan::call('sls:resolve-crawler-contact-names', [
        '--limit' => (int) $crawlerSetting('crawler_contact_resolve_limit', env('SLS_CRAWLER_CONTACT_RESOLVE_LIMIT', 200)),
    ]);
})
    ->name('sls-resolve-crawler-contact-names')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () use ($crawlerSetting) {
    Artisan::call('sls:worker-health-check', [
        '--focus' => 'sector_tenders',
        '--minutes' => $crawlerSetting('worker_stale_minutes', env('SLS_WORKER_STALE_MINUTES', 45)),
    ]);
})
    ->name('sls-worker-health-check')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
