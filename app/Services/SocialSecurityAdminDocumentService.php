<?php

namespace App\Services;

use App\Models\Country;
use App\Models\IntelligenceSource;
use App\Models\MarketCrawler;
use App\Models\MarketOrganization;
use App\Models\SocialSecurityAdminCandidate;
use App\Models\SocialSecurityAdminCandidateContext;
use App\Models\SourceDocument;
use App\Support\SocialSecurityAdminNameCleaner;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialSecurityAdminDocumentService
{
    public function extract(SourceDocument $document, ?Country $country = null): array
    {
        $country ??= $document->relatedCountry ?: $this->countryFromFilename((string) $document->original_filename);
        $text = $document->chunks()->orderBy('id')->pluck('chunk_text')->implode("\n");
        $rows = $this->extractAdministrativeOccurrences($text);
        $created = 0;
        $updated = 0;
        $contextsCreated = 0;
        $contextsUpdated = 0;

        foreach ($rows as $row) {
            $row['organization_name'] = SocialSecurityAdminNameCleaner::cleanCountrySuffix(
                $row['organization_name'],
                $country?->name,
                $country?->iso_code,
            );

            if ($row['organization_name'] === '') {
                continue;
            }

            $candidate = SocialSecurityAdminCandidate::updateOrCreate(
                [
                    'source_document_id' => $document->id,
                    'country_iso' => $country?->iso_code,
                    'organization_name' => $row['organization_name'],
                ],
                [
                    'country_id' => $country?->id,
                    'country_name' => $country?->name,
                    'role_in_programme' => $row['role_in_programme'],
                    'related_programmes' => $row['related_programmes'],
                    'evidence_excerpt' => $row['evidence_excerpt'],
                    'confidence_score' => $row['confidence_score'],
                    'status' => 'staged',
                ],
            );

            $candidate->wasRecentlyCreated ? $created++ : $updated++;

            $context = SocialSecurityAdminCandidateContext::updateOrCreate(
                [
                    'social_security_admin_candidate_id' => $candidate->id,
                    'context_key' => $this->contextKey($row),
                ],
                array_merge([
                    'role_in_programme' => $row['role_in_programme'],
                    'related_programmes' => $row['related_programmes'],
                    'evidence_excerpt' => $row['evidence_excerpt'],
                    'confidence_score' => $row['confidence_score'],
                ], $this->classifyContext($row)),
            );

            $context->wasRecentlyCreated ? $contextsCreated++ : $contextsUpdated++;
            $this->refreshCandidateSummary($candidate);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'found' => count($rows),
            'contexts_created' => $contextsCreated,
            'contexts_updated' => $contextsUpdated,
            'country' => $country,
        ];
    }

    public function approve(SocialSecurityAdminCandidate $candidate): MarketOrganization
    {
        $crawler = MarketCrawler::query()->where('crawler_key', 'social_security_organization')->first();
        $country = $candidate->country ?: Country::query()->where('iso_code', $candidate->country_iso)->first();
        $name = trim($candidate->organization_name);
        $fingerprint = hash('sha256', Str::lower('social_security_admin_candidate|' . ($candidate->country_iso ?: '') . '|' . $name));

        $organization = MarketOrganization::updateOrCreate(
            ['source_fingerprint' => $fingerprint],
            [
                'market_crawler_id' => $crawler?->id,
                'name' => Str::limit($name, 255, ''),
                'name_normalized' => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString(),
                'organization_type' => 'government_agency',
                'industry' => 'government',
                'organization_subcategory' => 'social_security_administration',
                'country' => $country?->name ?: $candidate->country_name,
                'country_raw' => $candidate->country_name,
                'country_iso' => $candidate->country_iso,
                'country_resolution_status' => $candidate->country_iso ? 'resolved' : 'unresolved',
                'region' => $country?->region,
                'status' => 'active',
                'lead_status' => 'researching',
                'lead_source' => 'social_security_system_document',
                'notes' => trim(implode("\n", array_filter([
                    'Extracted from social security system document #' . $candidate->source_document_id . '.',
                    $candidate->role_in_programme ? 'Role: ' . $candidate->role_in_programme : null,
                    $candidate->related_programmes ? 'Related programmes: ' . $candidate->related_programmes : null,
                ]))),
                'last_crawler_name' => $crawler?->name,
            ],
        );

        if ($country && blank($country->social_security_administration_name)) {
            $country->update(['social_security_administration_name' => $name]);
        }

        IntelligenceSource::updateOrCreate(
            [
                'country_iso' => $candidate->country_iso,
                'name' => $name,
                'source_class' => 'social_security_admin',
            ],
            [
                'region' => $country?->region,
                'focus' => 'social_security',
                'access_method' => 'document_identified_pending_url',
                'connector' => 'social_security_admin_crawler',
                'is_enabled' => true,
            ],
        );

        $candidate->update([
            'status' => 'approved',
            'market_organization_id' => $organization->id,
        ]);

        return $organization;
    }

    /**
     * @param array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int} $row
     * @return array<string,mixed>
     */
    private function classifyContext(array $row): array
    {
        $roleText = $row['role_in_programme'] ?? '';
        $programmeText = $row['related_programmes'] ?? '';
        $allText = $this->clean(implode(' ', [
            $row['organization_name'] ?? '',
            $roleText,
            $programmeText,
            $row['evidence_excerpt'] ?? '',
        ]));

        $roles = $this->matchBuckets($roleText, [
            'General Supervision & Regulation' => '/General supervision|regulation/i',
            'Programme Administration & Delivery' => '/Programme administration|Program administration|administration and delivery|delivery/i',
            'Contribution Collection' => '/Collection of contributions/i',
            'Benefit Payment / Delivery' => '/Payment\/delivery|Payment/i',
            'Policy Development / Coordination' => '/policy development|policy coordination|policy guidance|policy formulation/i',
            'Inspection / Enforcement / Appeals' => '/Inspection|Enforcement|Appeals|Adjudication|Assessment|Licensing/i',
            'Investment / Fund Management' => '/Investment|fund management|Management Companies|Management Authority/i',
        ]);

        $programmeL1 = $this->matchBuckets($programmeText, [
            'Social Insurance' => '/Social insurance|Pension insurance|Pension and invalidity|Pension and disability|old.?age|survivor|provident fund|mandatory individual account|occupational pension|NDC programme/i',
            'Health Insurance / Medical Benefits' => '/Health insurance|Health care|Medical benefits|medical benefits|Compulsory Health|Social Health|Mandatory insurance for medical/i',
            'Unemployment' => '/Unemployment|Employment insurance|Employment.related/i',
            'Work Injury / Occupational Disease' => '/Accidents at work|occupational diseases|Work injury|Accident insurance|Industrial accident|work.related injuries/i',
            'Sickness & Maternity' => '/Sickness|Maternity|Cash sickness|Cash maternity/i',
            'Family / Household Benefits' => '/Family and household|family benefits|child protection|family allowance/i',
            'Social Assistance / Universal Benefits' => '/Social assistance|Universal|Social pension|Social aid|welfare|Aswesuma/i',
            'Long-Term Care' => '/long.?term care/i',
            'Employer Liability' => '/Employer liability|Employer.liability/i',
            'Private Pensions' => '/private pension|private pensions|voluntary individual account/i',
            'Private Health Insurance' => '/private health/i',
        ]);

        $programmeL2 = $this->matchBuckets($programmeText, [
            'Old age / invalidity / survivors' => '/old.?age|invalidity|survivor|Pension insurance|Pension and invalidity|Pension and disability/i',
            'Provident fund' => '/Provident fund/i',
            'Mandatory individual account' => '/mandatory individual account|individual account/i',
            'Mandatory occupational pension' => '/occupational pension/i',
            'Cash sickness benefits' => '/Cash sickness|Sickness/i',
            'Cash maternity benefits' => '/Cash maternity|Maternity/i',
            'Medical benefits' => '/Medical benefits|medical benefits|Health care/i',
            'Health insurance' => '/Health insurance|Compulsory Health|Mandatory insurance for medical/i',
            'Unemployment insurance' => '/Unemployment insurance|Employment insurance/i',
            'Accidents at work' => '/Accidents at work|Work injury|work.related injuries/i',
            'Occupational diseases' => '/occupational diseases/i',
            'Family allowances' => '/Family and household|family benefits|child protection|family allowance/i',
            'Social pension' => '/Social pension/i',
            'Social assistance' => '/Social assistance|Social aid|welfare|Aswesuma/i',
            'Long-term care benefits' => '/long.?term care/i',
            'Employer liability' => '/Employer liability|Employer.liability/i',
        ]);

        $specialSystemMentioned = (bool) preg_match('/special system|special scheme|special systems|separate system/i', $allText);
        $specialEmployerTypes = $this->matchBuckets($allText, [
            'Civil Service' => '/civil servants|public servants|public sector|government employees/i',
            'Military' => '/military|armed forces|defence|defense/i',
            'Police' => '/police/i',
        ]);

        $employerTypes = ['All / General Workforce'];

        if ($specialSystemMentioned && $specialEmployerTypes === []) {
            $specialEmployerTypes = ['Unknown Special System'];
        }

        $notes = [];

        if ($specialSystemMentioned) {
            $notes[] = 'Special system mentioned; responsible organization is not identified in this document and needs enrichment.';
        }

        return [
            'normalized_roles' => $roles,
            'programme_l1' => $programmeL1,
            'programme_l2' => $programmeL2,
            'employer_types' => $employerTypes,
            'special_system_mentioned' => $specialSystemMentioned,
            'special_system_employer_types' => $specialEmployerTypes,
            'needs_enrichment' => $specialSystemMentioned,
            'classification_notes' => implode(' ', $notes),
        ];
    }

    /**
     * @param array<string,string> $patterns
     * @return array<int,string>
     */
    private function matchBuckets(string $text, array $patterns): array
    {
        $matches = [];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $matches[] = $label;
            }
        }

        return array_values(array_unique($matches));
    }

    public function countryFromFilename(string $filename): ?Country
    {
        $stem = Str::of(pathinfo($filename, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\b(social|security|system|profile|overview|country|report|pension|pdf|docx?)\b/i', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->ascii()
            ->lower()
            ->replaceMatches('/\b\d+\b/', ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        if ($stem === '') {
            return null;
        }

        $aliases = $this->countryAliases();
        $lookupName = $aliases[$stem] ?? $stem;

        return Country::query()
            ->get()
            ->first(function (Country $country) use ($stem, $lookupName) {
                $name = Str::of($country->name)
                    ->ascii()
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', ' ')
                    ->replaceMatches('/\s+/', ' ')
                    ->trim()
                    ->toString();
                $iso = Str::lower((string) $country->iso_code);

                return $lookupName === $name
                    || $stem === $name
                    || $stem === $iso
                    || $lookupName === $iso;
            });
    }

    /**
     * @return array<string,string>
     */
    private function countryAliases(): array
    {
        return [
            'brunei darussalam' => 'brunei',
            'cote d ivoire' => 'ivory coast',
            'lao people s democratic republic' => 'laos',
            'micronesia' => 'federated states of micronesia',
            'moldova republic of' => 'moldova',
            'republic of korea' => 'south korea',
            'russian federation' => 'russia',
            'sao tome and principe' => 'sao tome and principe',
            'taiwan china' => 'taiwan',
            'turkiye' => 'turkey',
            't rkiye' => 'turkey',
            'united republic of tanzania' => 'tanzania',
            'united states of america' => 'united states',
            'venezuela bolivarian republic of' => 'venezuela',
            'venezuala bolivarian republic of' => 'venezuela',
            'viet nam' => 'vietnam',
        ];
    }

    /**
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function extractAdministrativeRows(string $text): array
    {
        return $this->mergeRepeatedOrganizations($this->extractAdministrativeOccurrences($text));
    }

    /**
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function extractAdministrativeOccurrences(string $text): array
    {
        $text = $this->clean($text);
        $sections = preg_split('/\bAdministrative organization\b/i', $text);

        if (! $sections || count($sections) < 2) {
            return [];
        }

        $rows = [];

        foreach (array_slice($sections, 1) as $section) {
            $rows = array_merge($rows, $this->extractAdministrativeRowsFromSection($section));
        }

        return $this->normalizeAdministrativeRows($rows);
    }

    private function contextKey(array $row): string
    {
        return hash('sha256', Str::lower($this->clean(implode('|', [
            $row['role_in_programme'] ?? '',
            $row['related_programmes'] ?? '',
            $row['evidence_excerpt'] ?? '',
        ]))));
    }

    private function refreshCandidateSummary(SocialSecurityAdminCandidate $candidate): void
    {
        $contexts = $candidate->contexts()->oldest('id')->get();

        if ($contexts->isEmpty()) {
            return;
        }

        $candidate->update([
            'role_in_programme' => Str::limit($this->mergeDistinctTextFromCollection($contexts->pluck('role_in_programme')), 1000, ''),
            'related_programmes' => Str::limit($this->mergeDistinctTextFromCollection($contexts->pluck('related_programmes')), 1000, ''),
            'evidence_excerpt' => Str::limit($contexts->pluck('evidence_excerpt')->filter()->unique()->implode("\n"), 1500, ''),
            'confidence_score' => (int) $contexts->max('confidence_score'),
        ]);
    }

    private function mergeDistinctTextFromCollection(Collection $values, string $separator = '; '): string
    {
        return $values
            ->flatMap(fn (?string $value) => explode($separator, (string) $value))
            ->map(fn (string $part) => $this->clean($part))
            ->filter()
            ->unique(fn (string $part) => Str::lower($part))
            ->values()
            ->implode($separator);
    }

    /**
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function extractAdministrativeRowsFromSection(string $section): array
    {
        $section = preg_split('/\b(?:ISSA Country profiles|Branch overview|Financing|Coverage|Qualifying conditions|Source of funds|Regulatory framework|Services covered|Details on how|Benefit duration|Benefit supplements|Annex:|Dependent children|Other dependents|Combined maximum)\b/i', $section, 2)[0] ?? $section;
        $section = preg_replace('/\bOrganization\b\s+\bRole in relation to programme\b\s+\bRelated programmes\b/i', ' ', $section);
        $section = preg_replace('/\bRole in relation to programme\b|\bRelated programme\(s\)\b|\bRelated programmes\b|\bAdditional information\b/i', ' ', $section);

        preg_match_all(
            '/\b(?:General\s+(?:supervision|regulation|administration)|Regulatory\s+functions?|Programme\s+(?:administration|delivery)|Program\s+(?:administration|delivery)|Collection\s+of\s+contributions|Payment\/delivery\s+of\s+benefits|Policy\s+(?:development|coordination|guidance|formulation)|Licensing|Inspection|Appeals|Adjudication|Assessment)\b/i',
            $section,
            $roleStarts,
            PREG_OFFSET_CAPTURE,
        );

        $rows = [];
        $pendingOrganization = null;
        $cursor = 0;
        $roles = $roleStarts[0] ?? [];

        for ($index = 0; $index < count($roles);) {
            $roleStart = $roles[$index];
            $roleOffset = $roleStart[1];
            $organization = $pendingOrganization ?: substr($section, $cursor, $roleOffset - $cursor);
            $sentenceEnd = strpos($section, '.', $roleOffset);

            if ($sentenceEnd === false) {
                $index++;
                continue;
            }

            $role = substr($section, $roleOffset, $sentenceEnd - $roleOffset + 1);
            $nextIndex = $index + 1;

            while (isset($roles[$nextIndex]) && $roles[$nextIndex][1] <= $sentenceEnd) {
                $nextIndex++;
            }

            $nextRoleOffset = $roles[$nextIndex][1] ?? strlen($section);
            $between = substr($section, $sentenceEnd + 1, $nextRoleOffset - $sentenceEnd - 1);
            [$programmes, $nextOrganization] = $this->splitProgrammesFromNextOrganization($between, $nextIndex < count($roles));

            $organization = $this->cleanOrganizationName($organization);
            $role = $this->clean($role);
            $programmes = $this->cleanRelatedProgrammes($programmes);

            if ($this->looksLikeAdministrativeOrganization($organization)) {
                $rows[] = [
                    'organization_name' => Str::limit($organization, 255, ''),
                    'role_in_programme' => Str::limit($role, 1000, ''),
                    'related_programmes' => Str::limit($programmes, 1000, ''),
                    'evidence_excerpt' => Str::limit(trim($organization . ' | ' . $role . ' | ' . $programmes), 1500, ''),
                    'confidence_score' => $programmes !== '' ? 88 : 72,
                ];
            }

            $pendingOrganization = $nextOrganization;
            $cursor = $nextRoleOffset;
            $index = $nextIndex;
        }

        return $rows;
    }

    /**
     * Recovery pass for tables where the previous row's programme text appears directly before
     * the next organization name, especially with a fourth "Additional information" column.
     *
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function extractAdministrativeRowsByDirectPattern(string $section): array
    {
        $programmePattern = '(?:Social insurance|Social assistance|Universal|Employer liability|Health insurance|Medical benefits|Provident fund|Unemployment insurance|Maternity|Sickness|Accidents at work and occupational diseases|Work injury)';
        $rolePattern = '(?:Regulatory functions?|General supervision(?: and regulation)?|Programme administration(?: and delivery)?(?:;\s*Collection of contributions)?|Program administration(?: and delivery)?(?:;\s*Collection of contributions)?|Collection of contributions|Payment\/delivery of benefits)';

        preg_match_all(
            '/' . $programmePattern . '(?:\s*,\s*' . $programmePattern . ')*\s+(?<organization>[A-Z][A-Za-z&.,\'()\/-]+(?:\s+(?!' . $rolePattern . '\b)[A-Za-z&.,\'()\/-]+){0,10})\s+(?<role>' . $rolePattern . ')/i',
            $section,
            $matches,
            PREG_SET_ORDER,
        );

        $rows = [];

        foreach ($matches as $match) {
            $organization = $this->cleanOrganizationName($match['organization'] ?? '');
            $role = $this->clean($match['role'] ?? '');
            $beforeOrganization = Str::before($match[0] ?? '', $match['organization'] ?? '');
            $programmes = $this->cleanRelatedProgrammes($beforeOrganization);

            if (! $this->looksLikeAdministrativeOrganization($organization)) {
                continue;
            }

            $rows[] = [
                'organization_name' => Str::limit($organization, 255, ''),
                'role_in_programme' => Str::limit($role, 1000, ''),
                'related_programmes' => Str::limit($programmes, 1000, ''),
                'evidence_excerpt' => Str::limit(trim($organization . ' | ' . $role . ' | ' . $programmes), 1500, ''),
                'confidence_score' => $programmes !== '' ? 86 : 70,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}> $rows
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function mergeRepeatedOrganizations(array $rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $key = Str::of(Str::ascii($row['organization_name']))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', ' ')
                ->trim()
                ->toString();

            if ($key === '') {
                continue;
            }

            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            $merged[$key]['role_in_programme'] = $this->mergeDistinctText(
                $merged[$key]['role_in_programme'],
                $row['role_in_programme'],
            );
            $merged[$key]['related_programmes'] = $this->mergeDistinctText(
                $merged[$key]['related_programmes'],
                $row['related_programmes'],
            );
            $merged[$key]['evidence_excerpt'] = Str::limit($this->mergeDistinctText(
                $merged[$key]['evidence_excerpt'],
                $row['evidence_excerpt'],
                "\n",
            ), 1500, '');
            $merged[$key]['confidence_score'] = max($merged[$key]['confidence_score'], $row['confidence_score']);
        }

        return array_values($merged);
    }

    /**
     * @param array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}> $rows
     * @return array<int, array{organization_name:string, role_in_programme:string, related_programmes:string, evidence_excerpt:string, confidence_score:int}>
     */
    private function normalizeAdministrativeRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $organization = $this->cleanOrganizationName($row['organization_name'] ?? '');
            $role = $this->clean($row['role_in_programme'] ?? '');
            $programmes = $this->cleanRelatedProgrammes($row['related_programmes'] ?? '');

            if (! $this->looksLikeAdministrativeOrganization($organization)) {
                continue;
            }

            $normalized[] = [
                'organization_name' => Str::limit($organization, 255, ''),
                'role_in_programme' => Str::limit($role, 1000, ''),
                'related_programmes' => Str::limit($programmes, 1000, ''),
                'evidence_excerpt' => Str::limit(trim($organization . ' | ' . $role . ' | ' . $programmes), 1500, ''),
                'confidence_score' => $row['confidence_score'] ?? ($programmes !== '' ? 88 : 72),
            ];
        }

        return $normalized;
    }

    private function mergeDistinctText(?string $existing, ?string $incoming, string $separator = '; '): string
    {
        $parts = collect(explode($separator, (string) $existing))
            ->merge(explode($separator, (string) $incoming))
            ->map(fn (string $part) => $this->clean($part))
            ->filter()
            ->unique(fn (string $part) => Str::lower($part))
            ->values();

        return $parts->implode($separator);
    }

    /**
     * @return array{0:string,1:?string}
     */
    private function splitProgrammesFromNextOrganization(string $value, bool $hasNextRole): array
    {
        $value = $this->clean($value);

        if (! $hasNextRole || $value === '') {
            return [$value, null];
        }

        preg_match_all(
            '/\b(?:[A-Z][A-Za-z&.,\'()\/-]+(?:\s+(?:of|for|and|de|du|des|del|da|do|dos|das|la|le|les|el|y|e|&|[A-Z][A-Za-z&.,\'()\/-]+)){0,10}\s+(?:Board|Fund|Funds|Agency|Authority|Administration|Commission|Ministry|Department|Office|Organization|Organizations|Company|Companies|Entity|Entities|Centre|Centres|Center|Centers|Committee|Commissioners|Service|Services|Institute|Institution|Directorate|Corporation|Council|Bureau|Secretariat|Social Security|Insurance System)|(?:Services Australia|Payment Denmark|Health New Zealand|WorkSafe[A-Z]+|WorkplaceNL|Colpensiones|Pakistan Bait-ul-Mal)|(?:Ministry|Department|Caisse|Caja|Instituto|Institut|National|Social Security|Pension|Provident|Insurance)\s+(?:of|for|and|de|du|des|del|da|do|dos|das|la|le|les|el|y|e|&|[A-Z][A-Za-z&.,\'()\/-]+)(?:\s+(?:of|for|and|de|du|des|del|da|do|dos|das|la|le|les|el|y|e|&|[A-Z][A-Za-z&.,\'()\/-]+)){0,10})$/',
            $value,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $candidates = $matches[0] ?? [];
        $candidate = end($candidates);

        if (! $candidate) {
            return [$value, null];
        }

        $offset = $candidate[1];
        $programmes = trim(substr($value, 0, $offset));
        $organization = $this->cleanOrganizationName(substr($value, $offset));

        if (! $this->looksLikeAdministrativeOrganization($organization)) {
            return [$value, null];
        }

        return [$programmes, $organization];
    }

    private function cleanRelatedProgrammes(string $value): string
    {
        $value = $this->clean($value);
        $known = $this->knownProgrammesFromText($value);

        if ($known !== '') {
            return $known;
        }

        if (preg_match('/^\s*Note:/i', $value)) {
            return '';
        }

        $value = preg_replace('/\b(?:Regulatory|Administrative|Administration|Programme|Program|Delivery|Collection|Payment|Management|Policy|Supervision)\s+functions?\b.*$/i', '', $value ?? '');
        $value = preg_split('/(?:^|\s)(?:Note:|Governed by|Services covered|Details on how|Benefit duration|Benefit supplements|Annex:|ISSA Country profiles|Branch overview|Type of programme|Coverage|Regulatory framework)\b/i', $value, 2)[0] ?? $value;
        $value = preg_replace('/\bAdditional information\b/i', ' ', $value ?? '');

        return trim($this->clean($value), " \t\n\r\0\x0B.,;:");
    }

    private function knownProgrammesFromText(string $value): string
    {
        preg_match_all(
            '/\b(?:Pension insurance|Social pension|Social insurance|Social assistance|Unemployment insurance|Compulsory Healthcare Insurance Fund|Compulsory Health Insurance Fund|Cash sickness benefits(?:\s*\(social insurance\))?|Cash maternity benefits(?:\s*\(social insurance\))?|Sickness|Maternity|Medical benefits|Health insurance|Long-term care benefits|Work injury|Accidents at work and occupational diseases|Family and household benefits|Universal|Provident fund|Employer liability)\b/i',
            $value,
            $matches,
        );

        return collect($matches[0] ?? [])
            ->map(fn (string $match) => $this->clean($match))
            ->unique(fn (string $match) => Str::lower($match))
            ->values()
            ->implode(', ');
    }

    private function looksLikeAdministrativeOrganization(string $value): bool
    {
        $value = $this->cleanOrganizationName($value);

        if ($value === '' || Str::length($value) < 3) {
            return false;
        }

        if ($this->isOrganizationFragment($value)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:Ministry|Department|Caisse|Caja|Instituto|Institut|National|Social Security|Pension|Provident|Insurance|Board|Fund|Agency|Authority|Administration|Commission|Office|Organization|Organizations|Company|Companies|Entity|Entities|Centre|Centres|Center|Centers|Committee|Commissioners|Service|Services|Institute|Institution|Directorate|Corporation|Council|Bureau|Secretariat|Court|Services Australia|Payment Denmark|Health New Zealand|WorkSafe|WorkplaceNL|Colpensiones|Bait-ul-Mal)\b/i',
            $value,
        );
    }

    private function cleanOrganizationName(string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('/^\s*(?:related\s+)?programmes?\s*(?:\(\s*s\s*\))?\s+/i', '', $value ?? '');
        $value = preg_replace('/^\s*(?:Organization|Role in relation to programme|Related programme\(s\)|Related programmes|Additional information)\s+/i', '', $value ?? '');
        $value = preg_replace('/^(?:Social insurance|Social assistance|Universal|Employer liability|Health insurance|Medical benefits|Provident fund|Unemployment insurance|Maternity|Sickness)\s+(?!(?:Organization|Board|Fund|Funds|Office|Agency|Authority|Institute|Institution|System)\b)(.+)$/i', '$1', $value ?? '');
        $value = preg_replace('/\b(?:Regulatory|Administrative|Administration|Programme|Program|Delivery|Collection|Payment|Management|Policy|Supervision)\s+functions?\b.*$/i', '', $value ?? '');
        $value = preg_replace('/\bNote:\s*.*$/i', '', $value ?? '');
        $value = preg_replace('/\.\s*(?:Employers?|Employees?|Beneficiaries|Self-employed persons?|Civil servants?)\b.*$/i', '', $value ?? '');
        $value = preg_replace('/\b(Scheme|Fund|Programme|Program)\s+Employers?\b\.?\s*$/i', '$1', $value ?? '');
        $parts = preg_split('/\.\s+/', $value);

        if (is_array($parts) && count($parts) > 1) {
            $last = $this->clean(end($parts) ?: '');

            if ($this->looksLikeAdministrativeOrganizationName($last)) {
                $value = $last;
            }
        }

        if (preg_match('/^\s*[\p{Lu}][\p{L}\s&.-]{1,40}\)\s+(.+)$/u', $value ?? '', $matches)) {
            $candidate = $this->clean($matches[1] ?? '');

            if ($this->looksLikeAdministrativeOrganizationName($candidate)) {
                $value = $candidate;
            }
        }

        $value = preg_replace(
            '/^Compulsory Healthcare Insurance Fund\s+(Compulsory Health Insurance Fund)$/i',
            '$1',
            $value ?? '',
        );
        $value = $this->repairKnownOrganizationFragment($value ?? '');
        $value = preg_replace('/^(?:Organization|Role in relation to programme|Related programme\(s\)|Related programmes|programme\s*\(s\)|programmes?|Additional information)\s+/i', '', $value ?? '');
        $value = preg_replace('/^(?:Social insurance|Social assistance|Universal|Employer liability|Health insurance|Medical benefits|Provident fund|Unemployment insurance|Maternity|Sickness)\s+(?!(?:Organization|Board|Fund|Funds|Office|Agency|Authority|Institute|Institution|System)\b)(.+)$/i', '$1', $value ?? '');
        $value = preg_replace('/\s+\b(?:Regulatory|Administrative|Administration|Programme|Program|Delivery|Collection|Payment|Management|Policy|Supervision)\s+functions?\b[.;:]?\s*$/i', '', $value ?? '');
        $value = preg_replace('/\.\s*(?:Employers?|Employees?|Beneficiaries|Self-employed persons?|Civil servants?)\b.*$/i', '', $value ?? '');
        $value = preg_replace('/\b(Scheme|Fund|Programme|Program)\s+Employers?\b\.?\s*$/i', '$1', $value ?? '');
        $value = preg_replace('/^(?:and|or|the)\s+/i', '', $value ?? '');
        $value = $this->repairKnownOrganizationFragment($value ?? '');

        return trim($value ?? " \t\n\r\0\x0B.,;:");
    }

    private function repairKnownOrganizationFragment(string $value): string
    {
        $value = $this->clean($value);
        $repairs = [
            'Department' => 'Health Insurance Department',
            'Department of Revenue' => 'Quebec Department of Revenue',
            'Insurance Ltd., BIL' => 'Bhutan Insurance Ltd., BIL',
            'Insurance Bhutan Insurance Ltd., BIL' => 'Bhutan Insurance Ltd., BIL',
            'Insurance Plan' => 'Quebec Parental Insurance Plan',
            'Insurance Plan. Employment and Social Development Canada' => 'Employment and Social Development Canada',
            'Corporation' => "Employees' State Insurance (ESI) Corporation",
            'General Authority' => 'Social Insurance General Authority',
            'Review and Assessment Service' => 'Health Insurance Review and Assessment Service',
            'Assistant Commissioners' => 'The Commissioner for Labour and his appointed Assistant Commissioners',
            'Commissioner for Labour and his appointed Assistant Commissioners' => 'The Commissioner for Labour and his appointed Assistant Commissioners',
            'Companies' => 'Health Insurance Companies',
            'Insurance Review and' => 'Health Insurance Review and Assessment Service',
            'Rural Life Insurance National Pension and Provident Fund' => 'National Pension and Provident Fund',
            'Rural Life Insurance Royal Civil Service Commission' => 'Royal Civil Service Commission',
            'Ministry of Home Affairs. Supreme Court' => 'Supreme Court',
        ];

        return $repairs[$value] ?? $value;
    }

    private function isOrganizationFragment(string $value): bool
    {
        $value = $this->clean($value);

        return (bool) preg_match('/^(?:and|or|the|programme|program|delivery|collection|administration|fund|funds|institute|institution|insurance|service|services|office|agency|authority|board|commission|committee|centre|center|department|corporation|companies|general authority|assistant commissioners|insurance plan|insurance review and)$/i', $value);
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\t"], "\n", $value);
        $value = preg_replace('/[ ]+/', ' ', $value ?? '');
        $value = preg_replace('/\n+/', "\n", $value ?? '');
        $value = preg_replace('/\s+/', ' ', $value ?? '');

        return trim($value ?? '');
    }

    private function looksLikeAdministrativeOrganizationName(string $value): bool
    {
        if ($value === '' || Str::length($value) < 3) {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:Ministry|Department|Caisse|Caja|Instituto|Institut|National|Social Security|Pension|Provident|Insurance|Board|Fund|Agency|Authority|Administration|Commission|Office|Organization|Organizations|Company|Companies|Entity|Entities|Centre|Centres|Center|Centers|Committee|Commissioners|Service|Services|Institute|Institution|Directorate|Corporation|Council|Bureau|Secretariat|Court|Services Australia|Payment Denmark|Health New Zealand|WorkSafe|WorkplaceNL|Colpensiones|Bait-ul-Mal)\b/i',
            $value,
        );
    }
}
