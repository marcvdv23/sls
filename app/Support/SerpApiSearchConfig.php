<?php

namespace App\Support;

use App\Models\SerpApiSearchTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SerpApiSearchConfig
{
    public static function defaultKeywords(): array
    {
        return [
            'payroll software',
            'social security administration software',
            'pension administration software',
            'contributions management software',
            'benefit claims administration software',
            'position budgeting software',
            'recruitment software',
            'applicant tracking software',
            'time attendance software',
            'scheduling software',
            'rostering software',
            'leave management software',
            'HRMS',
            'HRIS',
            'human resources management software',
            'HCM',
            'human capital management software',
            'benefits administration software',
            'talent management software',
            'career planning software',
            'competency management software',
            'succession planning software',
            'grants management software',
            'disciplinary actions management software',
            'health & safety management software',
            'parking space management software',
            'office space management software',
            'employee ID card management software',
            'global payroll software',
            'pensioner payroll software',
            'onboarding management software',
            'offboarding management software',
            'employee self-service portal software',
        ];
    }

    public static function defaultRequiredTerms(): array
    {
        return [
            'tender',
            'rfp',
            'rfi',
            'rfq',
            'eoi',
            'request for proposal',
            'request for proposals',
            'request for information',
            'request for quotation',
            'expression of interest',
            'invitation to bid',
            'invitation for bid',
            'invitation for bids',
            'invitation to tender',
            'bid notice',
            'bidding document',
            'bidding documents',
            'procurement notice',
            'contract notice',
            'solicitation',
            'terms of reference',
            'consulting services',
            'notice inviting',
        ];
    }

    public static function defaultBlockedDomains(): array
    {
        return [
            'adp.com',
            'apple.com',
            'bluebisonsoftware.com',
            'capterra.',
            'darwinbox.com',
            'employmenthero.com',
            'facebook.com',
            'flaxem.com',
            'focussoftnet.com',
            'hibob.com',
            'instagram.com',
            'g2.com',
            'getapp.',
            'lattice.com',
            'leverx.com',
            'linkedin.com',
            'paylocity.com',
            'softwareadvice.',
            'sourceforge.',
            'selecthub.',
            'trustradius.',
            'saasworthy.',
            'softwaresuggest.',
            'ramco.com',
            'reddit.com',
            'rsmus.com',
            'peoplemanagingpeople.',
            'triblockhr.com',
            'techradar.',
            'forbes.com',
            'workzoom.com',
            'youtube.com',
        ];
    }

    public static function defaultBlockedPathTerms(): array
    {
        return [
            '/keywords/',
            '/keyword/',
        ];
    }

    public static function defaultVendorTerms(): array
    {
        return [
            'pricing',
            'free trial',
            'book a demo',
            'request a demo',
            'schedule a demo',
            'features',
            'compare',
            'alternatives',
            'reviews',
            'best ',
            'top ',
            'buyer guide',
            'case study',
            'what is ',
            'our software',
            'software solution for',
            'software solutions for',
        ];
    }

    public static function seedDefaultTemplate(): void
    {
        if (! Schema::hasTable('serpapi_search_templates') || SerpApiSearchTemplate::query()->exists()) {
            return;
        }

        SerpApiSearchTemplate::query()->create([
            'name' => 'HR, payroll, and HCM software tenders',
            'focus' => 'hrms_tenders',
            'query_template' => '"{country}" ({keywords}) ("request for proposals" OR RFP OR tender OR "invitation to bid" OR "expression of interest" OR EOI OR RFI) -pricing -demo -"free trial" -"book a demo"',
            'keywords' => static::defaultKeywords(),
            'required_terms' => static::defaultRequiredTerms(),
            'blocked_domains' => static::defaultBlockedDomains(),
            'blocked_path_terms' => static::defaultBlockedPathTerms(),
            'vendor_terms' => static::defaultVendorTerms(),
            'results_per_country' => 10,
            'is_enabled' => true,
        ]);
    }

    public static function predefinedKeywords(): array
    {
        if (! Schema::hasTable('serpapi_search_templates')) {
            return static::defaultKeywords();
        }

        $keywords = SerpApiSearchTemplate::query()
            ->where('is_enabled', true)
            ->get(['keywords'])
            ->flatMap(fn (SerpApiSearchTemplate $template) => $template->keywords ?? [])
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique(fn (string $keyword) => Str::lower($keyword))
            ->values()
            ->all();

        return $keywords !== [] ? $keywords : static::defaultKeywords();
    }

    public static function termsFromText(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique(fn (string $line) => Str::lower($line))
            ->values()
            ->all();
    }

    public static function lines(array|string|null $value): string
    {
        if (is_array($value)) {
            return implode("\n", $value);
        }

        return (string) $value;
    }

    public static function filterParameters(?SerpApiSearchTemplate $template): array
    {
        return [
            'required_terms' => static::listOrDefault($template?->required_terms, static::defaultRequiredTerms()),
            'blocked_domains' => static::listOrDefault($template?->blocked_domains, static::defaultBlockedDomains()),
            'blocked_path_terms' => static::listOrDefault($template?->blocked_path_terms, static::defaultBlockedPathTerms()),
            'vendor_terms' => static::listOrDefault($template?->vendor_terms, static::defaultVendorTerms()),
        ];
    }

    protected static function listOrDefault(mixed $value, array $default): array
    {
        $items = collect((array) $value)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return $items !== [] ? $items : $default;
    }
}
