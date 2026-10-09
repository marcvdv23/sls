<?php

namespace App\Support;

use App\Models\CountryUpdate;
use Illuminate\Support\Str;

class CountryUpdateClassifier
{
    public static function inferFocus(CountryUpdate $update): ?string
    {
        $text = self::searchableText($update);

        if (Str::contains($text, [
            'social security',
            'social security administration',
            'social insurance',
            'social protection',
            'national insurance',
            'national provident fund',
            'pension management information system',
            'pension information system',
            'pension administration',
            'pension system',
            'pension payroll',
            'pensioner payroll',
            'pension fund',
            'provident fund',
            'beneficiary registry',
            'benefit payment system',
            'benefits administration',
            'benefits management',
            'erp',
            'enterprise resource planning',
            'enterprise resources planning',
        ])) {
            return 'social_security';
        }

        if (Str::contains($text, [
            'erm software',
            'enterprise risk management',
            'risk management software',
            'risk management system',
            'risk register',
            'grc',
            'governance risk compliance',
            'compliance software',
            'compliance management',
            'audit management',
            'operational risk',
        ])) {
            return 'erms_tenders';
        }

        if (Str::contains($text, [
            '[ebpc tender intelligence]',
            'ebpc',
            'budgeting software',
            'budgeting',
            'budget management',
            'budget management system',
            'integrated financial management information system',
            'financial management information system',
            'ifmis',
            'fmis',
            'pfmis',
            'public financial management information system',
            'budget planning software',
            'budget preparation system',
            'budget formulation system',
            'budget execution system',
            'budget module',
            'program based budgeting',
            'programme based budgeting',
            'performance based budgeting',
            'financial planning software',
            'forecasting software',
            'medium term expenditure framework',
            'mtef',
        ])) {
            return 'ebpc_tenders';
        }

        $isEquipmentPerformanceManagement = Str::contains($text, [
            'asset performance management',
            'asset performance management system',
            'apms',
            'distribution transformer',
            'distribution transformers',
            'power transformer',
            'power transformers',
            'substation',
            '100kva',
            '200kva',
            'kv rating',
            'kva rating',
        ]);

        $hasHrmsSignal = Str::contains($text, [
            '[hrms tender intelligence]',
            'hrms',
            'hris',
            'hcm',
            'payroll',
            'payroll management',
            'pension payroll',
            'pensioner payroll',
            'human resource',
            'human resources',
            'human resources information system',
            'human resource information system',
            'human resources management software',
            'human resource management software',
            'hrmis',
            'hrims',
            'benefits administration',
            'benefits management',
            'time and attendance',
            'leave management',
            'scheduling software',
            'talent management',
            'performance management',
            'employee performance management',
            'competency management',
            'learning management',
            'training management',
            'succession planning',
            'career planning',
            'recruitment management software',
            'applicant tracking software',
        ]);
        $hasHrPerformanceSignal = Str::contains($text, [
            'performance management',
            'performance management system',
        ]) && Str::contains($text, [
            'employee',
            'personnel',
            'human resource',
            'human resources',
            'workforce',
            'talent',
            'staff',
        ]);

        if (! $isEquipmentPerformanceManagement && ($hasHrmsSignal || $hasHrPerformanceSignal)) {
            return 'hrms_tenders';
        }

        if (Str::contains($text, [
            '[sector tender intelligence]',
            'telecom',
            'telecommunications',
            'oil and gas',
            'petroleum',
            'postal',
            'civil service',
            'public administration',
            'airline',
            'aviation',
            'airport',
            'mining',
            'banking',
            'financial services',
            'central bank',
        ])) {
            return 'sector_tenders';
        }

        if (self::isOfficialLegislation($update, $text)) {
            return 'legislation';
        }

        foreach (ReviewFocuses::all() as $focusKey => $focus) {
            if (in_array($focusKey, ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders', 'legislation'], true)) {
                continue;
            }

            $terms = collect($focus['terms'] ?? [])
                ->merge($focus['strong_signals'] ?? [])
                ->map(fn ($term) => Str::lower(trim((string) $term)))
                ->filter()
                ->unique()
                ->values();

            if ($terms->contains(fn (string $term) => $term !== '' && Str::contains($text, $term))) {
                return $focusKey;
            }
        }

        return null;
    }

    private static function isOfficialLegislation(CountryUpdate $update, string $text): bool
    {
        if (! ReviewFocuses::has('legislation')) {
            return false;
        }

        $sourceText = self::sourceText($update);
        $hasOfficialLawSource = Str::contains($sourceText, [
            'eur-lex',
            'eurlex',
            'official journal of the european union',
            'official journal l',
        ]);

        if ($hasOfficialLawSource) {
            return true;
        }

        $hasCelexIdentifier = Str::contains($text, [
            'celex:',
        ]);
        $hasLegislationType = Str::contains($text, [
            'legislation',
            'regulation',
            'directive',
            'decision',
            'official journal',
        ]);

        return ($hasOfficialLawSource || $hasCelexIdentifier) && $hasLegislationType;
    }

    public static function isTender(CountryUpdate $update): bool
    {
        $fullText = self::searchableText($update);
        $titleText = self::titleText($update);
        $sourceText = self::sourceText($update);

        if (Str::contains($fullText, [
            'bond tender offer',
            'cash tender offer',
            'debt tender offer',
            'notes tender offer',
            'senior notes',
            'exchange offer',
            'repurchase offer',
            'noteholders',
            'bondholders',
            'coupon',
            'securities',
        ])) {
            return false;
        }

        $isTendersOnTime = Str::contains($sourceText, ['tendersontime', 'tenders on time']);

        if (! $isTendersOnTime && self::inferFocus($update) === null) {
            return false;
        }

        $hasExplicitProcurementLanguage = Str::contains($titleText, [
            'tender',
            'rfp',
            'request for proposal',
            'request for proposals',
            'request for expression of interest',
            'request for expressions of interest',
            'expression of interest',
            'request for bids',
            'call for bids',
            'call for offers',
            'invitation for bids',
            'invitation to bid',
            'invitation for offers',
            'bid submission',
            'bidding document',
            'contract notice',
            'terms of reference',
            'procurement notice',
            'procurement opportunity',
        ]);

        if ($hasExplicitProcurementLanguage || $isTendersOnTime) {
            return true;
        }

        return self::isProcurementSource($sourceText, $fullText);
    }

    private static function isProcurementSource(string $sourceText, string $fullText): bool
    {
        if (Str::contains($fullText, ['[official tender source]'])) {
            return true;
        }

        return Str::contains($sourceText, [
            'procurement',
            'tender portal',
            'tender notice',
            'tenders portal',
            'public procurement',
            'e-procurement',
            'eprocurement',
            'world bank procurement',
            'projects.worldbank.org',
            'adb procurement',
            'iadb procurement',
            'idbdocs.iadb.org',
            'ungm.org',
            'dgmarket',
            'devbusiness',
        ]);
    }

    private static function titleText(CountryUpdate $update): string
    {
        return Str::lower(implode(' ', [
            $update->title,
            $update->title_english,
            $update->title_original,
        ]));
    }

    private static function sourceText(CountryUpdate $update): string
    {
        return Str::lower(implode(' ', [
            $update->source_name,
            $update->source_url,
        ]));
    }

    private static function searchableText(CountryUpdate $update): string
    {
        return Str::lower(implode(' ', [
            $update->title,
            $update->title_english,
            $update->title_original,
            $update->summary,
            $update->source_name,
            $update->source_url,
        ]));
    }
}
