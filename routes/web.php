<?php

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryUpdate;
use App\Models\CountryUpdateOpportunity;
use App\Models\CountryUpdateOrganization;
use App\Models\ChatAnswerLog;
use App\Models\CrawlerSetting;
use App\Models\DemoFeatureMoment;
use App\Models\DemoFrame;
use App\Models\DemoSession;
use App\Models\DirectoryImageBatch;
use App\Models\DirectoryImageEntry;
use App\Models\IntelligenceKeyword;
use App\Models\IntelligenceContact;
use App\Models\IntelligenceMonitorPriority;
use App\Models\IntelligenceSource;
use App\Models\IntelligenceSourceAudit;
use App\Models\JournalistArticle;
use App\Models\Journalist;
use App\Models\MarketCrawler;
use App\Models\MarketCrawlerRun;
use App\Models\MarketEmailAccount;
use App\Models\MarketOrganization;
use App\Models\MarketOrganizationActivity;
use App\Models\MarketOrganizationCommunication;
use App\Models\MarketOrganizationContact;
use App\Models\MarketOrganizationTask;
use App\Models\UniversitySurveyTarget;
use App\Models\UniversitySurveyContact;
use App\Models\KnowledgeChunk;
use App\Models\Product;
use App\Models\SourceDocument;
use App\Models\SocialSecurityAdminCandidate;
use App\Models\SlsOperationRun;
use App\Models\SerpApiSearchTemplate;
use App\Models\SlsTask;
use App\Models\TenderAwardedCompany;
use App\Models\AccessAuditLog;
use App\Models\PermissionForm;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupCountryAccess;
use App\Models\UserGroupPermission;
use App\Models\UserGroupProductAccess;
use App\Services\KnowledgeChatService;
use App\Services\KnowledgeIngestionService;
use App\Services\BankDomainGuessService;
use App\Services\CountryIntelligenceMonitor;
use App\Services\DemoMediaKnowledgeService;
use App\Services\DemoMediaIngestionService;
use App\Services\DocumentContactExtractionService;
use App\Services\DirectoryImageImportService;
use App\Services\CountryStoryOpportunityService;
use App\Services\OpportunityEmailDraftService;
use App\Services\IntelligenceSourceCheckerService;
use App\Services\JournalistDiscoveryService;
use App\Services\SourceContactExtractionService;
use App\Services\SocialSecurityAdminDocumentService;
use App\Services\TenderAwardLookupService;
use App\Services\UniversityMarketCrawlerService;
use App\Services\UniversitySurveyCrawlerService;
use App\Support\CountryUpdateClassifier;
use App\Support\CountryUpdateDedupeRules;
use App\Support\CountryUpdateNoiseRules;
use App\Support\SocialSecurityAdminNameCleaner;
use App\Support\TitleLanguage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

$orderedProducts = fn () => Product::query()
    ->orderByRaw("FIELD(name, 'Interact SSAS', 'Interact HRMS', 'Interact ERMS', 'Interact EBPC')")
    ->orderBy('name')
    ->get();

$africaCountries = fn () => collect([
    ['iso' => 'DZ', 'name' => 'Algeria', 'lat' => 28.0, 'lon' => 2.6],
    ['iso' => 'AO', 'name' => 'Angola', 'lat' => -11.2, 'lon' => 17.9],
    ['iso' => 'BJ', 'name' => 'Benin', 'lat' => 9.3, 'lon' => 2.3],
    ['iso' => 'BW', 'name' => 'Botswana', 'lat' => -22.3, 'lon' => 24.7],
    ['iso' => 'BF', 'name' => 'Burkina Faso', 'lat' => 12.2, 'lon' => -1.6],
    ['iso' => 'BI', 'name' => 'Burundi', 'lat' => -3.4, 'lon' => 29.9],
    ['iso' => 'CV', 'name' => 'Cabo Verde', 'lat' => 15.1, 'lon' => -23.6],
    ['iso' => 'CM', 'name' => 'Cameroon', 'lat' => 5.7, 'lon' => 12.7],
    ['iso' => 'CF', 'name' => 'Central African Republic', 'lat' => 6.6, 'lon' => 20.9],
    ['iso' => 'TD', 'name' => 'Chad', 'lat' => 15.4, 'lon' => 18.7],
    ['iso' => 'KM', 'name' => 'Comoros', 'lat' => -11.9, 'lon' => 43.8],
    ['iso' => 'CG', 'name' => 'Congo', 'lat' => -0.7, 'lon' => 15.8],
    ['iso' => 'CI', 'name' => "Cote d'Ivoire", 'lat' => 7.5, 'lon' => -5.6],
    ['iso' => 'CD', 'name' => 'Democratic Republic of the Congo', 'lat' => -2.9, 'lon' => 23.7],
    ['iso' => 'DJ', 'name' => 'Djibouti', 'lat' => 11.8, 'lon' => 42.6],
    ['iso' => 'EG', 'name' => 'Egypt', 'lat' => 26.8, 'lon' => 30.8],
    ['iso' => 'GQ', 'name' => 'Equatorial Guinea', 'lat' => 1.6, 'lon' => 10.3],
    ['iso' => 'ER', 'name' => 'Eritrea', 'lat' => 15.2, 'lon' => 39.8],
    ['iso' => 'SZ', 'name' => 'Eswatini', 'lat' => -26.5, 'lon' => 31.5],
    ['iso' => 'ET', 'name' => 'Ethiopia', 'lat' => 8.6, 'lon' => 39.6],
    ['iso' => 'GA', 'name' => 'Gabon', 'lat' => -0.8, 'lon' => 11.6],
    ['iso' => 'GM', 'name' => 'Gambia', 'lat' => 13.4, 'lon' => -15.4],
    ['iso' => 'GH', 'name' => 'Ghana', 'lat' => 7.9, 'lon' => -1.0],
    ['iso' => 'GN', 'name' => 'Guinea', 'lat' => 10.4, 'lon' => -10.9],
    ['iso' => 'GW', 'name' => 'Guinea-Bissau', 'lat' => 12.0, 'lon' => -15.0],
    ['iso' => 'KE', 'name' => 'Kenya', 'lat' => 0.1, 'lon' => 37.9],
    ['iso' => 'LS', 'name' => 'Lesotho', 'lat' => -29.6, 'lon' => 28.2],
    ['iso' => 'LR', 'name' => 'Liberia', 'lat' => 6.4, 'lon' => -9.4],
    ['iso' => 'LY', 'name' => 'Libya', 'lat' => 26.3, 'lon' => 17.2],
    ['iso' => 'MG', 'name' => 'Madagascar', 'lat' => -18.8, 'lon' => 46.9],
    ['iso' => 'MW', 'name' => 'Malawi', 'lat' => -13.3, 'lon' => 34.3],
    ['iso' => 'ML', 'name' => 'Mali', 'lat' => 17.6, 'lon' => -3.9],
    ['iso' => 'MR', 'name' => 'Mauritania', 'lat' => 20.3, 'lon' => -10.9],
    ['iso' => 'MU', 'name' => 'Mauritius', 'lat' => -20.2, 'lon' => 57.5],
    ['iso' => 'MA', 'name' => 'Morocco', 'lat' => 31.8, 'lon' => -7.1],
    ['iso' => 'MZ', 'name' => 'Mozambique', 'lat' => -18.7, 'lon' => 35.5],
    ['iso' => 'NA', 'name' => 'Namibia', 'lat' => -22.6, 'lon' => 17.1],
    ['iso' => 'NE', 'name' => 'Niger', 'lat' => 17.6, 'lon' => 8.1],
    ['iso' => 'NG', 'name' => 'Nigeria', 'lat' => 9.1, 'lon' => 8.7],
    ['iso' => 'RW', 'name' => 'Rwanda', 'lat' => -1.9, 'lon' => 29.9],
    ['iso' => 'SN', 'name' => 'Senegal', 'lat' => 14.5, 'lon' => -14.5],
    ['iso' => 'SL', 'name' => 'Sierra Leone', 'lat' => 8.5, 'lon' => -11.8],
    ['iso' => 'SO', 'name' => 'Somalia', 'lat' => 5.1, 'lon' => 46.2],
    ['iso' => 'ZA', 'name' => 'South Africa', 'lat' => -30.6, 'lon' => 22.9],
    ['iso' => 'SS', 'name' => 'South Sudan', 'lat' => 7.3, 'lon' => 30.3],
    ['iso' => 'SD', 'name' => 'Sudan', 'lat' => 15.9, 'lon' => 30.2],
    ['iso' => 'TZ', 'name' => 'Tanzania', 'lat' => -6.4, 'lon' => 34.9],
    ['iso' => 'TG', 'name' => 'Togo', 'lat' => 8.6, 'lon' => 1.1],
    ['iso' => 'TN', 'name' => 'Tunisia', 'lat' => 33.9, 'lon' => 9.5],
    ['iso' => 'UG', 'name' => 'Uganda', 'lat' => 1.4, 'lon' => 32.3],
    ['iso' => 'ZM', 'name' => 'Zambia', 'lat' => -13.1, 'lon' => 27.8],
    ['iso' => 'ZW', 'name' => 'Zimbabwe', 'lat' => -19.0, 'lon' => 29.2],
])->map(function (array $country) {
    $country['x'] = round((($country['lon'] + 25) / 85) * 100, 2);
    $country['y'] = round(((38 - $country['lat']) / 75) * 100, 2);
    $country['region_group'] = 'Africa';

    return $country;
});

$caribbeanCountries = fn () => collect([
    ['iso' => 'AI', 'name' => 'Anguilla', 'lat' => 18.2, 'lon' => -63.1],
    ['iso' => 'AG', 'name' => 'Antigua and Barbuda', 'lat' => 17.1, 'lon' => -61.8],
    ['iso' => 'AW', 'name' => 'Aruba', 'lat' => 12.5, 'lon' => -70.0],
    ['iso' => 'BS', 'name' => 'Bahamas', 'lat' => 25.0, 'lon' => -77.4],
    ['iso' => 'BB', 'name' => 'Barbados', 'lat' => 13.2, 'lon' => -59.5],
    ['iso' => 'BZ', 'name' => 'Belize', 'lat' => 17.2, 'lon' => -88.5],
    ['iso' => 'BM', 'name' => 'Bermuda', 'lat' => 32.3, 'lon' => -64.8],
    ['iso' => 'VG', 'name' => 'British Virgin Islands', 'lat' => 18.4, 'lon' => -64.6],
    ['iso' => 'KY', 'name' => 'Cayman Islands', 'lat' => 19.3, 'lon' => -81.3],
    ['iso' => 'CU', 'name' => 'Cuba', 'lat' => 21.5, 'lon' => -78.9],
    ['iso' => 'CW', 'name' => 'Curacao', 'lat' => 12.2, 'lon' => -69.0],
    ['iso' => 'DM', 'name' => 'Dominica', 'lat' => 15.4, 'lon' => -61.4],
    ['iso' => 'DO', 'name' => 'Dominican Republic', 'lat' => 18.8, 'lon' => -70.2],
    ['iso' => 'GD', 'name' => 'Grenada', 'lat' => 12.1, 'lon' => -61.7],
    ['iso' => 'GP', 'name' => 'Guadeloupe', 'lat' => 16.3, 'lon' => -61.6],
    ['iso' => 'GY', 'name' => 'Guyana', 'lat' => 5.0, 'lon' => -58.9],
    ['iso' => 'HT', 'name' => 'Haiti', 'lat' => 19.0, 'lon' => -72.3],
    ['iso' => 'JM', 'name' => 'Jamaica', 'lat' => 18.1, 'lon' => -77.3],
    ['iso' => 'MQ', 'name' => 'Martinique', 'lat' => 14.6, 'lon' => -61.0],
    ['iso' => 'MS', 'name' => 'Montserrat', 'lat' => 16.7, 'lon' => -62.2],
    ['iso' => 'PR', 'name' => 'Puerto Rico', 'lat' => 18.2, 'lon' => -66.5],
    ['iso' => 'KN', 'name' => 'Saint Kitts and Nevis', 'lat' => 17.4, 'lon' => -62.8],
    ['iso' => 'LC', 'name' => 'Saint Lucia', 'lat' => 13.9, 'lon' => -61.0],
    ['iso' => 'VC', 'name' => 'Saint Vincent and the Grenadines', 'lat' => 13.2, 'lon' => -61.2],
    ['iso' => 'SX', 'name' => 'Sint Maarten', 'lat' => 18.0, 'lon' => -63.1],
    ['iso' => 'SR', 'name' => 'Suriname', 'lat' => 4.1, 'lon' => -56.0],
    ['iso' => 'TT', 'name' => 'Trinidad and Tobago', 'lat' => 10.7, 'lon' => -61.2],
    ['iso' => 'TC', 'name' => 'Turks and Caicos Islands', 'lat' => 21.7, 'lon' => -71.8],
    ['iso' => 'VI', 'name' => 'US Virgin Islands', 'lat' => 18.3, 'lon' => -64.9],
])->map(function (array $country) {
    $country['x'] = round((($country['lon'] + 90) / 35) * 100, 2);
    $country['y'] = round(((34 - $country['lat']) / 31) * 100, 2);
    $country['region_group'] = 'Caribbean';

    return $country;
});

$asiaCountries = fn () => collect([
    'AF' => [33.9, 67.7], 'AM' => [40.1, 45.0], 'AZ' => [40.1, 47.6], 'BH' => [26.1, 50.6],
    'BD' => [23.7, 90.4], 'BT' => [27.5, 90.4], 'BN' => [4.5, 114.7], 'KH' => [12.6, 104.9],
    'CN' => [35.9, 104.2], 'GE' => [42.3, 43.4], 'IN' => [20.6, 78.9], 'ID' => [-2.5, 118.0],
    'IR' => [32.4, 53.7], 'IQ' => [33.2, 43.7], 'IL' => [31.0, 35.0], 'JP' => [36.2, 138.3],
    'JO' => [31.2, 36.2], 'KZ' => [48.0, 67.0], 'KW' => [29.3, 47.5], 'KG' => [41.2, 74.8],
    'LA' => [19.9, 102.5], 'LB' => [33.9, 35.9], 'MY' => [4.2, 102.0], 'MV' => [3.2, 73.2],
    'MN' => [46.9, 103.8], 'MM' => [21.9, 95.9], 'NP' => [28.4, 84.1], 'KP' => [40.3, 127.5],
    'OM' => [21.5, 55.9], 'PK' => [30.4, 69.3], 'PS' => [31.9, 35.2], 'PH' => [12.9, 121.8],
    'QA' => [25.4, 51.2], 'SA' => [23.9, 45.1], 'SG' => [1.35, 103.8], 'KR' => [36.5, 127.8],
    'LK' => [7.9, 80.8], 'SY' => [34.8, 38.9], 'TW' => [23.7, 121.0], 'TJ' => [38.9, 71.0],
    'TH' => [15.9, 101.0], 'TL' => [-8.9, 125.7], 'TR' => [39.0, 35.2], 'TM' => [38.9, 59.6],
    'AE' => [24.4, 54.3], 'UZ' => [41.4, 64.6], 'VN' => [14.1, 108.3], 'YE' => [15.6, 48.5],
])->map(function (array $latLon, string $iso) {
    $country = [
        'iso' => $iso,
        'name' => config("country_intelligence.monitored_countries.$iso.name", $iso),
        'lat' => $latLon[0],
        'lon' => $latLon[1],
    ];
    $country['x'] = round((($country['lon'] - 25) / 125) * 100, 2);
    $country['y'] = round(((55 - $country['lat']) / 70) * 100, 2);
    $country['region_group'] = 'Asia';

    return $country;
})->values();

$latinAmericaCountries = fn () => collect([
    'AR' => [-38.4, -63.6], 'BO' => [-16.3, -63.6], 'BR' => [-14.2, -51.9], 'CL' => [-35.7, -71.5],
    'CO' => [4.6, -74.1], 'CR' => [9.7, -84.2], 'EC' => [-1.8, -78.2], 'SV' => [13.8, -88.9],
    'GT' => [15.8, -90.2], 'HN' => [15.2, -86.2], 'MX' => [23.6, -102.5], 'NI' => [12.9, -85.2],
    'PA' => [8.5, -80.8], 'PY' => [-23.4, -58.4], 'PE' => [-9.2, -75.0], 'UY' => [-32.5, -55.8],
    'VE' => [6.4, -66.6],
])->map(function (array $latLon, string $iso) {
    $country = [
        'iso' => $iso,
        'name' => config("country_intelligence.monitored_countries.$iso.name", $iso),
        'lat' => $latLon[0],
        'lon' => $latLon[1],
    ];
    $country['x'] = round((($country['lon'] + 120) / 90) * 100, 2);
    $country['y'] = round(((35 - $country['lat']) / 75) * 100, 2);
    $country['region_group'] = 'Latin America';

    return $country;
})->values();

$northAmericaCountries = fn () => collect([
    'CA' => [56.1, -106.3],
    'US' => [39.8, -98.6],
])->map(function (array $latLon, string $iso) {
    $country = [
        'iso' => $iso,
        'name' => config("country_intelligence.monitored_countries.$iso.name", $iso),
        'lat' => $latLon[0],
        'lon' => $latLon[1],
    ];
    $country['x'] = round((($country['lon'] + 170) / 120) * 100, 2);
    $country['y'] = round(((75 - $country['lat']) / 55) * 100, 2);
    $country['region_group'] = 'North America';

    return $country;
})->values();

$europeCountries = fn () => collect([
    'AL' => [41.2, 20.2], 'AD' => [42.5, 1.6], 'AT' => [47.5, 14.5], 'BE' => [50.5, 4.5],
    'BA' => [44.2, 17.7], 'BG' => [42.7, 25.5], 'HR' => [45.1, 15.2], 'CY' => [35.1, 33.4],
    'CZ' => [49.8, 15.5], 'DK' => [56.2, 9.5], 'EE' => [58.6, 25.0], 'FI' => [61.9, 25.7],
    'FR' => [46.2, 2.2], 'DE' => [51.2, 10.5], 'GR' => [39.1, 22.9], 'HU' => [47.2, 19.5],
    'IS' => [64.9, -18.6], 'IE' => [53.4, -8.2], 'IT' => [41.9, 12.6], 'XK' => [42.6, 20.9],
    'LV' => [56.9, 24.6], 'LI' => [47.2, 9.6], 'LT' => [55.2, 23.9], 'LU' => [49.8, 6.1],
    'MT' => [35.9, 14.4], 'MD' => [47.4, 28.4], 'MC' => [43.7, 7.4], 'ME' => [42.7, 19.4],
    'NL' => [52.1, 5.3], 'MK' => [41.6, 21.7], 'NO' => [60.5, 8.5], 'PL' => [51.9, 19.1],
    'PT' => [39.4, -8.2], 'RO' => [45.9, 24.9], 'SM' => [43.9, 12.5], 'RS' => [44.0, 20.9],
    'SK' => [48.7, 19.7], 'SI' => [46.1, 14.8], 'ES' => [40.5, -3.7], 'SE' => [60.1, 18.6],
    'CH' => [46.8, 8.2], 'UA' => [48.4, 31.2], 'GB' => [55.4, -3.4], 'VA' => [41.9, 12.5],
])->map(function (array $latLon, string $iso) {
    $country = [
        'iso' => $iso,
        'name' => config("country_intelligence.monitored_countries.$iso.name", $iso),
        'lat' => $latLon[0],
        'lon' => $latLon[1],
    ];
    $country['x'] = round((($country['lon'] + 25) / 70) * 100, 2);
    $country['y'] = round(((72 - $country['lat']) / 38) * 100, 2);
    $country['region_group'] = 'Europe';

    return $country;
})->values();

$allMapCountries = fn () => $africaCountries()->merge($caribbeanCountries())->merge($asiaCountries())->merge($latinAmericaCountries())->merge($northAmericaCountries())->merge($europeCountries());

$relevantCountryUpdates = function ($countries, string $focus = 'social_security', ?string $publishedSince = null) {
    $focus = array_key_exists($focus, config('country_intelligence.focuses', [])) ? $focus : 'social_security';
    $focusLabel = config("country_intelligence.focuses.$focus.label");
    $focusTerms = collect(config("country_intelligence.focuses.$focus.terms", []))
        ->merge(config("country_intelligence.focuses.$focus.strong_signals", []))
        ->unique()
        ->values();

    $countryNames = $countries->pluck('name')->all();
    $countryIsoCodes = $countries->pluck('iso')->all();
    $dbCountries = Country::query()
        ->whereIn('name', $countryNames)
        ->orWhereIn('iso_code', $countryIsoCodes)
        ->get()
        ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));

    $updatesByCountryId = CountryUpdate::query()
        ->with('country')
        ->where('review_status', '<>', 'rejected')
        ->whereHas('country', fn ($query) => $query->whereIn('name', $countryNames)->orWhereIn('iso_code', $countryIsoCodes))
        ->when($publishedSince, fn ($query) => $query->whereNotNull('publication_date')->where('publication_date', '>=', $publishedSince))
        ->where(function ($query) use ($focus, $focusLabel, $focusTerms) {
            if ($focus !== 'social_security') {
                $query->where('summary', 'like', '%[' . $focusLabel . ']%');

                foreach ($focusTerms as $term) {
                    $query->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('summary', 'like', '%' . $term . '%');
                }

                return;
            }

            $query->where('title', 'like', '%social security%')
                ->orWhere('title', 'like', '%pension%')
                ->orWhere('title', 'like', '%provident%')
                ->orWhere('title', 'like', '%tender%')
                ->orWhere('title', 'like', '%procurement%')
                ->orWhere('title', 'like', '%request for proposal%')
                ->orWhere('title', 'like', '%expression of interest%')
                ->orWhere('title', 'like', '%bid%')
                ->orWhere('title', 'like', '%ministry of labor%')
                ->orWhere('title', 'like', '%ministry of labour%')
                ->orWhere('title', 'like', '%protection sociale%')
                ->orWhere('title', 'like', '%securite sociale%')
                ->orWhere('title', 'like', '%sÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© sociale%')
                ->orWhere('title', 'like', '%retraite%')
                ->orWhere('title', 'like', '%IPRES%')
                ->orWhere('title', 'like', '%CSS%')
                ->orWhere('title', 'like', '%ABSSB%')
                ->orWhere('title', 'like', '%contribution%')
                ->orWhere('summary', 'like', '%social security%')
                ->orWhere('summary', 'like', '%pension%')
                ->orWhere('summary', 'like', '%provident%')
                ->orWhere('summary', 'like', '%tender%')
                ->orWhere('summary', 'like', '%procurement%')
                ->orWhere('summary', 'like', '%request for proposal%')
                ->orWhere('summary', 'like', '%expression of interest%')
                ->orWhere('summary', 'like', '%bid%')
                ->orWhere('summary', 'like', '%ministry of labor%')
                ->orWhere('summary', 'like', '%ministry of labour%')
                ->orWhere('summary', 'like', '%protection sociale%')
                ->orWhere('summary', 'like', '%securite sociale%')
                ->orWhere('summary', 'like', '%sÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© sociale%')
                ->orWhere('summary', 'like', '%retraite%')
                ->orWhere('summary', 'like', '%IPRES%')
                ->orWhere('summary', 'like', '%CSS%')
                ->orWhere('summary', 'like', '%ABSSB%')
                ->orWhere('summary', 'like', '%contribution%');
        })
        ->latest('publication_date')
        ->latest('retrieved_at')
        ->get()
        ->groupBy('country_id');

    $containsNonLatinScript = static fn (?string $value): bool => filled($value) && preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', (string) $value) === 1;
    $cleanEnglishTitle = static function (?string $englishTitle, ?string $originalTitle) use ($containsNonLatinScript): string {
        $englishTitle = trim((string) $englishTitle);
        $originalTitle = trim((string) $originalTitle);

        if ($englishTitle === '' || Str::lower($englishTitle) === 'translation pending') {
            return '';
        }

        if ($originalTitle !== '' && $englishTitle === $originalTitle && TitleLanguage::looksNonEnglish($originalTitle)) {
            return '';
        }

        return $englishTitle;
    };
    $classifyMapItemType = static function (?CountryUpdate $update): string {
        if (! $update) {
            return 'none';
        }

        return CountryUpdateClassifier::isTender($update) ? 'tender' : 'news';
    };
    $storySummary = static function (?CountryUpdate $update, string $displayTitle, string $itemType): string {
        if (! $update) {
            return '';
        }

        $sourceName = trim((string) $update->source_name);
        $countryName = trim((string) $update->country?->name);
        $rawSummary = trim((string) ($update->summary_english ?: $update->summary));
        $rawSummary = preg_replace('/\[[^\]]+\]\s*/', '', $rawSummary) ?? '';
        $rawSummary = preg_replace('/\bAggregator lead\s*[-â€“]\s*/i', '', $rawSummary) ?? '';
        $rawSummary = preg_replace('/\bPotential (?:social security|HRMS|ERMS|EBPC|sector|procurement|tender)[^.]*\.?\s*/i', '', $rawSummary) ?? '';
        $rawSummary = preg_replace('/\bMatched themes?:[^.]*\.?\s*/i', '', $rawSummary) ?? '';
        $rawSummary = preg_replace('/\bManual classification note:[^.]*\.?\s*/i', '', $rawSummary) ?? '';
        $rawSummary = trim(preg_replace('/\s+/', ' ', $rawSummary) ?? '');

        $rawSummaryLooksBoilerplate = $rawSummary === ''
            || Str::contains(Str::lower($rawSummary), ['verify at official source', 'official source', 'classified as', 'matched themes']);

        $topicSentence = $displayTitle !== '' && $displayTitle !== 'Translation pending'
            ? $displayTitle
            : trim((string) ($update->title_english ?: $update->title ?: $update->title_original));
        $topicSentence = rtrim($topicSentence, '. ');

        $sentences = [];
        if ($topicSentence !== '') {
            $sentences[] = ($itemType === 'tender' ? 'The tender notice concerns ' : 'The story reports on ') . lcfirst($topicSentence) . '.';
        }

        if (! $rawSummaryLooksBoilerplate && $rawSummary !== '') {
            $cleanSentences = collect(preg_split('/(?<=[.!?])\s+/', $rawSummary) ?: [])
                ->map(fn ($sentence) => trim((string) $sentence))
                ->filter(fn ($sentence) => $sentence !== '' && ! Str::contains(Str::lower($sentence), ['verify at official source', 'matched themes']))
                ->take(2)
                ->values();

            foreach ($cleanSentences as $sentence) {
                $sentences[] = preg_match('/[.!?]$/', $sentence) ? $sentence : rtrim($sentence, '.!?') . '.';
            }
        }

        if (count($sentences) < 2) {
            $context = [];
            if ($countryName !== '') {
                $context[] = 'in ' . $countryName;
            }
            if ($sourceName !== '') {
                $context[] = 'from ' . $sourceName;
            }
            $sentences[] = 'The useful context for review is the country/source trail' . (empty($context) ? '' : ' ' . implode(' ', $context)) . '.';
        }

        return Str::limit(implode(' ', array_slice($sentences, 0, 2)), 360, '');
    };

    return $countries->map(function (array $country) use ($dbCountries, $updatesByCountryId, $cleanEnglishTitle, $classifyMapItemType, $storySummary, $containsNonLatinScript) {
        $dbCountry = $dbCountries->get($country['iso']);
        $updates = $dbCountry ? $updatesByCountryId->get($dbCountry->id, collect())->take(3)->values() : collect();
        $latest = $updates->first();
        $originalTitle = $latest ? trim((string) ($latest->title_original ?: $latest->title)) : '';
        $englishTitle = $latest ? $cleanEnglishTitle($latest->title_english, $originalTitle) : '';
        $displayTitle = $englishTitle !== '' ? $englishTitle : (TitleLanguage::looksNonEnglish($originalTitle) ? 'Translation pending' : $originalTitle);
        $sortDate = $latest?->publication_date?->format('Y-m-d') ?? $latest?->retrieved_at?->format('Y-m-d H:i:s');
        $latestType = $classifyMapItemType($latest);
        $latestOpened = (bool) $latest?->map_opened_at;
        $latestProcessed = (bool) $latest?->map_processed_at;

        $country['database_id'] = $dbCountry?->id;
        $country['latest_id'] = $latest?->id;
        $country['latest_type'] = $latestType;
        $country['latest_title'] = $displayTitle;
        $country['latest_original_title'] = $originalTitle;
        $country['latest_needs_translation'] = $displayTitle === 'Translation pending';
        $country['latest_summary'] = $storySummary($latest, $displayTitle, $latestType);
        $country['latest_date'] = $latest?->publication_date?->toDateString() ?? $latest?->retrieved_at?->toDateString();
        $country['latest_sort_date'] = $sortDate;
        $country['latest_source'] = $latest?->source_name;
        $country['latest_url'] = $latest?->source_url;
        $country['latest_opened'] = $latestOpened;
        $country['latest_processed'] = $latestProcessed;
        $country['latest_action_status'] = $latest?->map_action_status;
        $country['latest_opened_url'] = $latest ? route('sls.intelligence.mapItems.opened', ['update' => $latest]) : null;
        $country['latest_action_url'] = $latest ? route('sls.intelligence.mapItems.action', ['update' => $latest]) : null;
        $country['latest_follow_up_url'] = $latest ? route('sls.intelligence.updates.sourcePage', ['countryUpdate' => $latest]) . '#follow-up' : null;
        $country['update_count'] = $updates->count();
        $country['status'] = $updates->isNotEmpty() ? 'has-update' : ($dbCountry ? 'monitored' : 'not-started');

        return $country;
    });
};

Route::get('/', function () {
    return redirect('/sls');
});

Route::get('/sls/login', function () {
    if (auth()->check()) {
        return redirect('/sls');
    }

    return view('sls.auth.login');
})->name('sls.login');

Route::post('/sls/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $rateKey = Str::lower($credentials['email']) . '|' . $request->ip();

    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 5)) {
        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Too many sign-in attempts. Please wait a minute and try again.',
            ]);
    }

    if (auth()->attempt([
        'email' => $credentials['email'],
        'password' => $credentials['password'],
        'is_active' => true,
    ], $request->boolean('remember'))) {
        \Illuminate\Support\Facades\RateLimiter::clear($rateKey);
        $request->session()->regenerate();

        return redirect()->intended('/sls');
    }

    \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 60);

    return back()
        ->withInput($request->only('email'))
        ->withErrors([
            'email' => 'The email or password is not correct, or the account is inactive.',
        ]);
})->name('sls.login.store');

Route::post('/sls/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('sls.login');
})->name('sls.logout');

Route::post('/sls/intelligence/map-items/{update}/opened', function (CountryUpdate $update) {
    abort_if($update->review_status === 'rejected', 404);

    $update->forceFill([
        'map_opened_at' => $update->map_opened_at ?: now(),
        'map_opened_by_user_id' => $update->map_opened_by_user_id ?: auth()->id(),
    ])->save();

    return response()->json([
        'ok' => true,
        'state' => $update->map_processed_at ? 'processed' : 'opened',
        'opened_at' => $update->map_opened_at?->toDateTimeString(),
    ]);
})->name('sls.intelligence.mapItems.opened');

Route::post('/sls/intelligence/map-items/{update}/action', function (Request $request, CountryUpdate $update) {
    abort_if($update->review_status === 'rejected', 404);

    $data = $request->validate([
        'action_status' => ['required', 'in:no_action_required,action_taken,follow_up'],
        'action_note' => ['nullable', 'string', 'max:1000'],
    ]);

    $processed = in_array($data['action_status'], ['no_action_required', 'action_taken'], true);

    $update->forceFill([
        'map_opened_at' => $update->map_opened_at ?: now(),
        'map_opened_by_user_id' => $update->map_opened_by_user_id ?: auth()->id(),
        'map_action_status' => $data['action_status'],
        'map_action_note' => filled($data['action_note'] ?? null) ? $data['action_note'] : null,
        'map_processed_at' => $processed ? now() : null,
        'map_processed_by_user_id' => $processed ? auth()->id() : null,
    ])->save();

    if ($data['action_status'] === 'follow_up') {
        $update->forceFill([
            'is_favorite' => true,
            'favorited_at' => $update->favorited_at ?: now(),
            'favorite_note' => filled($data['action_note'] ?? null)
                ? $data['action_note']
                : 'Follow up from dashboard map review.',
        ])->save();
    }

    return response()->json([
        'ok' => true,
        'state' => $update->map_processed_at ? 'processed' : 'opened',
        'action_status' => $update->map_action_status,
    ]);
})->name('sls.intelligence.mapItems.action');

Route::post('/sls/tracked-countries/admin-urls', function (Request $request) {
    $data = $request->validate([
        'country_iso' => ['required', 'string', 'max:8'],
        'country_name' => ['required', 'string', 'max:255'],
        'region' => ['nullable', 'string', 'max:120'],
        'organization_name' => ['required', 'string', 'max:255'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'general_url' => ['nullable', 'string', 'max:1000'],
        'press_url' => ['nullable', 'string', 'max:1000'],
        'tenders_url' => ['nullable', 'string', 'max:1000'],
    ]);

    $normalizeUrl = function (?string $url): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    };
    $normalizeName = fn (string $name): string => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    $domainFromUrl = function (?string $url): ?string {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? Str::of($host)->lower()->replaceStart('www.', '')->toString() : null;
    };

    $urls = [
        'general_url' => $normalizeUrl($data['general_url'] ?? null),
        'press_url' => $normalizeUrl($data['press_url'] ?? null),
        'tenders_url' => $normalizeUrl($data['tenders_url'] ?? null),
    ];

    foreach ($urls as $field => $url) {
        if (filled($data[$field] ?? null) && $url === null) {
            return response()->json([
                'message' => 'Please enter a valid URL for ' . str_replace('_', ' ', $field) . '.',
            ], 422);
        }
    }

    $iso = Str::upper(trim((string) $data['country_iso']));
    $name = trim((string) $data['organization_name']);
    $nameNormalized = $normalizeName($name);
    $country = Country::query()->where('iso_code', $iso)->first();
    $defaultProduct = Product::query()
        ->where('name', 'Interact SSAS')
        ->orWhere('code', 'SSAS')
        ->first();
    $product = filled($data['product_id'] ?? null)
        ? Product::query()->find((int) $data['product_id'])
        : $defaultProduct;
    $productId = $product?->id;
    $productKey = $product
        ? Str::of($product->code ?: $product->name)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString()
        : 'unmapped';
    $crawler = MarketCrawler::firstOrCreate(
        ['crawler_key' => 'social_security_organization'],
        [
            'name' => 'Social security organization crawler',
            'crawler_type' => 'social_security_contact_crawler',
            'description' => 'Finds official social security organization websites, media/press pages, procurement pages, leadership, and public contacts.',
            'is_enabled' => true,
        ],
    );

    $organization = MarketOrganization::query()
        ->where('country_iso', $iso)
        ->where('organization_subcategory', 'social_security_administration')
        ->where('name_normalized', $nameNormalized)
        ->when(Schema::hasColumn('market_organizations', 'product_id'), function ($query) use ($defaultProduct, $productId) {
            $query->where(function ($inner) use ($defaultProduct, $productId) {
                $inner->where('product_id', $productId);

                if ($productId !== null && $defaultProduct?->id === $productId) {
                    $inner->orWhereNull('product_id');
                }
            });
        })
        ->first();

    if (! $organization) {
        $organization = new MarketOrganization([
            'source_fingerprint' => hash('sha256', 'manual-organization-url|' . $productKey . '|' . $iso . '|' . $nameNormalized),
        ]);
    }

    $organizationData = [
        'market_crawler_id' => $organization->market_crawler_id ?: $crawler->id,
        'name' => Str::limit($name, 255, ''),
        'name_normalized' => $nameNormalized,
        'organization_type' => 'government_agency',
        'industry' => 'government',
        'organization_subcategory' => 'social_security_administration',
        'country' => $country?->name ?: $data['country_name'],
        'country_raw' => $data['country_name'],
        'country_iso' => $iso,
        'country_resolution_status' => 'resolved',
        'region' => $country?->region ?: ($data['region'] ?? null),
        'website_url' => $urls['general_url'],
        'website_domain' => $domainFromUrl($urls['general_url']),
        'news_page_url' => $urls['press_url'],
        'procurement_page_url' => $urls['tenders_url'],
        'status' => 'active',
        'lead_status' => $organization->lead_status ?: 'researching',
        'lead_source' => 'manual_social_security_admin_url',
        'notes' => trim(collect([
            $organization->notes,
            'Manual dashboard URL review updated ' . now()->toDateTimeString() . ' for ' . ($product?->name ?: 'unmapped product') . '.',
        ])->filter()->unique()->implode("\n")) ?: null,
        'last_crawler_name' => $crawler->name,
    ];

    if (Schema::hasColumn('market_organizations', 'product_id')) {
        $organizationData['product_id'] = $productId;
    }

    $organization->fill($organizationData)->save();

    return response()->json([
        'ok' => true,
        'organization_id' => $organization->id,
        'product_id' => $productId,
        'urls' => $urls,
    ]);
})->name('sls.trackedCountries.adminUrls.update');

Route::post('/sls/tracked-countries/admin-urls/delete', function (Request $request) {
    $data = $request->validate([
        'country_iso' => ['required', 'string', 'max:8'],
        'organization_name' => ['required', 'string', 'max:255'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'reason' => ['nullable', 'string', 'max:80'],
    ]);

    $iso = Str::upper(trim((string) $data['country_iso']));
    $name = trim((string) $data['organization_name']);
    $nameNormalized = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    $reason = in_array((string) ($data['reason'] ?? 'duplicate'), ['duplicate', 'invalid', 'not_relevant'], true)
        ? (string) ($data['reason'] ?? 'duplicate')
        : 'duplicate';

    if (Schema::hasTable('social_security_admin_name_suppressions')) {
        DB::table('social_security_admin_name_suppressions')->updateOrInsert(
            [
                'country_iso' => $iso,
                'name_normalized' => $nameNormalized,
            ],
            [
                'name' => $name,
                'reason' => $reason,
                'notes' => 'Removed manually from the dashboard organization list.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    SocialSecurityAdminCandidate::query()
        ->where('country_iso', $iso)
        ->where('organization_name', $name)
        ->update(['status' => 'rejected', 'updated_at' => now()]);

    $organizations = MarketOrganization::query()
        ->where('country_iso', $iso)
        ->where('organization_subcategory', 'social_security_administration')
        ->where('name_normalized', $nameNormalized)
        ->when(Schema::hasColumn('market_organizations', 'product_id') && filled($data['product_id'] ?? null), fn ($query) => $query->where(function ($inner) use ($data) {
            $inner->where('product_id', (int) $data['product_id'])->orWhereNull('product_id');
        }));

    $organizations->update([
        'status' => 'duplicate',
        'lead_status' => 'duplicate',
        'notes' => DB::raw("TRIM(CONCAT(COALESCE(notes, ''), '\nRemoved manually from dashboard as duplicate on " . now()->toDateTimeString() . ".'))"),
        'updated_at' => now(),
    ]);

    return response()->json([
        'ok' => true,
        'country_iso' => $iso,
        'organization_name' => $name,
    ]);
})->name('sls.trackedCountries.adminUrls.delete');

Route::get('/sls', function () use ($orderedProducts, $allMapCountries, $relevantCountryUpdates) {
    $inferIntelligenceFocus = function (CountryUpdate $update): ?string {
        $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->summary . ' ' . $update->source_name . ' ' . $update->source_url);
        $sourceText = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->summary . ' ' . $update->source_name . ' ' . $update->source_url);

        if (Str::contains($text, [
            'social security',
            'social insurance',
            'social protection',
            'national insurance',
            'pension management information system',
            'pension information system',
            'pension administration',
            'pension system',
            'pension fund',
            'provident fund',
            'beneficiary registry',
            'benefit payment system',
        ])) {
            return 'social_security';
        }

        if (Str::contains($sourceText, [
            'erm software',
            'enterprise risk management',
            'risk management software',
            'risk management system',
            'risk register',
            'grc',
            'governance risk compliance',
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

        if (Str::contains($text, [
            '[hrms tender intelligence]',
            'hrms',
            'hcm',
            'payroll',
            'human resource',
            'human resources',
            'hrmis',
            'hrims',
            'benefits administration',
            'talent management',
            'performance management',
        ])) {
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

        return null;
    };
    $inferIntelligenceFocus = fn (CountryUpdate $update): ?string => CountryUpdateClassifier::inferFocus($update);
    $monitorHealth = function (string|array $focus, string $label, string $region = 'all') {
        $focuses = is_array($focus) ? $focus : [$focus];
        $latestRun = CountryMonitorRun::query()
            ->with('country')
            ->whereIn('focus', $focuses)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();
        $minutesSinceRun = $latestRun?->finished_at
            ? (int) $latestRun->finished_at->diffInMinutes(now())
            : null;

        return [
            'focus' => implode(',', $focuses),
            'healthy' => $minutesSinceRun !== null && $minutesSinceRun <= 30,
            'items_found' => $latestRun?->items_found,
            'label' => $label,
            'last_country' => $latestRun?->country?->name,
            'last_focus' => $latestRun?->focus,
            'last_run_at' => $latestRun?->finished_at,
            'minutes_since_last_run' => $minutesSinceRun,
            'region' => $region,
        ];
    };
    $publishedWindowDays = 120;
    $publishedWindowKey = 'last120';
    $publishedSince = now()->subDays($publishedWindowDays)->toDateString();
    $hasUsableDashboardTitle = function (CountryUpdate $update): bool {
        $title = trim((string) ($update->title_english ?: $update->title ?: $update->title_original));

        if ($title === '' || filter_var($title, FILTER_VALIDATE_URL)) {
            return false;
        }

        $compact = preg_replace('/\s+/', '', $title) ?? $title;

        if (strlen($compact) >= 40
            && $compact === $title
            && preg_match('/^[A-Za-z0-9_-]+$/', $compact) === 1) {
            return false;
        }

        return true;
    };
    $recentPublishedItems = CountryUpdate::query()
        ->with(['country', 'journalistArticles.journalist'])
        ->where('review_status', '!=', 'rejected')
        ->whereNotNull('publication_date')
        ->where('publication_date', '>=', $publishedSince)
        ->get()
        ->map(function (CountryUpdate $update) use ($inferIntelligenceFocus) {
            $update->inferred_focus = $inferIntelligenceFocus($update);

            return $update;
        })
        ->unique(fn (CountryUpdate $update) => filled($update->source_url) ? Str::lower($update->source_url) : 'update:' . $update->id)
        ->filter($hasUsableDashboardTitle)
        ->values();
    $retrievedWindowDays = 7;
    $retrievedWindowKey = 'last7';
    $retrievedSince = now()->subDays($retrievedWindowDays);
    $recentRetrievedItems = CountryUpdate::query()
        ->with(['country', 'journalistArticles.journalist'])
        ->where('review_status', '!=', 'rejected')
        ->whereNotNull('retrieved_at')
        ->where('retrieved_at', '>=', $retrievedSince)
        ->get()
        ->map(function (CountryUpdate $update) use ($inferIntelligenceFocus) {
            $update->inferred_focus = $inferIntelligenceFocus($update);

            return $update;
        })
        ->unique(fn (CountryUpdate $update) => filled($update->source_url) ? Str::lower($update->source_url) : 'update:' . $update->id)
        ->filter($hasUsableDashboardTitle)
        ->values();
    $hasTenderSignal = function (CountryUpdate $update): bool {
        $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->summary . ' ' . $update->source_name . ' ' . $update->source_url);
        if (Str::contains($text, [
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

        $hasProcurementSignal = Str::contains($text, [
            'tender',
            'procurement notice',
            'procurement opportunity',
            'procurement plan',
            'request for proposal',
            'request for proposals',
            'request for expression of interest',
            'request for expressions of interest',
            'expression of interest',
            'request for bids',
            'invitation for bids',
            'invitation to bid',
            'contract notice',
            'bid submission',
            'bidding document',
            'terms of reference',
        ]);
        $hasTargetSignal = Str::contains($text, [
            'social security',
            'social insurance',
            'national insurance',
            'pension administration',
            'pension management information system',
            'pension information system',
            'pension system',
            'provident fund',
            'beneficiary registry',
            'benefit payment system',
            'hrms',
            'hris',
            'hcm',
            'payroll',
            'human capital management',
            'human resource management system',
            'human resources management system',
            'human resource information system',
            'human resources information system',
            'personnel management system',
            'personnel information system',
            'civil service management system',
            'integrated personnel payroll',
            'benefits administration',
            'talent management',
            'performance management',
            'workforce management',
            'risk management software',
            'enterprise risk management',
            'risk management system',
            'erm software',
            'risk register',
            'grc software',
            'compliance management system',
            'audit management system',
            'operational risk management',
            'budgeting software',
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
        ]);

        return $hasProcurementSignal && $hasTargetSignal;
    };
    $hasTenderSignal = fn (CountryUpdate $update): bool => CountryUpdateClassifier::isTender($update);
    $recentSocialSecurityTenders = $recentPublishedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'social_security' && $hasTenderSignal($update));
    $recentHrmsTenders = $recentPublishedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'hrms_tenders' && $hasTenderSignal($update));
    $recentErmsTenders = $recentPublishedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'erms_tenders' && $hasTenderSignal($update));
    $recentEbpcTenders = $recentPublishedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'ebpc_tenders' && $hasTenderSignal($update));
    $recentSocialSecurityNews = $recentPublishedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'social_security' && ! $hasTenderSignal($update));
    $recentRetrievedSocialSecurityTenders = $recentRetrievedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'social_security' && $hasTenderSignal($update));
    $recentRetrievedHrmsTenders = $recentRetrievedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'hrms_tenders' && $hasTenderSignal($update));
    $recentRetrievedErmsTenders = $recentRetrievedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'erms_tenders' && $hasTenderSignal($update));
    $recentRetrievedEbpcTenders = $recentRetrievedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'ebpc_tenders' && $hasTenderSignal($update));
    $recentRetrievedSocialSecurityNews = $recentRetrievedItems
        ->filter(fn (CountryUpdate $update) => $update->inferred_focus === 'social_security' && ! $hasTenderSignal($update));
    $latestCapturedItem = fn ($items) => $items
        ->filter($hasUsableDashboardTitle)
        ->sortByDesc(fn (CountryUpdate $update) => $update->retrieved_at?->timestamp ?? 0)
        ->first();
    $capturedSummary = fn ($tenderItems, string $focus, ?string $newsFocus = null, $newsItems = null) => [
        'window_days' => $retrievedWindowDays,
        'tender_count' => $tenderItems->count(),
        'news_count' => $newsItems?->count(),
        'latest_tender' => $latestCapturedItem($tenderItems),
        'latest_news' => $newsItems ? $latestCapturedItem($newsItems) : null,
        'tender_url' => route('sls.intelligence.review', ['focus' => $focus, 'region' => 'all', 'published' => 'all', 'retrieved' => $retrievedWindowKey, 'type' => 'tenders', 'limit' => 500]),
        'news_url' => $newsFocus ? route('sls.intelligence.review', ['focus' => $newsFocus, 'region' => 'all', 'published' => 'all', 'retrieved' => $retrievedWindowKey, 'type' => 'news', 'limit' => 500]) : null,
    ];
    $latestItem = fn ($items) => $items
        ->filter($hasUsableDashboardTitle)
        ->sortByDesc(fn (CountryUpdate $update) => sprintf(
            '%010d-%010d',
            $update->publication_date?->timestamp ?? 0,
            $update->retrieved_at?->timestamp ?? 0
        ))
        ->first();
    $productActivityTerms = [
        'Interact SSAS' => ['ssas', 'social security', 'pension', 'benefits administration', 'contribution'],
        'Interact HRMS' => ['hrms', 'hris', 'hcm', 'payroll', 'human resource', 'talent management', 'performance management'],
        'Interact ERMS' => ['erms', 'enterprise risk', 'risk management', 'grc', 'compliance', 'audit management'],
        'Interact EBPC' => ['ebpc', 'budgeting', 'budget planning', 'budget control', 'budget execution', 'ifmis', 'fmis', 'public financial management', 'forecasting', 'financial planning'],
    ];
    $latestTaggedActivity = function (string $productName) use ($productActivityTerms) {
        $terms = $productActivityTerms[$productName] ?? [Str::lower($productName)];

        $activity = MarketOrganizationActivity::query()
            ->with('organization')
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('activity_type', 'like', '%' . $term . '%')
                        ->orWhere('subject', 'like', '%' . $term . '%')
                        ->orWhere('body', 'like', '%' . $term . '%');
                }
            })
            ->latest('activity_at')
            ->first();

        $task = MarketOrganizationTask::query()
            ->with('organization')
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('task_type', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('notes', 'like', '%' . $term . '%');
                }
            })
            ->latest('due_at')
            ->first();

        if (! $activity && ! $task) {
            return null;
        }

        if ($activity && (! $task || ($activity->activity_at?->timestamp ?? 0) >= ($task->due_at?->timestamp ?? 0))) {
            return [
                'kind' => 'Activity',
                'title' => $activity->subject ?: ucfirst((string) $activity->activity_type),
                'organization' => $activity->organization?->name,
                'date' => $activity->activity_at,
                'url' => $activity->organization ? route('sls.organizations.show', $activity->organization) : null,
            ];
        }

        return [
            'kind' => 'Task',
            'title' => $task->title ?: ucfirst((string) $task->task_type),
            'organization' => $task->organization?->name,
            'date' => $task->due_at,
            'url' => $task->organization ? route('sls.organizations.show', $task->organization) : null,
        ];
    };
    $productCards = collect([
        [
            'name' => 'Interact SSAS',
            'focus' => 'social_security',
            'latest_tender' => $latestItem($recentSocialSecurityTenders),
            'secondary_label' => 'Latest relevant news',
            'secondary_item' => $latestItem($recentSocialSecurityNews),
            'secondary_type' => 'news',
            'captured' => $capturedSummary($recentRetrievedSocialSecurityTenders, 'social_security', 'social_security', $recentRetrievedSocialSecurityNews),
        ],
        [
            'name' => 'Interact HRMS',
            'focus' => 'hrms_tenders',
            'latest_tender' => $latestItem($recentHrmsTenders),
            'secondary_label' => 'Most recent tagged activity',
            'secondary_item' => $latestTaggedActivity('Interact HRMS'),
            'secondary_type' => 'activity',
            'captured' => $capturedSummary($recentRetrievedHrmsTenders, 'hrms_tenders'),
        ],
        [
            'name' => 'Interact ERMS',
            'focus' => 'erms_tenders',
            'latest_tender' => $latestItem($recentErmsTenders),
            'secondary_label' => 'Most recent tagged activity',
            'secondary_item' => $latestTaggedActivity('Interact ERMS'),
            'secondary_type' => 'activity',
            'captured' => $capturedSummary($recentRetrievedErmsTenders, 'erms_tenders'),
        ],
        [
            'name' => 'Interact EBPC',
            'focus' => 'ebpc_tenders',
            'latest_tender' => $latestItem($recentEbpcTenders),
            'secondary_label' => 'Most recent tagged activity',
            'secondary_item' => $latestTaggedActivity('Interact EBPC'),
            'secondary_type' => 'activity',
            'captured' => $capturedSummary($recentRetrievedEbpcTenders, 'ebpc_tenders'),
        ],
    ]);
    $regionSlug = fn (string $region) => match (Str::lower($region)) {
        'latin america' => 'latin_america',
        default => Str::of($region)->lower()->replace(' ', '_')->toString(),
    };
    $regionNames = collect(['Africa', 'Asia', 'Latin America', 'Caribbean', 'North America', 'Europe'])
        ->merge(Country::query()->whereNotNull('region')->distinct()->pluck('region'))
        ->filter()
        ->unique()
        ->values();
    $regionTotals = $regionNames
        ->map(function (string $region) use ($recentSocialSecurityTenders, $recentHrmsTenders, $recentErmsTenders, $recentEbpcTenders, $recentSocialSecurityNews, $regionSlug, $publishedWindowKey) {
            $matchesRegion = fn (CountryUpdate $update) => Str::lower((string) $update->country?->region) === Str::lower($region);
            $slug = $regionSlug($region);

            return [
                'name' => $region,
                'slug' => $slug,
                'social_security_tenders' => $recentSocialSecurityTenders->filter($matchesRegion)->count(),
                'hrms_tenders' => $recentHrmsTenders->filter($matchesRegion)->count(),
                'erms_tenders' => $recentErmsTenders->filter($matchesRegion)->count(),
                'ebpc_tenders' => $recentEbpcTenders->filter($matchesRegion)->count(),
                'social_security_news' => $recentSocialSecurityNews->filter($matchesRegion)->count(),
                'links' => [
                    'social_security_tenders' => route('sls.intelligence.review', ['focus' => 'social_security', 'region' => $slug, 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                    'hrms_tenders' => route('sls.intelligence.review', ['focus' => 'hrms_tenders', 'region' => $slug, 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                    'erms_tenders' => route('sls.intelligence.review', ['focus' => 'erms_tenders', 'region' => $slug, 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                    'ebpc_tenders' => route('sls.intelligence.review', ['focus' => 'ebpc_tenders', 'region' => $slug, 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                    'social_security_news' => route('sls.intelligence.review', ['focus' => 'social_security', 'region' => $slug, 'published' => $publishedWindowKey, 'type' => 'news', 'limit' => 500]),
                ],
            ];
        })
        ->values();

    $dashboardProducts = $orderedProducts();
    $defaultDashboardProduct = $dashboardProducts
        ->first(fn (Product $product) => $product->name === 'Interact SSAS' || $product->code === 'SSAS')
        ?: $dashboardProducts->first();
    $normaliseAdminName = fn (string $name): string => Str::lower(Str::ascii(trim($name)));
    $reviewedAdminNameReplacements = [
        'BO' => [
            'de la Seguridad Social de Largo Plazo' => 'Gestora PÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica de la Seguridad Social de Largo Plazo',
        ],
        'CA' => [
            'WorkplaceNL' => 'Workplace Health, Safety and Compensation Commission of Newfoundland and Labrador (WorkplaceNL)',
        ],
        'DK' => [
            'Payment' => 'Payment Denmark',
        ],
        'VG' => [
            'Social Security Board' => 'BVI Social Security Board',
        ],
        'SV' => [
            'Salvadorian Social Insurance Institute' => 'Instituto Salvadoreno del Seguro Social',
        ],
        'UZ' => [
            'National Federation of Trade Unions)' => 'National Federation of Trade Unions',
        ],
    ];
    $reviewedAdminNameReplacements = collect($reviewedAdminNameReplacements)
        ->map(fn (array $replacements) => collect($replacements)
            ->mapWithKeys(fn (string $replacement, string $original) => [
                SocialSecurityAdminNameCleaner::repairMojibake($original) => SocialSecurityAdminNameCleaner::repairMojibake($replacement),
            ])
            ->all())
        ->all();
    $reviewedAdminDuplicateNames = [
        'AO' => ['National Institute of Social Security'],
        'BS' => ['National Insurance Board of The'],
        'BB' => ['National Insurance Office'],
        'BR' => ['INSS'],
        'CL' => ['Social Security Institute'],
        'CR' => ['Costa Rican Social Insurance Fund'],
        'CY' => ['Welfare Benefits Administration Service'],
        'EC' => ['Ecuadorian Social Security Institute'],
        'ET' => ['Public Servants Social Security Service'],
        'GE' => ['Social Service Agency'],
        'GT' => ['Social Security Institute'],
        'HN' => ['Social Security Institute'],
        'LK' => ['Ministry of Labour'],
        'LT' => ['Ministry of Health of the Republic'],
        'ME' => ['Insurance'],
        'MX' => ['Mexican Social Security Institute'],
        'MZ' => ['National Institute of Social Security'],
        'PA' => ['Social Insurance Fund', 'Caja de Seguro Social PanamÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡'],
        'PY' => ['Social Insurance Institute'],
        'PE' => ['Social Security Normalization', 'ONP'],
        'KR' => ['National Pension Service Korea'],
        'ZA' => ['South African Social Security Agency'],
        'SI' => ['Ministry of Labor, Family, Social Affairs, and Equal Opportunities'],
        'TW' => ['Department of Labor Insurance of the Ministry of Labor'],
        'UY' => ['National Insurance Bank'],
        'VE' => ['Venezuelan Social Insurance Institute'],
        'VN' => ['Social Security'],
        'AR' => ['ANSES'],
    ];
    $reviewedAdminDuplicateNames = collect($reviewedAdminDuplicateNames)
        ->map(fn (array $names) => collect($names)
            ->map(fn (string $name) => SocialSecurityAdminNameCleaner::repairMojibake($name))
            ->all())
        ->all();
    $reviewedAdminInvalidNames = [
        'BO' => ['Gestora PÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica'],
        'CV' => ['National Health Service Ministry of Family, Inclusion and Social Development'],
        'JP' => ['National Pension Programme, Employees\' Pension Insurance Programme Japan Pension Service'],
        'MX' => ['MÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©xico'],
        'UZ' => ['Ministry of Economy and Finance. Citizens\' Commissions'],
    ];
    $reviewedAdminInvalidNames = collect($reviewedAdminInvalidNames)
        ->map(fn (array $names) => collect($names)
            ->map(fn (string $name) => SocialSecurityAdminNameCleaner::repairMojibake($name))
            ->all())
        ->all();
    $reviewedAdminUrlOverrides = [
        'DZ' => [
            'Ministry of Labour, Employment and Social Security' => ['https://www.mtess.gov.dz/'],
        ],
    ];
    $extractUrlsFromText = function (?string $text): array {
        preg_match_all('/\bhttps?:\/\/[^\s<>"\']+/i', (string) $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => trim($url, " \t\n\r\0\x0B.,;:)]}>\"'"))
            ->filter()
            ->unique()
            ->values()
            ->all();
    };
    $decodePdfLiteralString = function (string $value): string {
        $value = preg_replace('/\\\\\r?\n/', '', $value) ?? $value;
        $value = preg_replace_callback(
            '/\\\\([0-7]{1,3})/',
            fn (array $match): string => chr(octdec($match[1])),
            $value
        ) ?? $value;

        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ]);
    };
    $decodePdfHexString = function (string $value): string {
        $hex = preg_replace('/\s+/', '', $value) ?? '';

        if ($hex === '') {
            return '';
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return hex2bin($hex) ?: '';
    };
    $extractPdfUriTargets = function (?string $pdfBody) use ($decodePdfLiteralString, $decodePdfHexString, $extractUrlsFromText): array {
        $pdfBody = (string) $pdfBody;
        $urls = collect();

        preg_match_all('/\/URI\s*\(((?:\\\\.|[^\\\\)])*)\)/s', $pdfBody, $literalMatches);
        foreach ($literalMatches[1] ?? [] as $rawUri) {
            $urls = $urls->merge($extractUrlsFromText($decodePdfLiteralString($rawUri)));
        }

        preg_match_all('/\/URI\s*<([0-9A-Fa-f\s]+)>/s', $pdfBody, $hexMatches);
        foreach ($hexMatches[1] ?? [] as $rawUri) {
            $urls = $urls->merge($extractUrlsFromText($decodePdfHexString($rawUri)));
        }

        return $urls->unique()->values()->all();
    };
    $resolveStoredDocumentPath = function (?string $storagePath): ?string {
        $storagePath = trim((string) $storagePath);

        if ($storagePath === '') {
            return null;
        }

        foreach ([Storage::path($storagePath), storage_path('app' . DIRECTORY_SEPARATOR . $storagePath)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    };
    $hyperlinkedDocumentUrlCache = [];
    $hyperlinkedDocumentUrls = function (?SourceDocument $sourceDocument) use (&$hyperlinkedDocumentUrlCache, $resolveStoredDocumentPath, $extractPdfUriTargets): array {
        if (! $sourceDocument) {
            return [];
        }

        $documentId = (int) $sourceDocument->id;

        if (array_key_exists($documentId, $hyperlinkedDocumentUrlCache)) {
            return $hyperlinkedDocumentUrlCache[$documentId];
        }

        $path = $resolveStoredDocumentPath($sourceDocument->storage_path);

        if ($path === null || Str::lower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return $hyperlinkedDocumentUrlCache[$documentId] = [];
        }

        return $hyperlinkedDocumentUrlCache[$documentId] = $extractPdfUriTargets((string) @file_get_contents($path));
    };
    $preferredAdminUrl = function (array $urls): ?string {
        $ranked = collect($urls)
            ->map(fn (string $url) => trim($url))
            ->filter()
            ->unique()
            ->sortBy(function (string $url): int {
                $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
                $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
                $haystack = $host . ' ' . $path;
                $lawDomains = ['ilo.org', 'natlex', 'legislation.', 'laws.gov', 'austlii', 'paclii', 'pravo.', 'gazette', 'parliament'];

                if (Str::endsWith($path, '.pdf')) {
                    return 40;
                }

                if (Str::contains($haystack, $lawDomains)) {
                    return 30;
                }

                if (Str::contains($haystack, ['gov', 'gob', 'gouv', 'go.', 'org', 'fund', 'pension', 'social', 'insurance', 'isss', 'ssb', 'inss'])) {
                    return 5;
                }

                return 10;
            })
            ->values();

        return $ranked->first();
    };
    $adminUrlMatchesName = function (string $name, string $url): bool {
        $nameText = Str::lower(Str::ascii($name));
        $host = Str::lower(Str::ascii((string) parse_url($url, PHP_URL_HOST)));
        $path = Str::lower(Str::ascii((string) parse_url($url, PHP_URL_PATH)));
        $urlText = preg_replace('/[^a-z0-9]+/', ' ', $host . ' ' . $path) ?? '';
        $urlCompact = preg_replace('/[^a-z0-9]+/', '', $host . ' ' . $path) ?? '';
        $words = collect(preg_split('/[^a-z0-9]+/', $nameText) ?: [])
            ->filter()
            ->reject(fn (string $word) => in_array($word, [
                'and', 'de', 'del', 'des', 'do', 'du', 'for', 'la', 'le', 'les', 'of', 'the', 'y',
            ], true))
            ->values();
        $acronym = $words
            ->map(fn (string $word): string => substr($word, 0, 1))
            ->implode('');

        if (strlen($acronym) >= 3 && Str::contains($urlCompact, $acronym)) {
            return true;
        }

        $genericWords = [
            'administration', 'agency', 'authority', 'board', 'caisse', 'commission', 'department',
            'fund', 'institute', 'institution', 'insurance', 'ministry', 'national', 'office',
            'pension', 'security', 'service', 'social',
        ];

        return $words
            ->reject(fn (string $word) => strlen($word) < 6 || in_array($word, $genericWords, true))
            ->contains(fn (string $word) => Str::contains($urlText, $word) || Str::contains($urlCompact, $word));
    };
    $addAdminUrl = function (array &$urlIndex, string $iso, string $name, array $urls, array $aliases = []) use ($normaliseAdminName, $preferredAdminUrl): void {
        $url = $preferredAdminUrl($urls);

        if (! $url) {
            return;
        }

        foreach (collect([$name])->merge($aliases)->filter()->unique() as $indexName) {
            $urlIndex[strtoupper($iso)][$normaliseAdminName((string) $indexName)][] = $url;
        }
    };
    $manualAdminUrlFields = fn (?MarketOrganization $organization): array => [
        'general_url' => $organization?->website_url,
        'press_url' => $organization?->news_page_url,
        'tenders_url' => $organization?->procurement_page_url,
        'product_id' => $organization?->product_id,
    ];
    $addAdminUrlDetails = function (array &$detailsIndex, string $iso, string $name, array $details, array $aliases = []) use ($normaliseAdminName): void {
        $details = collect([
            'general_url' => $details['general_url'] ?? null,
            'press_url' => $details['press_url'] ?? null,
            'tenders_url' => $details['tenders_url'] ?? null,
            'product_id' => $details['product_id'] ?? null,
        ])
            ->map(fn ($value) => filled($value) ? trim((string) $value) : null)
            ->filter()
            ->all();

        if ($details === []) {
            return;
        }

        foreach (collect([$name])->merge($aliases)->filter()->unique() as $indexName) {
            $key = $normaliseAdminName((string) $indexName);
            $existing = $detailsIndex[strtoupper($iso)][$key] ?? [];
            $detailsIndex[strtoupper($iso)][$key] = array_filter($details + $existing);
        }
    };

    $databaseCountries = Country::with('topics')->get()->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
    $sourceAdminRecords = IntelligenceSource::query()
        ->where('source_class', 'social_security_admin')
        ->get()
        ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso));
    $sourceAdminNames = $sourceAdminRecords
        ->map(fn ($sources) => $sources->pluck('name')->filter()->unique()->values());
    $adminCandidates = SocialSecurityAdminCandidate::query()
        ->with(['sourceDocument', 'marketOrganization'])
        ->where('status', '<>', 'rejected')
        ->whereNotNull('country_iso')
        ->whereNotNull('organization_name')
        ->orderBy('organization_name')
        ->get();
    $adminCandidateGroups = $adminCandidates->groupBy(fn (SocialSecurityAdminCandidate $candidate) => strtoupper((string) $candidate->country_iso));
    $manualAdminOrganizations = MarketOrganization::query()
        ->where('organization_subcategory', 'social_security_administration')
        ->whereNotNull('country_iso')
        ->get()
        ->groupBy(fn (MarketOrganization $organization) => strtoupper((string) $organization->country_iso));
    $manualAdminNameSuppressions = Schema::hasTable('social_security_admin_name_suppressions')
        ? DB::table('social_security_admin_name_suppressions')
            ->get(['country_iso', 'name_normalized'])
            ->groupBy(fn ($suppression) => strtoupper((string) $suppression->country_iso))
            ->map(fn ($suppressions) => $suppressions->pluck('name_normalized')->filter()->values())
        : collect();
    $extractedAdminNames = $adminCandidateGroups
        ->map(fn ($candidates) => $candidates->pluck('organization_name')->filter()->unique()->values());
    $adminUrlIndex = [];
    $adminUrlDetailsIndex = [];
    $trackedCountries = collect(config('country_intelligence.monitored_countries', []))
        ->map(function (array $countryConfig, string $iso) use (
            $addAdminUrl,
            $addAdminUrlDetails,
            $adminUrlMatchesName,
            $adminCandidateGroups,
            &$adminUrlDetailsIndex,
            &$adminUrlIndex,
            $databaseCountries,
            $defaultDashboardProduct,
            $hyperlinkedDocumentUrls,
            $manualAdminOrganizations,
            $manualAdminNameSuppressions,
            $manualAdminUrlFields,
            $normaliseAdminName,
            $preferredAdminUrl,
            $reviewedAdminDuplicateNames,
            $reviewedAdminInvalidNames,
            $reviewedAdminNameReplacements,
            $reviewedAdminUrlOverrides,
            $sourceAdminNames,
            $sourceAdminRecords,
            $extractedAdminNames
        ) {
            $iso = strtoupper((string) ($countryConfig['iso_code'] ?? $iso));
            $databaseCountry = $databaseCountries->get($iso);
            $adminNames = $extractedAdminNames->get($iso, collect());
            $previousAdminNames = collect([
                $databaseCountry?->social_security_administration_name,
                $countryConfig['social_security_administration_name'] ?? null,
            ])
                ->merge($sourceAdminNames->get($iso, collect()))
                ->filter()
                ->flatMap(fn (string $name) => preg_split('/\s+\/\s+/', $name) ?: [])
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->values();
            $countryName = $databaseCountry?->name ?? $countryConfig['name'] ?? $iso;
            $adminNames = SocialSecurityAdminNameCleaner::canonicalizeList(
                $adminNames->merge($previousAdminNames),
                $countryName,
                $iso,
                $countryConfig['search_names'] ?? [],
            );
            $adminNames = $adminNames
                ->map(fn (string $name) => $reviewedAdminNameReplacements[$iso][$name] ?? $name)
                ->reject(function (string $name) use ($iso, $manualAdminNameSuppressions, $normaliseAdminName, $reviewedAdminDuplicateNames, $reviewedAdminInvalidNames) {
                    $normaliseSuppressionName = fn (string $value): string => Str::of(Str::ascii($value))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
                    $reviewedRemovals = collect($reviewedAdminDuplicateNames[$iso] ?? [])
                        ->merge($reviewedAdminInvalidNames[$iso] ?? [])
                        ->merge($manualAdminNameSuppressions->get($iso, collect()))
                        ->flatMap(fn (string $removedName) => [
                            $normaliseAdminName($removedName),
                            $normaliseSuppressionName($removedName),
                        ])
                        ->all();

                    return in_array($normaliseAdminName($name), $reviewedRemovals, true)
                        || in_array($normaliseSuppressionName($name), $reviewedRemovals, true);
                })
                ->unique(fn (string $name) => $normaliseAdminName($name))
                ->values();

            foreach ($sourceAdminRecords->get($iso, collect()) as $source) {
                $addAdminUrl($adminUrlIndex, $iso, (string) $source->name, [(string) $source->url]);
            }

            foreach ($reviewedAdminUrlOverrides[$iso] ?? [] as $overrideName => $overrideUrls) {
                $addAdminUrl($adminUrlIndex, $iso, (string) $overrideName, (array) $overrideUrls);
            }

            foreach ($manualAdminOrganizations->get($iso, collect()) as $organization) {
                $addAdminUrlDetails($adminUrlDetailsIndex, $iso, (string) $organization->name, $manualAdminUrlFields($organization));
                $addAdminUrl($adminUrlIndex, $iso, (string) $organization->name, array_filter([$organization->website_url]));
            }

            foreach ($adminCandidateGroups->get($iso, collect()) as $candidate) {
                $candidateName = (string) $candidate->organization_name;
                $aliases = SocialSecurityAdminNameCleaner::canonicalizeList(
                    collect([$candidateName]),
                    $countryName,
                    $iso,
                    $countryConfig['search_names'] ?? [],
                )
                    ->merge([$reviewedAdminNameReplacements[$iso][$candidateName] ?? null])
                    ->filter()
                    ->unique(fn (string $name) => $normaliseAdminName($name))
                    ->values()
                    ->all();
                $urls = collect([
                    $candidate->marketOrganization?->website_url,
                ])
                    ->merge(collect([$candidate->sourceDocument?->source_url])
                        ->filter(fn (?string $url) => $url && $adminUrlMatchesName($candidateName, $url)))
                    ->merge(collect($hyperlinkedDocumentUrls($candidate->sourceDocument))
                        ->filter(fn (string $url) => $adminUrlMatchesName($candidateName, $url)))
                    ->filter()
                    ->values()
                    ->all();

                $addAdminUrl($adminUrlIndex, $iso, $candidateName, $urls, $aliases);
                $addAdminUrlDetails($adminUrlDetailsIndex, $iso, $candidateName, $manualAdminUrlFields($candidate->marketOrganization), $aliases);
            }
            $adminLinks = $adminNames
                ->map(function (string $name) use (&$adminUrlDetailsIndex, &$adminUrlIndex, $defaultDashboardProduct, $iso, $normaliseAdminName, $preferredAdminUrl) {
                    $urls = $adminUrlIndex[$iso][$normaliseAdminName($name)] ?? [];
                    $manualUrls = $adminUrlDetailsIndex[$iso][$normaliseAdminName($name)] ?? [];
                    $generalUrl = $manualUrls['general_url'] ?? $preferredAdminUrl($urls);

                    return [
                        'name' => SocialSecurityAdminNameCleaner::repairMojibake($name),
                        'url' => $generalUrl,
                        'general_url' => $generalUrl,
                        'press_url' => $manualUrls['press_url'] ?? null,
                        'tenders_url' => $manualUrls['tenders_url'] ?? null,
                        'product_id' => $manualUrls['product_id'] ?? $defaultDashboardProduct?->id,
                    ];
                })
                ->values();

            return (object) [
                'name' => $countryName,
                'iso_code' => $iso,
                'region' => $databaseCountry?->region ?? $countryConfig['region'] ?? 'Not assigned',
                'default_language_code' => $databaseCountry?->default_language_code ?? $countryConfig['default_language_code'] ?? 'en',
                'social_security_administration_name' => $adminNames->implode(' / '),
                'social_security_administration_names' => $adminNames,
                'social_security_administration_links' => $adminLinks,
                'social_protection_profile_url' => $databaseCountry?->social_protection_profile_url
                    ?: 'https://www.social-protection.org/gimi/gess/ShowCountryProfile.action?iso=' . $iso,
                'social_protection_profile_checked_at' => $databaseCountry?->social_protection_profile_checked_at,
                'social_protection_profile_last_error' => $databaseCountry?->social_protection_profile_last_error,
                'topics' => $databaseCountry?->topics ?? collect(),
            ];
        })
        ->sortBy([['region', 'asc'], ['name', 'asc']])
        ->values();
    $trackedRegions = $trackedCountries->pluck('region')->filter()->unique()->sort()->values();
    $trackedLanguages = $trackedCountries->pluck('default_language_code')->filter()->map(fn ($code) => strtoupper($code))->unique()->sort()->values();
    $dashboardMapCountries = $relevantCountryUpdates($allMapCountries(), 'social_security', $publishedSince);
    $backupRoot = PHP_OS_FAMILY === 'Windows'
        ? 'C:\\Users\\marcv\\Documents\\1G-SLS-Backups'
        : storage_path('app/backups');
    $latestBackup = null;

    if (is_dir($backupRoot)) {
        $backupFolders = collect(glob($backupRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [])
            ->map(fn (string $path) => [
                'name' => basename($path),
                'path' => $path,
                'updated_at' => filemtime($path) ?: null,
            ])
            ->filter(fn (array $backup) => $backup['updated_at'] !== null)
            ->sortByDesc('updated_at')
            ->values();

        if ($backupFolders->isNotEmpty()) {
            $latest = $backupFolders->first();
            $latestBackup = [
                'name' => $latest['name'],
                'path' => $latest['path'],
                'updated_at' => Carbon::createFromTimestamp($latest['updated_at']),
            ];
        }
    }

    return view('sls.dashboard', [
        'products' => $dashboardProducts,
        'productIntelligenceCards' => $productCards,
        'countries' => $trackedCountries,
        'trackedRegions' => $trackedRegions,
        'trackedLanguages' => $trackedLanguages,
        'dashboardMapCountries' => $dashboardMapCountries,
        'dashboardMapUpdateCount' => $dashboardMapCountries->sum('update_count'),
        'dashboardMapMonitoredCount' => $dashboardMapCountries->filter(fn (array $country) => $country['database_id'] !== null)->count(),
        'sourceDocumentCount' => SourceDocument::count(),
        'knowledgeChunkCount' => KnowledgeChunk::count(),
        'latestBackup' => $latestBackup,
        'backupRoot' => $backupRoot,
        'recentIntelligenceStats' => [
            'published_since' => $publishedSince,
            'published_window_days' => $publishedWindowDays,
            'links' => [
                'social_security_tenders' => route('sls.intelligence.review', ['focus' => 'social_security', 'region' => 'all', 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                'hrms_tenders' => route('sls.intelligence.review', ['focus' => 'hrms_tenders', 'region' => 'all', 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                'erms_tenders' => route('sls.intelligence.review', ['focus' => 'erms_tenders', 'region' => 'all', 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                'ebpc_tenders' => route('sls.intelligence.review', ['focus' => 'ebpc_tenders', 'region' => 'all', 'published' => $publishedWindowKey, 'type' => 'tenders', 'limit' => 500]),
                'social_security_news' => route('sls.intelligence.review', ['focus' => 'social_security', 'region' => 'all', 'published' => $publishedWindowKey, 'type' => 'news', 'limit' => 500]),
            ],
            'social_security_tenders' => $recentSocialSecurityTenders->count(),
            'hrms_tenders' => $recentHrmsTenders->count(),
            'erms_tenders' => $recentErmsTenders->count(),
            'ebpc_tenders' => $recentEbpcTenders->count(),
            'social_security_news' => $recentSocialSecurityNews->count(),
            'regions' => $regionTotals,
        ],
        'intelligenceMonitorHealths' => [
            array_merge(
                $monitorHealth('social_security', 'Social Security and Pension News', 'africa_asia_caribbean_latin_america_north_america_europe'),
                ['run_key' => 'social-security-news']
            ),
            array_merge(
                $monitorHealth(['hrms_tenders', 'sector_tenders', 'erms_tenders', 'ebpc_tenders'], 'Tender Monitor', 'africa_asia_caribbean_latin_america_north_america_europe'),
                ['run_key' => 'tenders']
            ),
        ],
    ]);
});

Route::post('/sls/system/scheduler/restart', function () {
    $script = base_path('scripts/restart-windows-scheduler-loop.ps1');

    if (! is_file($script)) {
        return back()->with('error', 'Scheduler restart script was not found.');
    }

    $process = new Process([
        'powershell.exe',
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        $script,
    ], base_path());
    $process->setTimeout(20);
    $process->run();

    if (! $process->isSuccessful()) {
        return back()->with('error', 'Scheduler restart failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    return back()->with('status', 'Laravel scheduler loop restarted. The monitors will continue on their normal schedule.');
})->name('sls.system.scheduler.restart');

Route::post('/sls/intelligence/monitors/{monitor}/run-now', function (string $monitor, CountryIntelligenceMonitor $countryMonitor) {
    $monitorConfigs = [
        'social-security-news' => [
            'label' => 'Social Security and Pension News',
            'focuses' => ['social_security'],
            'region' => 'africa_asia_caribbean_latin_america_north_america_europe',
        ],
        'tenders' => [
            'label' => 'Tender Monitor',
            'focuses' => ['hrms_tenders', 'sector_tenders', 'erms_tenders', 'ebpc_tenders'],
            'region' => 'africa_asia_caribbean_latin_america_north_america_europe',
        ],
    ];

    if (! array_key_exists($monitor, $monitorConfigs)) {
        abort(404);
    }

    $config = $monitorConfigs[$monitor];
    $maxResults = (int) config('country_intelligence.scheduled_max_results', config('country_intelligence.default_max_results', 3));
    $ran = collect();

    foreach ($config['focuses'] as $focus) {
        $results = $countryMonitor->run(
            countryKeys: [],
            maxResults: max(1, min(10, $maxResults)),
            dryRun: false,
            cycleSize: 1,
            focus: $focus,
            region: $config['region'],
        );

        $ran = $ran->merge(collect($results)->map(fn (array $result, string $iso) => [
            'iso' => $iso,
            'country' => $result['country'] ?? $iso,
            'items_found' => (int) ($result['items_found'] ?? 0),
            'focus' => $focus,
        ]));
    }

    $summary = $ran
        ->map(fn (array $item) => $item['country'] . ' (' . $item['focus'] . ': ' . $item['items_found'] . ')')
        ->implode(', ');

    return back()->with('status', $config['label'] . ' was run now' . ($summary ? ': ' . $summary : '.'));
})->name('sls.intelligence.monitors.runNow');

Route::get('/sls/tracked-countries/export', function () {
    $databaseCountries = Country::with('topics')->get()->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
    $adminSources = IntelligenceSource::query()
        ->where('source_class', 'social_security_admin')
        ->orderBy('name')
        ->get()
        ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso));
    $extractedAdminNames = SocialSecurityAdminCandidate::query()
        ->where('status', '<>', 'rejected')
        ->whereNotNull('country_iso')
        ->whereNotNull('organization_name')
        ->orderBy('organization_name')
        ->get()
        ->groupBy(fn (SocialSecurityAdminCandidate $candidate) => strtoupper((string) $candidate->country_iso))
        ->map(fn ($candidates) => $candidates->pluck('organization_name')->filter()->unique()->values());

    $rows = collect(config('country_intelligence.monitored_countries', []))
        ->map(function (array $countryConfig, string $iso) use ($databaseCountries, $adminSources, $extractedAdminNames) {
            $iso = strtoupper((string) ($countryConfig['iso_code'] ?? $iso));
            $databaseCountry = $databaseCountries->get($iso);
            $sources = $adminSources->get($iso, collect());
            $sourceNames = $sources->pluck('name')->filter()->unique()->values();
            $sourceUrls = $sources->pluck('url')->filter()->unique()->implode(' / ');
            $adminNames = $extractedAdminNames->get($iso, collect());
            $previousAdminNames = collect([
                $databaseCountry?->social_security_administration_name,
                $countryConfig['social_security_administration_name'] ?? null,
            ])
                ->merge($sourceNames)
                ->filter()
                ->flatMap(fn (string $name) => preg_split('/\s+\/\s+/', $name) ?: [])
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->values();
            $countryName = $databaseCountry?->name ?? $countryConfig['name'] ?? $iso;
            $adminNames = SocialSecurityAdminNameCleaner::canonicalizeList(
                $adminNames->merge($previousAdminNames),
                $countryName,
                $iso,
                $countryConfig['search_names'] ?? [],
            );

            $adminName = $adminNames->implode(' / ');

            return [
                'country' => $countryName,
                'iso_code' => $iso,
                'region' => $databaseCountry?->region ?? $countryConfig['region'] ?? '',
                'default_language_code' => $databaseCountry?->default_language_code ?? $countryConfig['default_language_code'] ?? '',
                'social_security_administration_name' => $adminName,
                'social_protection_profile_url' => $databaseCountry?->social_protection_profile_url
                    ?: 'https://www.social-protection.org/gimi/gess/ShowCountryProfile.action?iso=' . $iso,
                'social_protection_profile_checked_at' => $databaseCountry?->social_protection_profile_checked_at?->toDateTimeString() ?? '',
                'social_protection_profile_last_error' => $databaseCountry?->social_protection_profile_last_error ?? '',
                'official_source_names' => $sourceNames->implode(' / '),
                'official_source_urls' => $sourceUrls,
                'topics' => $databaseCountry?->topics?->pluck('topic')->implode(' | ') ?? '',
                'needs_official_source' => filled($adminName) ? 'No' : 'Yes',
            ];
        })
        ->sortBy([['region', 'asc'], ['country', 'asc']])
        ->values();

    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, [
        'country',
        'iso_code',
        'region',
        'default_language_code',
        'social_security_administration_name',
        'social_protection_profile_url',
        'social_protection_profile_checked_at',
        'social_protection_profile_last_error',
        'official_source_names',
        'official_source_urls',
        'topics',
        'needs_official_source',
    ]);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return response($csv, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="1g-sls-tracked-countries-official-sources.csv"',
        'Content-Length' => (string) strlen($csv),
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
})->name('sls.trackedCountries.export');

Route::post('/sls/system/backup', function () {
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $scriptPath = base_path($isWindows ? 'scripts/backup-1g-sls.ps1' : 'scripts/backup-1g-sls.sh');

    abort_unless(is_file($scriptPath), 404, 'Backup script was not found.');

    $command = $isWindows
        ? ['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $scriptPath]
        : ['bash', $scriptPath];

    $process = new Process($command, base_path());
    $process->setTimeout(900);
    $process->run();

    if (! $process->isSuccessful()) {
        return back()->with('status', 'Backup failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    $output = trim($process->getOutput());
    $backupFolder = null;

    foreach (preg_split('/\R/', $output) as $line) {
        if (Str::startsWith($line, 'Backup folder:')) {
            $backupFolder = trim(Str::after($line, 'Backup folder:'));
            break;
        }
    }

    return back()->with('status', 'Backup completed' . ($backupFolder ? ': ' . $backupFolder : '.') );
})->name('sls.system.backup');

$bankDomainOperationState = function (array $lastInput = [], ?array $lastResult = null) {
    $eligibleBase = function () {
        return MarketOrganization::query()
            ->where('organization_type', 'financial_institution')
            ->where('industry', 'banking_finance')
            ->where('status', '<>', 'sanctioned')
            ->where(function ($query) {
                $query->whereNull('website_url')->orWhere('website_url', '');
            })
            ->where(function ($query) {
                $query->whereNull('website_domain')->orWhere('website_domain', '');
            });
    };

    $regions = $eligibleBase()
        ->select('region', DB::raw('count(*) as missing_count'))
        ->whereNotNull('region')
        ->where('region', '<>', '')
        ->groupBy('region')
        ->orderBy('region')
        ->get();

    $countries = $eligibleBase()
        ->select('country_iso', 'country', DB::raw('count(*) as missing_count'))
        ->where(function ($query) {
            $query->whereNotNull('country_iso')->orWhereNotNull('country');
        })
        ->groupBy('country_iso', 'country')
        ->orderBy('country')
        ->get()
        ->map(function ($row) {
            $iso = Str::upper((string) ($row->country_iso ?: $row->country));
            $country = SocialSecurityAdminNameCleaner::repairMojibake(trim((string) ($row->country ?: $iso)));

            return [
                'value' => $iso,
                'label' => $country . ($row->country_iso ? ' (' . Str::upper((string) $row->country_iso) . ')' : ''),
                'missing_count' => (int) $row->missing_count,
            ];
        });

    return view('sls.operations.index', [
        'operationStats' => [
            'eligible_banks' => $eligibleBase()->count(),
            'regions' => $regions->count(),
            'countries' => $countries->count(),
        ],
        'regions' => $regions,
        'countries' => $countries,
        'lastInput' => $lastInput,
        'lastResult' => $lastResult,
    ]);
};

Route::get('/sls/operations', function () use ($bankDomainOperationState) {
    return $bankDomainOperationState();
})->name('sls.operations.index');

$startOperationRun = function (SlsOperationRun $run): void {
    $localPhp = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
    $php = is_file($localPhp) ? $localPhp : (PHP_BINARY ?: 'php');
    $artisan = base_path('artisan');
    $log = storage_path('logs/sls-operation-run-' . $run->id . '.log');
    $errorLog = storage_path('logs/sls-operation-run-' . $run->id . '-error.log');
    $arguments = [
        $artisan,
        'sls:run-operation',
        (string) $run->id,
    ];

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd.exe /C start "" /B '
            . escapeshellarg($php) . ' '
            . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log)
            . ' 2> ' . escapeshellarg($errorLog);
        pclose(popen($command, 'r'));

        return;
    }

    $command = escapeshellarg($php) . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
        . ' > ' . escapeshellarg($log)
        . ' 2> ' . escapeshellarg($errorLog)
        . ' &';
    pclose(popen($command, 'r'));
};

$serpApiSearchState = function () {
    $defaultSerpApiKeywords = [
        'payroll',
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

    try {
        $templatesTableReady = Schema::hasTable('serpapi_search_templates');
    } catch (Throwable $exception) {
        $templatesTableReady = false;
    }

    if (! $templatesTableReady) {
        return view('sls.serpapi-searches.index', [
            'templates' => collect(),
            'countries' => collect(),
            'regions' => collect(),
            'languageOptions' => [],
            'monthlyStats' => collect(),
            'recentRuns' => collect(),
            'migrationMissing' => true,
            'setupError' => 'SerpAPI search templates are not ready yet. Run migrations and clear cache.',
        ]);
    }

    try {
        if (SerpApiSearchTemplate::query()->count() === 0) {
            SerpApiSearchTemplate::query()->create([
                'name' => 'HR, payroll, and HCM software tenders',
                'focus' => 'hrms_tenders',
                'query_template' => '"{country}" ({keywords}) (tender OR RFP OR procurement OR "expression of interest")',
                'keywords' => $defaultSerpApiKeywords,
                'results_per_country' => 10,
                'is_enabled' => true,
            ]);
        }

        $countries = Country::query()
            ->whereNotNull('iso_code')
            ->orderBy('region')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'name' => $country->name,
                'iso_code' => strtoupper((string) $country->iso_code),
                'region' => $country->region ?: 'Unassigned',
                'language' => strtolower((string) ($country->default_language_code ?: '')),
            ]);

        $recentRuns = SlsOperationRun::query()
            ->where('operation_key', 'serpapi_search')
            ->latest('created_at')
            ->limit(100)
            ->get();
        $monthlyStats = $recentRuns
            ->groupBy(fn (SlsOperationRun $run) => ($run->started_at ?: $run->created_at)?->format('Y-m') ?: 'Unknown')
            ->map(function ($runs, string $month) {
                return (object) [
                    'run_month' => $month,
                    'runs_count' => $runs->count(),
                    'query_count' => $runs->sum(fn (SlsOperationRun $run) => (int) data_get($run->summary, 'queries', $run->processed_count ?? 0)),
                    'result_count' => $runs->sum(fn (SlsOperationRun $run) => (int) data_get($run->summary, 'results', 0)),
                    'captured_count' => $runs->sum(fn (SlsOperationRun $run) => (int) data_get($run->summary, 'captured', $run->success_count ?? 0)),
                ];
            })
            ->sortByDesc('run_month')
            ->take(12)
            ->values();

        return view('sls.serpapi-searches.index', [
            'templates' => SerpApiSearchTemplate::query()->orderByDesc('is_enabled')->orderBy('name')->get(),
            'countries' => $countries,
            'regions' => $countries->pluck('region')->filter()->unique()->sort()->values(),
            'languageOptions' => [
                '' => 'All languages',
                'en' => 'English',
                'fr' => 'French',
                'pt' => 'Portuguese',
                'ar' => 'Arabic',
            ],
            'defaultKeywordText' => implode("\n", $defaultSerpApiKeywords),
            'monthlyStats' => $monthlyStats,
            'recentRuns' => $recentRuns->take(10)->values(),
            'migrationMissing' => false,
        ]);
    } catch (Throwable $exception) {
        report($exception);

        return view('sls.serpapi-searches.index', [
            'templates' => collect(),
            'countries' => collect(),
            'regions' => collect(),
            'languageOptions' => [],
            'monthlyStats' => collect(),
            'recentRuns' => collect(),
            'migrationMissing' => true,
            'setupError' => 'SerpAPI search page failed while loading: ' . $exception->getMessage(),
        ]);
    }
};

Route::get('/sls/serpapi-searches', function () use ($serpApiSearchState) {
    return $serpApiSearchState();
})->name('sls.serpapiSearches.index');

Route::post('/sls/serpapi-searches/templates', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:180'],
        'focus' => ['required', 'string', 'max:80'],
        'query_template' => ['required', 'string', 'max:2000'],
        'keywords_text' => ['nullable', 'string', 'max:5000'],
        'results_per_country' => ['required', 'integer', 'min:1', 'max:20'],
        'is_enabled' => ['nullable', 'boolean'],
    ]);

    SerpApiSearchTemplate::query()->create([
        'name' => $data['name'],
        'focus' => $data['focus'],
        'query_template' => $data['query_template'],
        'keywords' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['keywords_text'] ?? '')))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all(),
        'results_per_country' => (int) $data['results_per_country'],
        'is_enabled' => (bool) ($data['is_enabled'] ?? true),
    ]);

    return redirect()->route('sls.serpapiSearches.index')->with('status', 'SerpAPI search template saved.');
})->name('sls.serpapiSearches.templates.store');

Route::post('/sls/serpapi-searches/run', function (Request $request) use ($startOperationRun) {
    $data = $request->validate([
        'template_id' => ['nullable', 'integer', 'exists:serpapi_search_templates,id'],
        'custom_query_template' => ['nullable', 'string', 'max:2000'],
        'predefined_keywords_text' => ['nullable', 'string', 'max:10000'],
        'custom_keywords_text' => ['nullable', 'string', 'max:5000'],
        'countries' => ['array'],
        'countries.*' => ['string', 'max:10'],
        'region' => ['nullable', 'string', 'max:120'],
        'language' => ['nullable', 'string', 'max:10'],
        'results_per_country' => ['required', 'integer', 'min:1', 'max:20'],
        'dry_run' => ['nullable', 'boolean'],
        'capture' => ['nullable', 'boolean'],
    ]);

    $template = filled($data['template_id'] ?? null)
        ? SerpApiSearchTemplate::query()->find((int) $data['template_id'])
        : null;

    $countryQuery = Country::query()->whereNotNull('iso_code');
    if (filled($data['region'] ?? null)) {
        $countryQuery->whereRaw('LOWER(region) = ?', [Str::lower((string) $data['region'])]);
    }

    if (filled($data['language'] ?? null)) {
        $countryQuery->whereRaw('LOWER(default_language_code) = ?', [Str::lower((string) $data['language'])]);
    }

    if (! empty($data['countries'])) {
        $countryQuery->whereIn('iso_code', array_map('strtoupper', $data['countries']));
    }

    $countries = $countryQuery->orderBy('name')->pluck('iso_code')->filter()->map(fn ($iso) => strtoupper((string) $iso))->values()->all();

    if ($countries === []) {
        return back()->withInput()->withErrors(['countries' => 'Choose at least one country, region, or language group.']);
    }

    $keywordLines = function (?string $text) {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();
    };

    $predefinedKeywords = $keywordLines($data['predefined_keywords_text'] ?? '');
    $customKeywords = $keywordLines($data['custom_keywords_text'] ?? '');
    $keywords = $predefinedKeywords
        ->merge($customKeywords)
        ->map(fn ($line) => trim((string) $line))
        ->filter()
        ->unique(fn ($line) => Str::lower($line))
        ->values()
        ->all();
    $templateKeywords = collect((array) ($template?->keywords ?? []))
        ->map(fn ($line) => trim((string) $line))
        ->filter()
        ->unique(fn ($line) => Str::lower($line))
        ->values()
        ->all();

    $parameters = [
        'template_id' => $template?->id,
        'template_name' => $template?->name ?: 'Custom SerpAPI search',
        'focus' => $template?->focus ?: 'social_security',
        'query_template' => trim((string) ($data['custom_query_template'] ?? '')) ?: ($template?->query_template ?: '"{country}" ({keywords}) (tender OR RFP OR procurement OR "expression of interest")'),
        'keywords' => $keywords !== [] ? $keywords : $templateKeywords,
        'predefined_keywords' => $predefinedKeywords->all(),
        'custom_keywords' => $customKeywords->all(),
        'countries' => $countries,
        'region' => $data['region'] ?? null,
        'language' => $data['language'] ?? null,
        'results_per_country' => (int) $data['results_per_country'],
        'dry_run' => (bool) ($data['dry_run'] ?? true),
        'capture' => (bool) ($data['capture'] ?? true),
    ];

    $run = SlsOperationRun::query()->create([
        'operation_key' => 'serpapi_search',
        'operation_name' => 'SerpAPI: ' . $parameters['template_name'],
        'status' => 'queued',
        'parameters' => $parameters,
        'items' => [],
        'summary' => [],
        'dry_run' => (bool) $parameters['dry_run'],
        'total_count' => count($countries),
    ]);

    $startOperationRun($run);

    return redirect()->route('sls.serpapiSearches.index')->with('status', 'SerpAPI search run queued. Refresh this page to see results.');
})->name('sls.serpapiSearches.run');

Route::post('/sls/operations/bank-domain-guesser', function (Request $request) use ($startOperationRun) {
    $data = $request->validate([
        'limit' => ['required', 'integer', 'min:1', 'max:500'],
        'region' => ['nullable', 'string', 'max:80'],
        'countries' => ['array'],
        'countries.*' => ['string', 'max:80'],
        'retry_previous_not_found' => ['nullable', 'boolean'],
        'dry_run' => ['nullable', 'boolean'],
        'pause' => ['required', 'integer', 'min:0', 'max:30'],
        'confidence' => ['required', 'integer', 'min:1', 'max:100'],
        'checks' => ['required', 'integer', 'min:1', 'max:48'],
    ]);

    $parameters = [
        'limit' => (int) $data['limit'],
        'region' => $data['region'] ?? null,
        'countries' => $data['countries'] ?? [],
        'retry_previous_not_found' => (bool) ($data['retry_previous_not_found'] ?? false),
        'dry_run' => (bool) ($data['dry_run'] ?? false),
        'pause' => (int) $data['pause'],
        'confidence' => (int) $data['confidence'],
        'checks' => (int) $data['checks'],
    ];

    $run = SlsOperationRun::query()->create([
        'operation_key' => 'bank_domain_guesser',
        'operation_name' => 'Bank domain guesser',
        'status' => 'queued',
        'parameters' => $parameters,
        'items' => [],
        'summary' => [],
        'dry_run' => (bool) $parameters['dry_run'],
    ]);

    $startOperationRun($run);

    return redirect()->route('sls.operations.runs.show', $run);
})->name('sls.operations.bankDomains.run');

Route::get('/sls/operations/runs/{operationRun}', function (SlsOperationRun $operationRun) {
    return view('sls.operations.run', [
        'operationRun' => $operationRun,
    ]);
})->name('sls.operations.runs.show');

$operationRunItems = function (SlsOperationRun $operationRun): array {
    return collect($operationRun->items ?? [])
        ->map(function (array $item) {
            $item['name'] = SocialSecurityAdminNameCleaner::repairMojibake((string) ($item['name'] ?? ''));
            $item['country'] = SocialSecurityAdminNameCleaner::repairMojibake((string) ($item['country'] ?? ''));
            $item['reasons'] = collect($item['reasons'] ?? [])
                ->map(fn ($reason) => SocialSecurityAdminNameCleaner::repairMojibake((string) $reason))
                ->values()
                ->all();

            return $item;
        })
        ->values()
        ->all();
};

Route::post('/sls/operations/runs/{operationRun}/items/{itemIndex}/save', function (Request $request, SlsOperationRun $operationRun, int $itemIndex) use ($operationRunItems) {
    abort_unless($operationRun->operation_key === 'bank_domain_guesser', 404);

    $data = $request->validate([
        'website_url' => ['required', 'url', 'max:500'],
    ]);

    $items = $operationRunItems($operationRun);
    abort_unless(isset($items[$itemIndex]), 404);

    $item = $items[$itemIndex];
    $domain = parse_url($data['website_url'], PHP_URL_HOST) ?: $data['website_url'];
    $domain = preg_replace('/^www\./i', '', strtolower((string) $domain));

    MarketOrganization::query()
        ->whereKey((int) $item['id'])
        ->update([
            'website_url' => $data['website_url'],
            'website_domain' => $domain,
            'last_error' => null,
        ]);

    $items[$itemIndex] = array_merge($item, [
        'status' => 'manual_updated',
        'best_url' => $data['website_url'],
        'best_domain' => $domain,
        'confidence' => 100,
        'reasons' => array_values(array_unique(array_merge($item['reasons'] ?? [], ['Manually corrected by user']))),
        'reviewed_at' => now()->toDateTimeString(),
    ]);

    $operationRun->update(['items' => $items]);

    return response()->json(['ok' => true, 'message' => 'Finding updated.']);
})->name('sls.operations.runs.items.save');

Route::post('/sls/operations/runs/{operationRun}/items/{itemIndex}/reject', function (SlsOperationRun $operationRun, int $itemIndex) use ($operationRunItems) {
    abort_unless($operationRun->operation_key === 'bank_domain_guesser', 404);

    $items = $operationRunItems($operationRun);
    abort_unless(isset($items[$itemIndex]), 404);

    $item = $items[$itemIndex];
    $organization = MarketOrganization::query()->find((int) $item['id']);

    if ($organization && filled($item['best_domain'] ?? null)) {
        $bestDomain = preg_replace('/^www\./i', '', strtolower((string) $item['best_domain']));
        $currentDomain = preg_replace('/^www\./i', '', strtolower((string) $organization->website_domain));

        if ($currentDomain === $bestDomain) {
            $organization->update([
                'website_url' => null,
                'website_domain' => null,
                'last_error' => 'Bank domain guess rejected by user.',
            ]);
        }
    }

    $items[$itemIndex] = array_merge($item, [
        'status' => 'rejected',
        'confidence' => 0,
        'reasons' => array_values(array_unique(array_merge($item['reasons'] ?? [], ['Rejected by user']))),
        'reviewed_at' => now()->toDateTimeString(),
    ]);

    $operationRun->update(['items' => $items]);

    return response()->json(['ok' => true, 'message' => 'Finding rejected.']);
})->name('sls.operations.runs.items.reject');

Route::get('/sls/operations/runs/{operationRun}/status', function (SlsOperationRun $operationRun) use ($operationRunItems) {
    return response()->json([
        'id' => $operationRun->id,
        'operation_name' => $operationRun->operation_name,
        'status' => $operationRun->status,
        'dry_run' => $operationRun->dry_run,
        'parameters' => $operationRun->parameters ?? [],
        'summary' => $operationRun->summary ?? [],
        'items' => $operationRunItems($operationRun),
        'processed_count' => $operationRun->processed_count,
        'total_count' => $operationRun->total_count,
        'success_count' => $operationRun->success_count,
        'failure_count' => $operationRun->failure_count,
        'error_message' => $operationRun->error_message,
        'started_at' => optional($operationRun->started_at)->toDateTimeString(),
        'finished_at' => optional($operationRun->finished_at)->toDateTimeString(),
        'updated_at' => optional($operationRun->updated_at)->toDateTimeString(),
    ]);
})->name('sls.operations.runs.status');

$securityActions = [
    'can_view' => 'View',
    'can_search' => 'Search',
    'can_insert' => 'Insert',
    'can_update' => 'Update',
    'can_delete' => 'Delete',
    'can_approve' => 'Approve',
    'can_print' => 'Print',
    'can_export' => 'Export',
    'can_import' => 'Import',
    'can_run_process' => 'Run',
    'can_assign' => 'Assign',
    'can_configure' => 'Configure',
];

$ensureSecurityAccess = function (string $action = 'view') {
    $user = auth()->user();

    if (! $user) {
        return;
    }

    abort_unless(app(\App\Services\AccessControlService::class)->can($user, 'security', $action), 403);
};

Route::get('/sls/security', function () use ($securityActions, $ensureSecurityAccess) {
    $ensureSecurityAccess('view');

    return view('sls.security.index', [
        'users' => User::query()->with('group')->orderBy('name')->get(),
        'groups' => UserGroup::query()
            ->with(['permissions.form', 'countryAccess.country', 'productAccess.product'])
            ->withCount(['users', 'permissions', 'countryAccess', 'productAccess'])
            ->orderBy('name')
            ->get(),
        'forms' => PermissionForm::query()->orderBy('category')->orderBy('label')->get(),
        'formsByCategory' => PermissionForm::query()->orderBy('category')->orderBy('label')->get()->groupBy('category'),
        'actions' => $securityActions,
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code', 'region']),
        'regions' => Country::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region')->filter()->values(),
        'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        'recentAuditLogs' => AccessAuditLog::query()->with('user')->latest('created_at')->limit(25)->get(),
    ]);
})->name('sls.security.index');

Route::post('/sls/security/users', function (Request $request) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('insert');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['nullable', 'string', 'min:8'],
        'user_group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
        'theme_preference' => ['nullable', 'in:conservative,lively,white'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $data['password'] = Hash::make($data['password'] ?: Str::password(16));
    $data['theme_preference'] = $data['theme_preference'] ?? 'white';
    $data['is_active'] = (bool) ($data['is_active'] ?? true);

    $user = User::query()->create($data);
    app(\App\Services\AccessControlService::class)->log('insert', 'security', $user, ['screen' => 'users']);

    return back()->with('status', 'User created. If you left the password blank, set one before enforcing login.');
})->name('sls.security.users.store');

Route::post('/sls/security/users/{user}', function (Request $request, User $user) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('update');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        'password' => ['nullable', 'string', 'min:8'],
        'user_group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
        'theme_preference' => ['nullable', 'in:conservative,lively,white'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    if (filled($data['password'] ?? null)) {
        $data['password'] = Hash::make($data['password']);
    } else {
        unset($data['password']);
    }

    $data['is_active'] = (bool) ($data['is_active'] ?? false);
    $user->update($data);
    app(\App\Services\AccessControlService::class)->log('update', 'security', $user, ['screen' => 'users']);

    return back()->with('status', 'User updated.');
})->name('sls.security.users.update');

Route::post('/sls/security/groups', function (Request $request) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('insert');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255', 'unique:user_groups,name'],
        'description' => ['nullable', 'string', 'max:2000'],
        'is_admin' => ['nullable', 'boolean'],
    ]);

    $group = UserGroup::query()->create([
        'name' => $data['name'],
        'slug' => Str::slug($data['name']),
        'description' => $data['description'] ?? null,
        'is_system' => false,
        'is_admin' => (bool) ($data['is_admin'] ?? false),
    ]);

    foreach (PermissionForm::query()->pluck('key') as $formKey) {
        UserGroupPermission::query()->create([
            'user_group_id' => $group->id,
            'form_key' => $formKey,
            'can_view' => true,
            'can_search' => true,
        ]);
    }

    app(\App\Services\AccessControlService::class)->log('insert', 'security', $group, ['screen' => 'groups']);

    return back()->with('status', 'User group created with read/search defaults.');
})->name('sls.security.groups.store');

Route::post('/sls/security/groups/{group}', function (Request $request, UserGroup $group) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('update');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255', 'unique:user_groups,name,' . $group->id],
        'description' => ['nullable', 'string', 'max:2000'],
        'is_admin' => ['nullable', 'boolean'],
    ]);

    $group->update([
        'name' => $data['name'],
        'slug' => Str::slug($data['name']),
        'description' => $data['description'] ?? null,
        'is_admin' => (bool) ($data['is_admin'] ?? false),
    ]);

    app(\App\Services\AccessControlService::class)->log('update', 'security', $group, ['screen' => 'groups']);

    return back()->with('status', 'User group updated.');
})->name('sls.security.groups.update');

Route::post('/sls/security/groups/{group}/permissions', function (Request $request, UserGroup $group) use ($securityActions, $ensureSecurityAccess) {
    $ensureSecurityAccess('configure');

    $submitted = collect($request->input('permissions', []));
    $forms = PermissionForm::query()->pluck('key');

    foreach ($forms as $formKey) {
        $row = [
            'user_group_id' => $group->id,
            'form_key' => $formKey,
        ];

        foreach (array_keys($securityActions) as $actionColumn) {
            $row[$actionColumn] = (bool) data_get($submitted, $formKey . '.' . $actionColumn, false);
        }

        UserGroupPermission::query()->updateOrCreate(
            ['user_group_id' => $group->id, 'form_key' => $formKey],
            $row,
        );
    }

    app(\App\Services\AccessControlService::class)->log('configure', 'security', $group, ['screen' => 'permissions']);

    return back()->with('status', 'Permission matrix updated for ' . $group->name . '.');
})->name('sls.security.groups.permissions.update');

Route::post('/sls/security/groups/{group}/country-access', function (Request $request, UserGroup $group) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('configure');

    $data = $request->validate([
        'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        'region' => ['nullable', 'string', 'max:120'],
        'can_access' => ['nullable', 'boolean'],
    ]);

    if (blank($data['country_id'] ?? null) && blank($data['region'] ?? null)) {
        return back()->with('status', 'Choose either a country or a region restriction.');
    }

    UserGroupCountryAccess::query()->updateOrCreate(
        [
            'user_group_id' => $group->id,
            'country_id' => $data['country_id'] ?? null,
            'region' => $data['region'] ?? null,
        ],
        ['can_access' => (bool) ($data['can_access'] ?? true)],
    );

    app(\App\Services\AccessControlService::class)->log('configure', 'security', $group, ['screen' => 'country_access']);

    return back()->with('status', 'Country access rule saved.');
})->name('sls.security.groups.countryAccess.store');

Route::post('/sls/security/country-access/{rule}/remove', function (UserGroupCountryAccess $rule) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('configure');

    $group = $rule->group;
    $rule->delete();
    app(\App\Services\AccessControlService::class)->log('configure', 'security', $group, ['screen' => 'country_access', 'removed_rule' => true]);

    return back()->with('status', 'Country access rule removed.');
})->name('sls.security.countryAccess.remove');

Route::post('/sls/security/groups/{group}/product-access', function (Request $request, UserGroup $group) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('configure');

    $data = $request->validate([
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'product_key' => ['nullable', 'string', 'max:120'],
        'can_access' => ['nullable', 'boolean'],
    ]);

    if (blank($data['product_id'] ?? null) && blank($data['product_key'] ?? null)) {
        return back()->with('status', 'Choose either a product or product key.');
    }

    UserGroupProductAccess::query()->updateOrCreate(
        [
            'user_group_id' => $group->id,
            'product_id' => $data['product_id'] ?? null,
            'product_key' => $data['product_key'] ?? null,
        ],
        ['can_access' => (bool) ($data['can_access'] ?? true)],
    );

    app(\App\Services\AccessControlService::class)->log('configure', 'security', $group, ['screen' => 'product_access']);

    return back()->with('status', 'Product access rule saved.');
})->name('sls.security.groups.productAccess.store');

Route::post('/sls/security/product-access/{rule}/remove', function (UserGroupProductAccess $rule) use ($ensureSecurityAccess) {
    $ensureSecurityAccess('configure');

    $group = $rule->group;
    $rule->delete();
    app(\App\Services\AccessControlService::class)->log('configure', 'security', $group, ['screen' => 'product_access', 'removed_rule' => true]);

    return back()->with('status', 'Product access rule removed.');
})->name('sls.security.productAccess.remove');

Route::get('/sls/intelligence/world', function (Request $request) use ($allMapCountries, $relevantCountryUpdates) {
    $focus = array_key_exists((string) $request->query('focus', 'social_security'), config('country_intelligence.focuses', []))
        ? (string) $request->query('focus', 'social_security')
        : 'social_security';
    $region = Str::of((string) $request->query('region', 'all'))->lower()->replace('_', ' ')->toString();
    $allCountriesWithUpdates = $relevantCountryUpdates($allMapCountries(), $focus);
    $regionCount = [
        'Africa' => $allCountriesWithUpdates->where('region_group', 'Africa')->count(),
        'Caribbean' => $allCountriesWithUpdates->where('region_group', 'Caribbean')->count(),
        'Asia' => $allCountriesWithUpdates->where('region_group', 'Asia')->count(),
        'Latin America' => $allCountriesWithUpdates->where('region_group', 'Latin America')->count(),
        'North America' => $allCountriesWithUpdates->where('region_group', 'North America')->count(),
        'Europe' => $allCountriesWithUpdates->where('region_group', 'Europe')->count(),
    ];
    $mapCountries = in_array($region, ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true)
        ? $allCountriesWithUpdates
            ->filter(fn (array $country) => Str::lower((string) ($country['region_group'] ?? '')) === $region)
            ->values()
        : $allCountriesWithUpdates;
    $latestItems = $mapCountries
        ->where('latest_title')
        ->sortByDesc(fn (array $country) => $country['latest_sort_date'] ?? $country['latest_date'] ?? '')
        ->values();

    return view('sls.intelligence.world', [
        'countries' => $mapCountries,
        'latestItems' => $latestItems,
        'focus' => $focus,
        'focusConfig' => config("country_intelligence.focuses.$focus"),
        'focuses' => config('country_intelligence.focuses'),
        'updateCount' => $mapCountries->sum('update_count'),
        'monitoredCount' => $mapCountries->filter(fn (array $country) => $country['database_id'] !== null)->count(),
        'regionCount' => $regionCount,
    ]);
})->name('sls.intelligence.world');

Route::get('/sls/intelligence/africa', fn () => redirect()->route('sls.intelligence.world', ['region' => 'africa']))->name('sls.intelligence.africa');

Route::get('/sls/intelligence/caribbean', fn () => redirect()->route('sls.intelligence.world', ['region' => 'caribbean']))->name('sls.intelligence.caribbean');

Route::match(['GET', 'POST'], '/sls/worker/intelligence/run', function (Request $request, CountryIntelligenceMonitor $monitor) {
    $configuredToken = (string) config('services.sls_worker.token');
    $requestToken = (string) ($request->bearerToken() ?: $request->query('token', $request->input('token', '')));

    if ($configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
        abort(403, 'Invalid worker token.');
    }

    $focus = array_key_exists((string) $request->query('focus', 'sector_tenders'), config('country_intelligence.focuses', []))
        ? (string) $request->query('focus', 'sector_tenders')
        : 'sector_tenders';
    $region = (string) $request->query('region', 'africa_asia_caribbean_latin_america_north_america_europe');
    $maxResults = max(1, min(20, (int) $request->query('max', config('country_intelligence.default_max_results', 10))));
    $cycleSize = max(1, min(10, (int) $request->query('cycle', 1)));
    $slotsPerDay = max(1, (int) $request->query('slots_per_day', 48));
    $slot = $request->query('slot');

    if ($slot === null || $slot === '') {
        $minutesSinceMidnight = ((int) now()->format('G') * 60) + (int) now()->format('i');
        $slot = intdiv($minutesSinceMidnight, max(1, (int) floor(1440 / $slotsPerDay))) % $slotsPerDay;
    }

    $results = $monitor->run(
        maxResults: $maxResults,
        cycleSize: $cycleSize,
        cycleSlot: (int) $slot,
        slotsPerDay: $slotsPerDay,
        focus: $focus,
        region: $region,
    );

    return response()->json([
        'ok' => true,
        'ran_at' => now()->toDateTimeString(),
        'focus' => $focus,
        'region' => $region,
        'cycle_size' => $cycleSize,
        'slot' => (int) $slot,
        'slots_per_day' => $slotsPerDay,
        'countries' => collect($results)->map(fn (array $result, string $iso) => [
            'iso' => $iso,
            'country' => $result['country'],
            'items_found' => $result['items_found'],
        ])->values(),
    ]);
})->name('sls.worker.intelligence.run');

Route::get('/sls/intelligence/opportunities/intake', function () use ($orderedProducts) {
    return view('sls.intelligence.opportunity-intake', [
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code', 'region', 'default_language_code']),
        'products' => $orderedProducts(),
        'focuses' => config('country_intelligence.focuses', []),
    ]);
})->name('sls.intelligence.opportunityIntake.create');

Route::post('/sls/intelligence/opportunities/intake', function (Request $request) use ($orderedProducts) {
    $focuses = config('country_intelligence.focuses', []);
    $focusKeys = array_keys($focuses);

    $data = $request->validate([
        'alert_text' => ['required_without:source_url', 'nullable', 'string', 'max:20000'],
        'source_url' => ['nullable', 'string', 'max:4000'],
        'title' => ['nullable', 'string', 'max:500'],
        'project_title' => ['nullable', 'string', 'max:500'],
        'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        'country_name' => ['nullable', 'string', 'max:255'],
        'item_type' => ['nullable', 'in:auto,news,tender'],
        'focus' => ['nullable', 'in:auto,' . implode(',', $focusKeys)],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'source_name' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:5000'],
    ]);

    $alertText = trim((string) ($data['alert_text'] ?? ''));
    $extractLine = function (string $label) use ($alertText): ?string {
        if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi', $alertText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    };
    $extractFirstUrl = function () use ($alertText): ?string {
        if (preg_match('/\[[^\]]+\]\((https?:\/\/[^\s)]+)\)/i', $alertText, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/https?:\/\/\S+/i', $alertText, $matches)) {
            return rtrim(trim($matches[0]), '),.;');
        }

        return null;
    };
    $resolveTrackingUrl = function (?string $url): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! isset($parts['host']) || ! Str::contains(Str::lower($parts['host']), 'mandrillapp.com')) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        $payload = (string) ($query['p'] ?? '');
        if ($payload === '') {
            return $url;
        }

        $payload = strtr($payload, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        $outer = $decoded ? json_decode($decoded, true) : null;
        $inner = is_array($outer) && isset($outer['p']) ? json_decode((string) $outer['p'], true) : null;
        $resolved = is_array($inner) ? ($inner['url'] ?? null) : null;

        return is_string($resolved) && $resolved !== '' ? $resolved : $url;
    };

    $rawUrl = trim((string) ($data['source_url'] ?? '')) ?: $extractFirstUrl();
    $url = $resolveTrackingUrl($rawUrl);
    if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
        return back()->withInput()->withErrors(['source_url' => 'Paste the alert link or a valid source URL.']);
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        $title = $extractLine('Title') ?: '';
        if (preg_match('/^\s*Title\s*:\s*\[([^\]]+)\]/mi', $alertText, $matches)) {
            $title = trim($matches[1]);
        }
    }
    if ($title === '') {
        $title = 'Submitted opportunity from ' . (parse_url($url, PHP_URL_HOST) ?: 'source');
    }

    $projectTitle = trim((string) ($data['project_title'] ?? '')) ?: ($extractLine('Project Title') ?: null);
    $countryName = trim((string) ($data['country_name'] ?? '')) ?: ($extractLine('Project Country') ?: '');
    $country = null;
    if (! empty($data['country_id'])) {
        $country = Country::query()->find((int) $data['country_id']);
    }
    if (! $country && $countryName !== '') {
        $country = Country::query()
            ->where('name', $countryName)
            ->orWhere('iso_code', $countryName)
            ->orWhere('name', 'like', '%' . $countryName . '%')
            ->first();
    }
    if (! $country) {
        return back()->withInput()->withErrors(['country_name' => 'Choose a country or include a Project Country line in the alert.']);
    }

    $combinedText = Str::lower($title . ' ' . $projectTitle . ' ' . $alertText . ' ' . $url);
    $focus = (string) ($data['focus'] ?? 'auto');
    if ($focus === 'auto') {
        $focus = Str::contains($combinedText, ['cash transfer', 'social safety net', 'social assistance', 'social protection', 'molsa', 'ssnep', 'beneficiary registry'])
            ? 'social_security'
            : 'sector_tenders';
    }

    $itemType = (string) ($data['item_type'] ?? 'auto');
    if ($itemType === 'auto') {
        $itemType = Str::contains($combinedText, ['procurement-detail', 'procurement', 'tender', 'rfp', 'request for bids', 'bid', 'expression of interest', 'world bank'])
            ? 'tender'
            : 'news';
    }

    $sourceHost = parse_url($url, PHP_URL_HOST);
    $sourceName = filled($data['source_name'] ?? null)
        ? trim((string) $data['source_name'])
        : (Str::contains(Str::lower((string) $sourceHost), 'worldbank.org') ? 'World Bank Procurement' : ($sourceHost ? Str::of($sourceHost)->replaceStart('www.', '')->toString() : 'Submitted source'));

    $existing = CountryUpdate::query()->where('source_url', $url)->first();
    if ($existing) {
        return redirect()
            ->route('sls.intelligence.updates.sourcePage', ['countryUpdate' => $existing])
            ->with('status', 'This opportunity URL was already captured. I opened the existing record instead.');
    }

    $focusLabel = $focuses[$focus]['label'] ?? 'Country Intelligence';
    $typeLabel = $itemType === 'tender' ? 'Manual tender/RFP opportunity' : 'Manual news/intelligence opportunity';
    $summaryParts = array_filter([
        '[' . $focusLabel . '] [' . $typeLabel . '] Submitted from manual opportunity intake.',
        $projectTitle ? 'Project: ' . $projectTitle . '.' : null,
        filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        $alertText !== '' ? Str::limit(preg_replace('/\s+/', ' ', $alertText), 1800, '') : null,
    ]);

    $update = CountryUpdate::create([
        'country_id' => $country->id,
        'title' => $title,
        'title_english' => $title,
        'title_original' => $title,
        'source_name' => $sourceName,
        'source_url' => $url,
        'publication_date' => null,
        'retrieved_at' => now(),
        'summary' => implode(' ', $summaryParts),
        'summary_english' => implode(' ', $summaryParts),
        'relevance_score' => 100,
        'review_status' => 'unreviewed',
    ]);

    if (! empty($data['product_id'])) {
        $product = Product::query()->find((int) $data['product_id']);
        CountryUpdateOpportunity::query()->updateOrCreate(
            [
                'country_update_id' => $update->id,
                'product_id' => $product?->id,
            ],
            [
                'issue_area' => $focusLabel,
                'opportunity_stage' => 'mapped',
                'issue_summary' => Str::limit($update->summary_english ?: $update->summary ?: $update->title_english ?: $update->title, 1800, ''),
                'product_alignment' => ($product?->name ?? 'Selected product') . ' was mapped from manual opportunity intake.',
            ]
        );
    }

    return redirect()
        ->route('sls.intelligence.review', [
            'country' => $update->country_id,
            'focus' => $focus,
            'type' => $itemType === 'tender' ? 'tenders' : 'news',
            'status' => 'all',
            'limit' => 500,
        ])
        ->with('status', 'Opportunity intake captured this item and sent it to Review Desk.');
})->name('sls.intelligence.opportunityIntake.store');
Route::get('/sls/intelligence/stories/create', function () {
    return view('sls.intelligence.create-story', [
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code', 'region', 'default_language_code']),
        'focuses' => config('country_intelligence.focuses', []),
    ]);
})->name('sls.intelligence.stories.create');

Route::post('/sls/intelligence/stories', function (Request $request) {
    $focuses = config('country_intelligence.focuses', []);
    $focusKeys = array_keys($focuses);

    $data = $request->validate([
        'country_id' => ['required', 'integer', 'exists:countries,id'],
        'item_type' => ['required', 'in:news,tender'],
        'focus' => ['required', 'in:' . implode(',', $focusKeys)],
        'title' => ['required', 'string', 'max:500'],
        'title_original' => ['nullable', 'string', 'max:500'],
        'source_name' => ['nullable', 'string', 'max:255'],
        'source_url' => ['required', 'url', 'max:1000'],
        'publication_date' => ['nullable', 'date'],
        'summary' => ['required', 'string', 'max:5000'],
    ]);

    $url = trim((string) $data['source_url']);
    $existing = CountryUpdate::query()
        ->where('source_url', $url)
        ->first();

    if ($existing) {
        return redirect()
            ->route('sls.intelligence.updates.sourcePage', ['countryUpdate' => $existing])
            ->with('status', 'This story URL was already captured. I opened the existing record instead.');
    }

    $focusLabel = $focuses[$data['focus']]['label'] ?? 'Social Security Intelligence';
    $typeLabel = $data['item_type'] === 'tender' ? 'Manual tender/RFP item' : 'Manual news story';
    $sourceHost = parse_url($url, PHP_URL_HOST);
    $sourceName = filled($data['source_name'] ?? null)
        ? trim((string) $data['source_name'])
        : ($sourceHost ? Str::of($sourceHost)->replaceStart('www.', '')->toString() : 'Manual source');
    $summary = '[' . $focusLabel . '] [' . $typeLabel . '] ' . trim((string) $data['summary']);

    if ($data['item_type'] === 'tender' && ! Str::contains(Str::lower($summary . ' ' . $data['title']), ['tender', 'rfp', 'request for proposal', 'procurement', 'bid'])) {
        $summary .= ' Manual classification note: tender/RFP/procurement item.';
    }

    $update = CountryUpdate::create([
        'country_id' => (int) $data['country_id'],
        'title' => trim((string) $data['title']),
        'title_english' => trim((string) $data['title']),
        'title_original' => filled($data['title_original'] ?? null) ? trim((string) $data['title_original']) : trim((string) $data['title']),
        'source_name' => $sourceName,
        'source_url' => $url,
        'publication_date' => filled($data['publication_date'] ?? null) ? $data['publication_date'] : null,
        'retrieved_at' => now(),
        'summary' => $summary,
        'summary_english' => trim((string) $data['summary']),
        'relevance_score' => 100,
        'review_status' => 'unreviewed',
    ]);

    return redirect()
        ->route('sls.intelligence.review', [
            'country' => $update->country_id,
            'focus' => $data['focus'],
            'type' => $data['item_type'] === 'tender' ? 'tenders' : 'news',
            'status' => 'all',
            'limit' => 500,
        ])
        ->with('status', 'Manual ' . ($data['item_type'] === 'tender' ? 'tender' : 'news story') . ' added to Review Desk.');
})->name('sls.intelligence.stories.store');

Route::get('/sls/intelligence/review', function (Request $request) use ($allMapCountries, $orderedProducts) {
    $focuses = config('country_intelligence.focuses', []);
    $focus = array_key_exists((string) $request->query('focus', 'all'), $focuses)
        ? (string) $request->query('focus')
        : 'all';
    $region = Str::of((string) $request->query('region', 'all'))->lower()->toString();
    $statusFilter = Str::of((string) $request->query('status', 'all'))->lower()->toString();
    $publishedFilter = Str::of((string) $request->query('published', ''))->lower()->toString();
    $retrievedFilter = Str::of((string) $request->query('retrieved', 'current'))->lower()->toString();
    if (! in_array($retrievedFilter, ['current', 'last7', 'last14', 'last30', 'last90', 'all'], true)) {
        $retrievedFilter = 'current';
    }
    $typeFilter = Str::of((string) $request->query('type', ''))->lower()->toString();
    $countrySearchQuery = trim((string) $request->query('country_q', ''));
    $countrySearchType = Str::of((string) $request->query('country_type', 'all'))->lower()->toString();
    $countrySearchStatus = Str::of((string) $request->query('country_status', 'all'))->lower()->toString();
    $activeTypeFilter = in_array($countrySearchType, ['tenders', 'news'], true) ? $countrySearchType : $typeFilter;
    $activeStatusFilter = $countrySearchQuery !== '' ? $countrySearchStatus : $statusFilter;
    $perPage = min(500, max(25, (int) $request->query('per_page', $request->query('limit', 100))));
    $page = max(1, (int) $request->query('page', 1));
    $reviewDeskCurrentStart = Carbon::parse('2026-06-01')->startOfDay();

    $configuredCountries = $allMapCountries();

    if (in_array($region, ['africa', 'caribbean', 'asia', 'latin_america', 'north_america', 'europe'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => Str::lower($country['region_group']) === str_replace('_', ' ', $region))
            ->values();
    } elseif (in_array($region, ['africa_asia', 'asia_africa'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean', 'caribbean_africa_asia'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america', 'africa_asia_latin_america_caribbean'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america_north_america', 'africa_asia_caribbean_latin_america_north_america_europe', 'all_core_regions'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_latin_america', 'latin_america_africa_asia'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'latin america'], true))
            ->values();
    }

    $countrySearchCountries = collect();

    if ($countrySearchQuery !== '') {
        $needle = Str::lower($countrySearchQuery);
        $countrySearchCountries = Country::query()
            ->where(function ($countryQuery) use ($countrySearchQuery) {
                $countryQuery
                    ->where('name', 'like', '%' . $countrySearchQuery . '%')
                    ->orWhere('iso_code', 'like', '%' . $countrySearchQuery . '%')
                    ->orWhere('region', 'like', '%' . $countrySearchQuery . '%')
                    ->orWhere('social_security_administration_name', 'like', '%' . $countrySearchQuery . '%');
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(iso_code) = ? THEN 1 ELSE 2 END', [$needle, $needle])
            ->orderBy('name')
            ->limit(20)
            ->get();

        $countryIsoCodes = $countrySearchCountries
            ->pluck('iso_code')
            ->filter()
            ->map(fn ($iso) => strtoupper((string) $iso))
            ->values()
            ->all();
        $configuredCountries = $countrySearchCountries
            ->map(fn (Country $country) => [
                'name' => $country->name,
                'iso' => strtoupper((string) $country->iso_code),
                'region_group' => $country->region ?: 'Unknown',
            ])
            ->values();
    } elseif ($region === 'all') {
        $countryIsoCodes = Country::query()
            ->whereNotNull('iso_code')
            ->pluck('iso_code')
            ->all();
    } elseif ($region === 'global') {
        $countryIsoCodes = Country::query()
            ->whereRaw('LOWER(region) = ?', ['global'])
            ->whereNotNull('iso_code')
            ->pluck('iso_code')
            ->all();
    } else {
        $countryIsoCodes = $configuredCountries->pluck('iso')->all();
    }

    $dbCountries = Country::query()
        ->whereIn('iso_code', $countryIsoCodes)
        ->get()
        ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
    $countryIds = $dbCountries->pluck('id')->all();
    $hasTenderSignal = fn (CountryUpdate $update): bool => CountryUpdateClassifier::isTender($update);
    $filteredUpdates = CountryUpdate::query()
        ->with(['country', 'journalistArticles.journalist'])
        ->when($activeStatusFilter === 'rejected', fn ($query) => $query->where('review_status', 'rejected'))
        ->when($activeStatusFilter !== 'rejected', function ($query) use ($activeStatusFilter) {
            $query->where('review_status', '!=', 'rejected');

            if (in_array($activeStatusFilter, ['unreviewed', 'approved'], true)) {
                $query->where('review_status', $activeStatusFilter);
            }
        })
        ->whereIn('country_id', $countryIds)
        ->when(in_array($publishedFilter, ['last30', 'last60', 'last120'], true), fn ($query) => $query->whereNotNull('publication_date')->where('publication_date', '>=', now()->subDays(match ($publishedFilter) {
            'last120' => 120,
            'last60' => 60,
            default => 30,
        })->toDateString()))
        ->when($retrievedFilter !== 'all', fn ($query) => $query->whereNotNull('retrieved_at')->where('retrieved_at', '>=', match ($retrievedFilter) {
            'last90' => now()->subDays(90),
            'last30' => now()->subDays(30),
            'last14' => now()->subDays(14),
            'last7' => now()->subDays(7),
            default => $reviewDeskCurrentStart,
        }))
        ->latest('retrieved_at')
        ->latest('publication_date')
        ->limit(5000)
        ->get()
        ->map(function (CountryUpdate $update) {
            $update->inferred_focus = CountryUpdateClassifier::inferFocus($update);

            return $update;
        })
        ->when($focus !== 'all', fn ($updates) => $updates->filter(fn (CountryUpdate $update) => $update->inferred_focus === $focus))
        ->when(in_array($activeTypeFilter, ['tenders', 'news'], true), fn ($updates) => $updates->filter(fn (CountryUpdate $update) => $activeTypeFilter === 'tenders' ? $hasTenderSignal($update) : ! $hasTenderSignal($update)))
        ->when($activeStatusFilter !== 'rejected', fn ($updates) => $updates->filter(fn (CountryUpdate $update) => ! CountryUpdateNoiseRules::isStaticReferenceUrl((string) $update->source_url)))
        ->unique(fn (CountryUpdate $update) => CountryUpdateDedupeRules::reviewDuplicateKey($update))
        ->sortBy([
            fn (CountryUpdate $update) => -1 * ($update->retrieved_at?->timestamp ?? 0),
            fn (CountryUpdate $update) => -1 * ($update->publication_date?->timestamp ?? 0),
            fn (CountryUpdate $update) => match (true) {
                $hasTenderSignal($update) && $update->inferred_focus === 'social_security' => 0,
                $hasTenderSignal($update) && $update->inferred_focus === 'hrms_tenders' => 1,
                $hasTenderSignal($update) && $update->inferred_focus === 'erms_tenders' => 2,
                $hasTenderSignal($update) && $update->inferred_focus === 'ebpc_tenders' => 3,
                default => 4,
            },
        ])
        ->values();

    $totalMatchingUpdates = $filteredUpdates->count();
    $updates = new LengthAwarePaginator(
        $filteredUpdates->forPage($page, $perPage)->values(),
        $totalMatchingUpdates,
        $perPage,
        $page,
        [
            'path' => $request->url(),
            'query' => $request->query(),
        ],
    );

    $latestRunIds = CountryMonitorRun::query()
        ->selectRaw('MAX(id) as id')
        ->whereIn('country_id', $countryIds)
        ->when($focus !== 'all', fn ($query) => $query->where('focus', $focus))
        ->groupBy('country_id');

    $latestRuns = CountryMonitorRun::query()
        ->with('country')
        ->whereIn('id', $latestRunIds)
        ->get()
        ->keyBy(fn (CountryMonitorRun $run) => (string) $run->country_id);
    $latestUpdatesByCountry = $filteredUpdates
        ->sortByDesc(fn (CountryUpdate $update) => $update->retrieved_at?->timestamp ?? 0)
        ->groupBy('country_id');
    $priorityFocus = $focus === 'all' ? 'social_security' : $focus;
    $pendingPriorities = Schema::hasTable('intelligence_monitor_priorities')
        ? IntelligenceMonitorPriority::query()
            ->where('status', 'pending')
            ->where('focus', $priorityFocus)
            ->pluck('requested_at', 'country_iso')
            ->mapWithKeys(fn ($requestedAt, string $iso) => [strtoupper($iso) => $requestedAt])
        : collect();

    $countrySearchRuns = collect();
    $countrySearchSummary = [
        'total' => 0,
        'tenders' => 0,
        'news' => 0,
        'rejected' => 0,
    ];

    if ($countrySearchQuery !== '') {
        $countrySearchRuns = CountryMonitorRun::query()
            ->with('country')
            ->whereIn('country_id', $countryIds)
            ->latest('finished_at')
            ->limit(60)
            ->get();

        $countrySearchSummary = [
            'total' => $totalMatchingUpdates,
            'tenders' => $filteredUpdates->filter(fn (CountryUpdate $update) => $hasTenderSignal($update))->count(),
            'news' => $filteredUpdates->filter(fn (CountryUpdate $update) => ! $hasTenderSignal($update))->count(),
            'rejected' => $filteredUpdates->where('review_status', 'rejected')->count(),
        ];
    }

    $countryStatus = $configuredCountries->map(function (array $country) use ($dbCountries, $latestRuns, $latestUpdatesByCountry, $priorityFocus, $pendingPriorities) {
        $iso = strtoupper((string) ($country['iso'] ?? ''));
        $dbCountry = $dbCountries->get($iso);
        $latestRun = $dbCountry ? $latestRuns->get((string) $dbCountry->id) : null;
        $latestUpdate = $dbCountry ? $latestUpdatesByCountry->get($dbCountry->id, collect())->first() : null;

        return [
            'name' => $country['name'],
            'iso' => $iso,
            'region' => $country['region_group'] ?? 'Unknown',
            'last_researched' => $latestRun?->finished_at ?? $latestUpdate?->retrieved_at,
            'next_scheduled_run' => 'Not scheduled',
            'last_update' => $latestUpdate?->retrieved_at,
            'items_found' => $latestRun?->items_found,
            'status' => $latestRun?->status ?? ($latestUpdate ? 'Before run log' : ($dbCountry ? 'No run log yet' : 'Not initialized')),
            'sources_checked' => $latestRun?->sources_checked ?? [],
            'priority_focus' => $priorityFocus,
            'priority_requested_at' => $pendingPriorities->get($iso),
        ];
    })->sortBy(['region', 'name'])->values();

    return view('sls.intelligence.review', [
        'updates' => $updates,
        'countryStatus' => $countryStatus,
        'reviewCountries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code']),
        'products' => $orderedProducts(),
        'focus' => $focus,
        'region' => $region,
        'publishedFilter' => $publishedFilter,
        'typeFilter' => $typeFilter,
        'retrievedFilter' => $retrievedFilter,
        'reviewDeskCurrentStart' => $reviewDeskCurrentStart,
        'displayLimit' => $perPage,
        'totalMatchingUpdates' => $totalMatchingUpdates,
        'focuses' => $focuses,
        'totalCountries' => $countryStatus->count(),
        'researchedCountries' => $countryStatus->filter(fn (array $country) => $country['last_researched'] !== null)->count(),
        'countrySearchQuery' => $countrySearchQuery,
        'countrySearchType' => $countrySearchType,
        'countrySearchStatus' => $countrySearchStatus,
        'countrySearchCountries' => $countrySearchCountries,
        'countrySearchRuns' => $countrySearchRuns,
        'countrySearchSummary' => $countrySearchSummary,
        'austinTz' => 'America/Chicago',
    ]);
})->name('sls.intelligence.review');

Route::get('/sls/intelligence/coverage', function (Request $request) use ($allMapCountries) {
    $focuses = config('country_intelligence.focuses', []);
    $focus = array_key_exists((string) $request->query('focus', 'social_security'), $focuses)
        ? (string) $request->query('focus', 'social_security')
        : 'social_security';
    $region = Str::of((string) $request->query('region', 'all'))->lower()->toString();

    $configuredCountries = $allMapCountries();

    if (in_array($region, ['africa', 'caribbean', 'asia', 'latin_america', 'north_america', 'europe'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => Str::lower($country['region_group']) === str_replace('_', ' ', $region))
            ->values();
    } elseif (in_array($region, ['africa_asia', 'asia_africa'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean', 'caribbean_africa_asia'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america', 'africa_asia_latin_america_caribbean'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america_north_america', 'africa_asia_caribbean_latin_america_north_america_europe', 'all_core_regions'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_latin_america', 'latin_america_africa_asia'], true)) {
        $configuredCountries = $configuredCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'latin america'], true))
            ->values();
    }

    $countryIsoCodes = $region === 'all'
        ? Country::query()->whereNotNull('iso_code')->pluck('iso_code')->all()
        : $configuredCountries->pluck('iso')->all();
    $dbCountries = Country::query()
        ->whereIn('iso_code', $countryIsoCodes)
        ->get()
        ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
    $countryIds = $dbCountries->pluck('id')->all();

    $latestRunIds = CountryMonitorRun::query()
        ->selectRaw('MAX(id) as id')
        ->whereIn('country_id', $countryIds)
        ->when($focus !== 'all', fn ($query) => $query->where('focus', $focus))
        ->groupBy('country_id');
    $latestRuns = CountryMonitorRun::query()
        ->with('country')
        ->whereIn('id', $latestRunIds)
        ->get()
        ->keyBy(fn (CountryMonitorRun $run) => (string) $run->country_id);
    $latestUpdatesByCountry = CountryUpdate::query()
        ->whereIn('country_id', $countryIds)
        ->where('review_status', '!=', 'rejected')
        ->latest('retrieved_at')
        ->limit(5000)
        ->get()
        ->groupBy('country_id');
    $pendingPriorities = Schema::hasTable('intelligence_monitor_priorities')
        ? IntelligenceMonitorPriority::query()
            ->where('status', 'pending')
            ->where('focus', $focus)
            ->pluck('requested_at', 'country_iso')
            ->mapWithKeys(fn ($requestedAt, string $iso) => [strtoupper($iso) => $requestedAt])
        : collect();

    $countryStatus = $configuredCountries->map(function (array $country) use ($dbCountries, $latestRuns, $latestUpdatesByCountry, $focus, $pendingPriorities) {
        $iso = strtoupper((string) ($country['iso'] ?? ''));
        $dbCountry = $dbCountries->get($iso);
        $latestRun = $dbCountry ? $latestRuns->get((string) $dbCountry->id) : null;
        $latestUpdate = $dbCountry ? $latestUpdatesByCountry->get($dbCountry->id, collect())->first() : null;

        return [
            'name' => $country['name'],
            'iso' => $iso,
            'region' => $country['region_group'] ?? 'Unknown',
            'last_researched' => $latestRun?->finished_at ?? $latestUpdate?->retrieved_at,
            'next_scheduled_run' => 'Not scheduled',
            'last_update' => $latestUpdate?->retrieved_at,
            'items_found' => $latestRun?->items_found,
            'status' => $latestRun?->status ?? ($latestUpdate ? 'Before run log' : ($dbCountry ? 'No run log yet' : 'Not initialized')),
            'sources_checked' => $latestRun?->sources_checked ?? [],
            'priority_focus' => $focus,
            'priority_requested_at' => $pendingPriorities->get($iso),
        ];
    })->sortBy(['region', 'name'])->values();

    return view('sls.intelligence.coverage', [
        'countryStatus' => $countryStatus,
        'focus' => $focus,
        'region' => $region,
        'focuses' => $focuses,
        'totalCountries' => $countryStatus->count(),
        'researchedCountries' => $countryStatus->filter(fn (array $country) => $country['last_researched'] !== null)->count(),
        'austinTz' => 'America/Chicago',
    ]);
})->name('sls.intelligence.coverage');


Route::get('/sls/intelligence/intake-log', function (Request $request) {
    $days = max(1, min(365, (int) $request->query('days', 7)));
    $limit = max(25, min(1000, (int) $request->query('limit', 500)));
    $filters = [
        'query' => trim((string) $request->query('q', '')),
        'country' => trim((string) $request->query('country', '')),
        'type' => in_array((string) $request->query('type', 'all'), ['all', 'news', 'tender'], true) ? (string) $request->query('type', 'all') : 'all',
        'status' => in_array((string) $request->query('status', 'all'), ['all', 'active', 'rejected', 'unreviewed'], true) ? (string) $request->query('status', 'all') : 'all',
        'days' => $days,
        'limit' => $limit,
    ];
    $focuses = config('country_intelligence.focuses', []);
    $reasonOptions = [
        'not_relevant' => 'Not relevant',
        'wrong_product' => 'Wrong product',
        'wrong_country' => 'Wrong country',
        'duplicate' => 'Duplicate',
        'old_or_awarded' => 'Old or awarded',
        'spam_or_scrape' => 'Scrape noise',
        'other' => 'Other',
    ];
    $storySummary = static function (CountryUpdate $update): string {
        $title = trim((string) ($update->title_english ?: $update->title ?: $update->title_original));
        $summary = trim((string) ($update->summary_english ?: $update->summary));
        $summary = preg_replace('/\[[^\]]+\]\s*/', '', $summary) ?? '';
        $summary = preg_replace('/\bAggregator lead\s*[-â€“]\s*/i', '', $summary) ?? '';
        $summary = preg_replace('/\bPotential (?:social security|HRMS|ERMS|EBPC|sector|procurement|tender)[^.]*\.?\s*/i', '', $summary) ?? '';
        $summary = preg_replace('/\bMatched themes?:[^.]*\.?\s*/i', '', $summary) ?? '';
        $summary = trim(preg_replace('/\s+/', ' ', $summary) ?? '');
        $sentences = collect(preg_split('/(?<=[.!?])\s+/', $summary) ?: [])->map(fn ($line) => trim((string) $line))->filter()->take(2)->values();
        if ($sentences->isNotEmpty() && ! Str::contains(Str::lower($sentences->implode(' ')), ['verify at official source', 'matched themes'])) {
            return Str::limit($sentences->implode(' '), 320, '');
        }
        return $title !== '' ? Str::limit('Captured item: ' . $title . '.', 320, '') : '';
    };
    $crawlerLabel = static function (CountryUpdate $update): string {
        $source = Str::lower(($update->source_name ?? '') . ' ' . ($update->source_url ?? ''));
        if (Str::contains($source, ['projects.worldbank.org', 'world bank procurement'])) {
            return 'World Bank procurement monitor';
        }
        if (Str::contains($source, ['iadb.org', 'idbdocs'])) {
            return 'IDB procurement monitor';
        }
        if (CountryUpdateClassifier::isTender($update)) {
            return 'Tender monitor';
        }
        return 'Social Security and Pension News monitor';
    };

    $updates = CountryUpdate::query()
        ->with('country')
        ->whereNotNull('retrieved_at')
        ->where('retrieved_at', '>=', now()->subDays($days))
        ->when($filters['query'] !== '', function ($query) use ($filters) {
            $q = $filters['query'];
            $query->where(function ($nested) use ($q) {
                $nested->where('title', 'like', '%' . $q . '%')
                    ->orWhere('title_english', 'like', '%' . $q . '%')
                    ->orWhere('title_original', 'like', '%' . $q . '%')
                    ->orWhere('summary', 'like', '%' . $q . '%')
                    ->orWhere('summary_english', 'like', '%' . $q . '%')
                    ->orWhere('source_name', 'like', '%' . $q . '%')
                    ->orWhere('source_url', 'like', '%' . $q . '%');
            });
        })
        ->when($filters['country'] !== '', function ($query) use ($filters) {
            $country = $filters['country'];
            $query->whereHas('country', function ($countryQuery) use ($country) {
                $countryQuery->where('name', 'like', '%' . $country . '%')
                    ->orWhere('iso_code', 'like', '%' . $country . '%');
            });
        })
        ->when($filters['status'] === 'active', fn ($query) => $query->where('review_status', '<>', 'rejected'))
        ->when($filters['status'] === 'rejected', fn ($query) => $query->where('review_status', 'rejected'))
        ->when($filters['status'] === 'unreviewed', fn ($query) => $query->where('review_status', 'unreviewed'))
        ->orderByDesc('retrieved_at')
        ->limit(1500)
        ->get()
        ->map(function (CountryUpdate $update) use ($focuses, $storySummary, $crawlerLabel) {
            $update->inferred_focus = CountryUpdateClassifier::inferFocus($update);
            $update->item_type = CountryUpdateClassifier::isTender($update) ? 'tender' : 'news';
            $update->focus_label = $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'Unclassified';
            $update->crawler_label = $crawlerLabel($update);
            $update->story_summary = $storySummary($update);
            $englishTitle = trim((string) $update->title_english);
            $originalTitle = trim((string) ($update->title_original ?: $update->title));
            $update->display_title = \App\Support\TitleLanguage::isUsableEnglishTitle($englishTitle, $originalTitle) ? $englishTitle : ($originalTitle ?: $update->title);

            return $update;
        })
        ->when($filters['type'] !== 'all', fn ($items) => $items->filter(fn (CountryUpdate $update) => $update->item_type === $filters['type']))
        ->take($limit)
        ->values();

    return view('sls.intelligence.intake-log', [
        'updates' => $updates,
        'filters' => $filters,
        'summary' => [
            'rows' => $updates->count(),
            'news' => $updates->where('item_type', 'news')->count(),
            'tenders' => $updates->where('item_type', 'tender')->count(),
            'dropped' => $updates->where('review_status', 'rejected')->count(),
            'unreviewed' => $updates->where('review_status', 'unreviewed')->count(),
        ],
        'crawlerCounts' => $updates->groupBy('crawler_label')->map->count()->sortDesc(),
        'reasonOptions' => $reasonOptions,
        'austinTz' => 'America/Chicago',
    ]);
})->name('sls.intelligence.intakeLog');
Route::get('/sls/intelligence/news-archive', function (Request $request) {
    $status = Str::of((string) $request->query('status', 'unread'))->lower()->toString();
    if (! in_array($status, ['unread', 'read', 'all'], true)) {
        $status = 'unread';
    }

    $days = max(1, min(365, (int) $request->query('days', 30)));
    $limit = max(25, min(1000, (int) $request->query('limit', 500)));
    $countryFilter = trim((string) $request->query('country', ''));
    $austinTz = 'America/Chicago';

    $query = CountryUpdate::query()
        ->with(['country', 'organizations'])
        ->where('review_status', '<>', 'rejected')
        ->whereNotNull('retrieved_at')
        ->where('retrieved_at', '>=', now()->subDays($days))
        ->when($status === 'unread', fn ($query) => $query->whereNull('archive_read_at'))
        ->when($status === 'read', fn ($query) => $query->whereNotNull('archive_read_at'))
        ->when($countryFilter !== '', function ($query) use ($countryFilter) {
            $query->whereHas('country', function ($countryQuery) use ($countryFilter) {
                $countryQuery->where('name', 'like', '%' . $countryFilter . '%')
                    ->orWhere('iso_code', 'like', '%' . $countryFilter . '%');
            });
        })
        ->orderByDesc('retrieved_at')
        ->limit(1500)
        ->get()
        ->map(function (CountryUpdate $update) {
            $update->inferred_focus = CountryUpdateClassifier::inferFocus($update);
            $update->item_type = CountryUpdateClassifier::isTender($update) ? 'tender' : 'news';

            return $update;
        })
        ->filter(fn (CountryUpdate $update) => $update->item_type === 'news')
        ->take($limit)
        ->values();

    $availableCountries = Country::query()
        ->whereIn('id', CountryUpdate::query()
            ->select('country_id')
            ->whereNotNull('retrieved_at')
            ->where('review_status', '<>', 'rejected'))
        ->orderBy('name')
        ->get(['id', 'name', 'iso_code']);

    return view('sls.intelligence.news-archive', [
        'updates' => $query,
        'availableCountries' => $availableCountries,
        'status' => $status,
        'days' => $days,
        'limit' => $limit,
        'countryFilter' => $countryFilter,
        'austinTz' => $austinTz,
        'focuses' => config('country_intelligence.focuses', []),
    ]);
})->name('sls.intelligence.newsArchive');

Route::post('/sls/intelligence/updates/{countryUpdate}/archive-read', function (CountryUpdate $countryUpdate) {
    abort_if($countryUpdate->review_status === 'rejected', 404);

    $countryUpdate->forceFill([
        'archive_read_at' => now(),
        'archive_read_by_user_id' => auth()->id(),
    ])->save();

    return back()->with('status', 'Story marked as read and cleared from the unread archive queue.');
})->name('sls.intelligence.updates.archiveRead');

Route::post('/sls/intelligence/updates/{countryUpdate}/archive-unread', function (CountryUpdate $countryUpdate) {
    abort_if($countryUpdate->review_status === 'rejected', 404);

    $countryUpdate->forceFill([
        'archive_read_at' => null,
        'archive_read_by_user_id' => null,
    ])->save();

    return back()->with('status', 'Story returned to the unread archive queue.');
})->name('sls.intelligence.updates.archiveUnread');
Route::get('/sls/intelligence/dropped', function (Request $request) {
    $reasonOptions = [
        'not_relevant' => 'Not relevant',
        'wrong_product' => 'Wrong product',
        'wrong_country' => 'Wrong country',
        'duplicate' => 'Duplicate',
        'old_or_awarded' => 'Old or awarded',
        'spam_or_scrape' => 'Scrape noise',
        'other' => 'Other',
    ];
    $focus = array_key_exists((string) $request->query('focus', 'all'), config('country_intelligence.focuses', []))
        ? (string) $request->query('focus')
        : 'all';
    $reason = array_key_exists((string) $request->query('reason', 'all'), $reasonOptions)
        ? (string) $request->query('reason')
        : 'all';
    $displayLimit = min(500, max(25, (int) $request->query('limit', 250)));

    $droppedUpdates = CountryUpdate::query()
        ->with('country')
        ->where('review_status', 'rejected')
        ->latest('rejected_at')
        ->latest('updated_at')
        ->limit(1000)
        ->get()
        ->map(function (CountryUpdate $update) {
            $update->inferred_focus = CountryUpdateClassifier::inferFocus($update);

            return $update;
        })
        ->when($focus !== 'all', fn ($updates) => $updates->filter(fn (CountryUpdate $update) => $update->inferred_focus === $focus))
        ->when($reason !== 'all', fn ($updates) => $updates->filter(fn (CountryUpdate $update) => $update->rejection_reason_code === $reason))
        ->take($displayLimit)
        ->values();

    $reasonCounts = CountryUpdate::query()
        ->where('review_status', 'rejected')
        ->get(['rejection_reason_code'])
        ->groupBy(fn (CountryUpdate $update) => $update->rejection_reason_code ?: 'other')
        ->map->count()
        ->sortDesc();

    return view('sls.intelligence.dropped', [
        'updates' => $droppedUpdates,
        'focus' => $focus,
        'reason' => $reason,
        'reasonOptions' => $reasonOptions,
        'reasonCounts' => $reasonCounts,
        'focuses' => config('country_intelligence.focuses'),
        'displayLimit' => $displayLimit,
    ]);
})->name('sls.intelligence.dropped');

Route::get('/sls/intelligence/search', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $params = [
        'country_q' => $query,
        'country_type' => Str::of((string) $request->query('type', 'all'))->lower()->toString(),
        'country_status' => Str::of((string) $request->query('status', 'all'))->lower()->toString(),
    ];

    if ($query === '') {
        $params = [];
    }

    return redirect(route('sls.intelligence.review', $params) . '#country-search');
})->name('sls.intelligence.search');

Route::get('/sls/intelligence/favorites', function (Request $request) {
    $status = Str::of((string) $request->query('status', 'open'))->lower()->toString();
    $austinTz = 'America/Chicago';
    $focuses = config('country_intelligence.focuses', []);

    $inferFocus = function (CountryUpdate $update): ?string {
        $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->source_name . ' ' . $update->source_url);
        $sourceText = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->source_name . ' ' . $update->source_url);

        if (Str::contains($text, ['social security', 'social insurance', 'social protection', 'national insurance', 'pension management information system', 'pension information system', 'pension administration', 'pension system', 'pension fund', 'provident fund', 'beneficiary registry', 'benefit payment system'])) {
            return 'social_security';
        }

        if (Str::contains($sourceText, ['enterprise risk management', 'risk management software', 'risk management system', 'erm software', 'erms', 'grc software', 'audit management system', 'internal audit software', 'risk register', 'operational risk management'])) {
            return 'erms_tenders';
        }

        if (Str::contains($text, ['budgeting software', 'budget management system', 'budget formulation system', 'financial planning software', 'forecasting software', 'mtef'])) {
            return 'ebpc_tenders';
        }

        if (Str::contains($text, ['hrms', 'hcm', 'payroll', 'human resource', 'hrmis', 'benefits administration', 'talent management', 'performance management'])) {
            return 'hrms_tenders';
        }

        if (Str::contains($text, ['telecom', 'oil and gas', 'postal', 'civil service', 'aviation', 'mining', 'banking', 'healthcare'])) {
            return 'sector_tenders';
        }

        return null;
    };

    $hasTenderSignal = function (CountryUpdate $update): bool {
        $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->summary . ' ' . $update->source_name . ' ' . $update->source_url);
        if (Str::contains($text, ['bond tender offer', 'cash tender offer', 'debt tender offer', 'notes tender offer', 'senior notes', 'exchange offer', 'repurchase offer', 'noteholders', 'bondholders', 'coupon', 'securities'])) {
            return false;
        }

        return Str::contains($text, [
            'tender',
            'procurement',
            'request for proposal',
            'request for bids',
            'expression of interest',
            'invitation to bid',
            'contract notice',
            'rfp',
        ]);
    };

    $inferFocus = fn (CountryUpdate $update): ?string => CountryUpdateClassifier::inferFocus($update);
    $hasTenderSignal = fn (CountryUpdate $update): bool => CountryUpdateClassifier::isTender($update);

    $favorites = CountryUpdate::query()
        ->with('country')
        ->where('review_status', '<>', 'rejected')
        ->where('is_favorite', true)
        ->when($status === 'open', fn ($query) => $query->whereNull('reminder_completed_at'))
        ->when($status === 'completed', fn ($query) => $query->whereNotNull('reminder_completed_at'))
        ->latest('favorited_at')
        ->latest('retrieved_at')
        ->limit(200)
        ->get()
        ->map(function (CountryUpdate $update) use ($inferFocus, $hasTenderSignal) {
            $update->inferred_focus = $inferFocus($update);
            $update->item_type = $hasTenderSignal($update) ? 'tender' : 'news';

            return $update;
        });

    return view('sls.intelligence.favorites', [
        'favorites' => $favorites,
        'status' => $status,
        'focuses' => $focuses,
        'austinTz' => $austinTz,
    ]);
})->name('sls.intelligence.favorites');

Route::post('/sls/intelligence/updates/{countryUpdate}/favorite', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'favorite_note' => ['nullable', 'string', 'max:5000'],
        'reminder_due_at' => ['nullable', 'date'],
        'return_to' => ['nullable', 'string', 'max:2000'],
    ]);

    $countryUpdate->update([
        'is_favorite' => true,
        'favorited_at' => $countryUpdate->favorited_at ?: now(),
        'favorite_note' => $data['favorite_note'] ?? null,
        'reminder_due_at' => filled($data['reminder_due_at'] ?? null) ? Carbon::parse($data['reminder_due_at']) : null,
        'reminder_completed_at' => null,
    ]);

    $returnTo = (string) ($data['return_to'] ?? '');

    return $returnTo !== '' && Str::startsWith($returnTo, url('/'))
        ? redirect($returnTo)->with('status', 'Saved as a favorite reminder.')
        : back()->with('status', 'Saved as a favorite reminder.');
})->name('sls.intelligence.updates.favorite');

Route::post('/sls/intelligence/updates/{countryUpdate}/favorite/complete', function (CountryUpdate $countryUpdate) {
    $countryUpdate->update([
        'reminder_completed_at' => now(),
    ]);

    return back()->with('status', 'Favorite reminder marked complete.');
})->name('sls.intelligence.updates.favorite.complete');

Route::post('/sls/intelligence/updates/{countryUpdate}/favorite/remove', function (CountryUpdate $countryUpdate) {
    $countryUpdate->update([
        'is_favorite' => false,
        'favorited_at' => null,
        'favorite_note' => null,
        'reminder_due_at' => null,
        'reminder_completed_at' => null,
    ]);

    return back()->with('status', 'Favorite removed.');
})->name('sls.intelligence.updates.favorite.remove');

Route::get('/sls/intelligence/updates/{countryUpdate}/source', function (CountryUpdate $countryUpdate) {
    $url = trim((string) $countryUpdate->source_url);

    abort_if($url === '' || ! preg_match('/^https?:\/\//i', $url), 404);

    $host = Str::of(parse_url($url, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString();

    if ($host === 'idbdocs.iadb.org') {
        try {
            $response = Http::timeout(25)
                ->connectTimeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
                    'Accept' => 'application/pdf,text/html,application/xhtml+xml,*/*',
                ])
                ->withoutVerifying()
                ->get($url);
        } catch (Throwable) {
            return redirect()->away($url);
        }

        if ($response->ok()) {
            $body = (string) $response->body();
            $contentType = (string) $response->header('Content-Type', 'application/pdf');

            if (Str::contains(Str::lower($contentType . ' ' . Str::substr($body, 0, 300)), ['multipart/form-data', 'content-disposition: form-data'])) {
                $pdfStart = strpos($body, '%PDF-');

                if ($pdfStart !== false) {
                    $body = substr($body, $pdfStart);
                    $endMarker = strrpos($body, '%%EOF');

                    if ($endMarker !== false) {
                        $body = substr($body, 0, $endMarker + 5);
                    }

                    $contentType = 'application/pdf';
                }
            }

            return response($body, 200, [
                'Content-Type' => Str::contains(Str::lower($contentType), 'pdf') ? 'application/pdf' : ($contentType ?: 'application/pdf'),
                'Content-Disposition' => 'inline; filename="idb-source-' . $countryUpdate->id . '.pdf"',
            ]);
        }
    }

    return redirect()->away($url);
})->name('sls.intelligence.updates.source');


$awardedCompanyStatusOptions = [
    'awarded_contractor' => 'Awarded contractor',
    'partner_candidate' => 'Partner candidate',
    'competitor' => 'Competitor',
    'existing_partner' => 'Existing partner',
    'ignore' => 'Ignore',
];

$saveAwardedCompany = function (array $data): TenderAwardedCompany {
    $country = null;
    if (! empty($data['country_id'])) {
        $country = Country::query()->find((int) $data['country_id']);
    }

    $update = null;
    if (! empty($data['country_update_id'])) {
        $update = CountryUpdate::query()->with(['country', 'opportunities.product'])->find((int) $data['country_update_id']);
        $country = $country ?: $update?->country;
    }

    $companyName = trim((string) $data['company_name']);
    $nameNormalized = Str::lower(trim(Str::ascii($companyName)));
    $countryName = $country?->name ?: trim((string) ($data['country_name'] ?? '')) ?: null;
    $countryIso = $country?->iso_code ?: strtoupper(trim((string) ($data['country_iso'] ?? ''))) ?: null;
    $websiteUrl = trim((string) ($data['website_url'] ?? '')) ?: null;
    $awardUrl = trim((string) ($data['award_url'] ?? '')) ?: ($update?->award_url ?: $update?->source_url);
    $contractTitle = trim((string) ($data['contract_title'] ?? '')) ?: ($update?->award_title ?: ($update?->title_english ?: $update?->title));
    $contractReference = trim((string) ($data['contract_reference'] ?? '')) ?: null;
    $organizationFingerprint = hash('sha256', 'awarded-contractor-org|' . ($countryIso ?: '') . '|' . $nameNormalized);

    $organization = MarketOrganization::query()
        ->where('source_fingerprint', $organizationFingerprint)
        ->when($countryIso, fn ($builder) => $builder->orWhere(fn ($nested) => $nested
            ->where('country_iso', $countryIso)
            ->where('name_normalized', $nameNormalized)))
        ->first();

    if (! $organization) {
        $organization = new MarketOrganization(['source_fingerprint' => $organizationFingerprint]);
    }

    $organization->fill([
        'product_id' => $data['product_id'] ?? $organization->product_id,
        'name' => $companyName,
        'name_normalized' => $nameNormalized,
        'organization_type' => $organization->organization_type ?: 'company',
        'industry' => $organization->industry ?: 'Tender contractor',
        'organization_subcategory' => $organization->organization_subcategory ?: 'Awarded contractor',
        'country' => $countryName,
        'country_raw' => $countryName,
        'country_iso' => $countryIso,
        'country_resolution_status' => $country ? 'resolved' : ($countryIso ? 'manual' : 'unknown'),
        'region' => $country?->region ?: $organization->region,
        'website_url' => $websiteUrl ?: $organization->website_url,
        'website_domain' => $websiteUrl ? strtolower((string) parse_url($websiteUrl, PHP_URL_HOST)) : $organization->website_domain,
        'organization_phone' => trim((string) ($data['phone'] ?? '')) ?: $organization->organization_phone,
        'status' => 'active',
        'lead_status' => $data['relationship_status'] ?? 'awarded_contractor',
        'lead_source' => 'tender award tracking',
        'notes' => trim(implode("\n\n", array_filter([
            $organization->notes,
            trim((string) ($data['notes'] ?? '')),
            $contractTitle ? 'Awarded contract: ' . $contractTitle : null,
        ]))) ?: null,
    ]);
    $organization->save();

    $email = trim((string) ($data['email'] ?? '')) ?: null;
    $phone = trim((string) ($data['phone'] ?? '')) ?: null;
    if ($email || $phone) {
        MarketOrganizationContact::query()->updateOrCreate(
            ['source_fingerprint' => hash('sha256', 'awarded-contractor-contact|' . $organization->id . '|' . ($email ?: $phone))],
            [
                'market_organization_id' => $organization->id,
                'contact_type' => 'award_contact',
                'person_name' => null,
                'job_title' => 'Award / procurement contact',
                'email' => $email,
                'phone' => $phone,
                'source_url' => $awardUrl,
                'context_excerpt' => $contractTitle,
                'verification_status' => 'manual',
                'extracted_at' => now(),
            ]
        );
    }

    $awardFingerprint = hash('sha256', implode('|', [
        'awarded-contractor',
        $update?->id ?: 'manual',
        $nameNormalized,
        Str::lower((string) $contractReference),
        Str::lower((string) $awardUrl),
    ]));

    return TenderAwardedCompany::query()->updateOrCreate(
        ['source_fingerprint' => $awardFingerprint],
        [
            'country_update_id' => $update?->id,
            'market_organization_id' => $organization->id,
            'product_id' => $data['product_id'] ?? $update?->opportunities?->first()?->product_id,
            'country_id' => $country?->id,
            'company_name' => $companyName,
            'country_name' => $countryName,
            'country_iso' => $countryIso,
            'email' => $email,
            'phone' => $phone,
            'website_url' => $websiteUrl,
            'contract_title' => $contractTitle,
            'contract_reference' => $contractReference,
            'contract_value' => trim((string) ($data['contract_value'] ?? '')) ?: null,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? ''))) ?: null,
            'award_date' => $data['award_date'] ?? $update?->award_date,
            'award_url' => $awardUrl,
            'source_name' => trim((string) ($data['source_name'] ?? '')) ?: ($update?->award_source_name ?: $update?->source_name),
            'contract_info' => trim((string) ($data['contract_info'] ?? '')) ?: ($update?->award_context ?: $update?->summary_english),
            'relationship_status' => $data['relationship_status'] ?? 'awarded_contractor',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]
    );
};

Route::get('/sls/intelligence/awarded-companies', function (Request $request) use ($orderedProducts, $awardedCompanyStatusOptions) {
    $filters = [
        'q' => trim((string) $request->query('q', '')),
        'country' => trim((string) $request->query('country', '')),
        'product_id' => trim((string) $request->query('product_id', '')),
        'status' => trim((string) $request->query('status', '')),
    ];

    $awardedCompanies = TenderAwardedCompany::query()
        ->with(['countryUpdate.country', 'organization', 'product', 'country'])
        ->when($filters['q'] !== '', function ($builder) use ($filters) {
            $q = $filters['q'];
            $builder->where(function ($nested) use ($q) {
                $nested->where('company_name', 'like', '%' . $q . '%')
                    ->orWhere('contract_title', 'like', '%' . $q . '%')
                    ->orWhere('contract_reference', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%')
                    ->orWhere('contract_info', 'like', '%' . $q . '%');
            });
        })
        ->when($filters['country'] !== '', fn ($builder) => $builder->where('country_name', $filters['country']))
        ->when($filters['product_id'] !== '', fn ($builder) => $builder->where('product_id', (int) $filters['product_id']))
        ->when($filters['status'] !== '', fn ($builder) => $builder->where('relationship_status', $filters['status']))
        ->orderByDesc('award_date')
        ->latest('updated_at')
        ->limit(500)
        ->get();

    return view('sls.intelligence.awarded-companies', [
        'awardedCompanies' => $awardedCompanies,
        'products' => $orderedProducts(),
        'countries' => TenderAwardedCompany::query()->whereNotNull('country_name')->distinct()->orderBy('country_name')->pluck('country_name'),
        'statusOptions' => $awardedCompanyStatusOptions,
        'filters' => $filters,
    ]);
})->name('sls.intelligence.awardedCompanies.index');

Route::get('/sls/intelligence/awarded-companies/create', function (Request $request) use ($orderedProducts, $awardedCompanyStatusOptions) {
    $update = null;
    if ($request->filled('country_update_id')) {
        $update = CountryUpdate::query()->with(['country', 'opportunities.product'])->find((int) $request->query('country_update_id'));
    }

    return view('sls.intelligence.awarded-company-create', [
        'update' => $update,
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code']),
        'products' => $orderedProducts(),
        'statusOptions' => $awardedCompanyStatusOptions,
    ]);
})->name('sls.intelligence.awardedCompanies.create');

Route::post('/sls/intelligence/awarded-companies', function (Request $request) use ($saveAwardedCompany) {
    $data = $request->validate([
        'country_update_id' => ['nullable', 'integer', 'exists:country_updates,id'],
        'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        'country_name' => ['nullable', 'string', 'max:120'],
        'country_iso' => ['nullable', 'string', 'max:8'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'company_name' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:80'],
        'website_url' => ['nullable', 'url', 'max:1000'],
        'contract_title' => ['nullable', 'string', 'max:500'],
        'contract_reference' => ['nullable', 'string', 'max:255'],
        'contract_value' => ['nullable', 'string', 'max:255'],
        'currency' => ['nullable', 'string', 'max:16'],
        'award_date' => ['nullable', 'date'],
        'award_url' => ['nullable', 'url', 'max:1500'],
        'source_name' => ['nullable', 'string', 'max:255'],
        'contract_info' => ['nullable', 'string', 'max:10000'],
        'relationship_status' => ['nullable', 'string', 'in:awarded_contractor,partner_candidate,competitor,existing_partner,ignore'],
        'notes' => ['nullable', 'string', 'max:5000'],
    ]);

    $award = $saveAwardedCompany($data);

    return redirect()->route('sls.intelligence.awardedCompanies.index')
        ->with('status', 'Awarded company saved: ' . $award->company_name . '.');
})->name('sls.intelligence.awardedCompanies.store');

Route::post('/sls/intelligence/awarded-companies/{awardedCompany}', function (Request $request, TenderAwardedCompany $awardedCompany) {
    $data = $request->validate([
        'relationship_status' => ['required', 'string', 'in:awarded_contractor,partner_candidate,competitor,existing_partner,ignore'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
    ]);

    $awardedCompany->update([
        'relationship_status' => $data['relationship_status'],
        'product_id' => $data['product_id'] ?? null,
    ]);

    if ($awardedCompany->organization) {
        $awardedCompany->organization->update([
            'lead_status' => $data['relationship_status'],
            'product_id' => $data['product_id'] ?? $awardedCompany->organization->product_id,
        ]);
    }

    return back()->with('status', 'Awarded company updated.');
})->name('sls.intelligence.awardedCompanies.update');
Route::get('/sls/intelligence/updates/{countryUpdate}/source-page', function (CountryUpdate $countryUpdate) use ($orderedProducts) {
    abort_if(blank($countryUpdate->source_url), 404);

    $countryUpdate->load('country', 'intelligenceContacts', 'intelligenceDocuments', 'opportunities.product', 'organizations');

    $mappingSelectorMode = in_array(config('sls.review_mapping_selector', 'multi_select_dropdown'), ['multi_select_dropdown', 'checkbox_list'], true)
        ? config('sls.review_mapping_selector', 'multi_select_dropdown')
        : 'multi_select_dropdown';

    return view('sls.intelligence.source-viewer', [
        'update' => $countryUpdate,
        'products' => $orderedProducts(),
        'mappedProductIds' => $countryUpdate->opportunities->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all(),
        'mappingSelectorMode' => $mappingSelectorMode,
        'dropReasonOptions' => [
            'not_relevant' => 'Not relevant',
            'wrong_product' => 'Wrong product',
            'wrong_country' => 'Wrong country',
            'duplicate' => 'Duplicate',
            'old_or_awarded' => 'Old or awarded',
            'spam_or_scrape' => 'Scrape noise',
            'other' => 'Other',
        ],
    ]);
})->name('sls.intelligence.updates.sourcePage');

Route::post('/sls/intelligence/updates/{countryUpdate}/product-map', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'product_ids' => ['nullable', 'array'],
        'product_ids.*' => ['integer', 'exists:products,id'],
    ]);

    $productIds = collect($data['product_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    DB::transaction(function () use ($countryUpdate, $productIds) {
        CountryUpdateOpportunity::query()
            ->where('country_update_id', $countryUpdate->id)
            ->delete();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            CountryUpdateOpportunity::query()->create([
                'country_update_id' => $countryUpdate->id,
                'product_id' => $productId,
                'issue_area' => $countryUpdate->topic?->name ?: $countryUpdate->country?->name ?: 'Intelligence item',
                'opportunity_stage' => 'mapped',
                'issue_summary' => Str::limit($countryUpdate->summary_english ?: $countryUpdate->summary ?: $countryUpdate->title_english ?: $countryUpdate->title, 1800, ''),
                'product_alignment' => ($product?->name ?? 'Selected product') . ' was mapped manually from the source viewer.',
            ]);
        }
    });

    return back()->with('status', $productIds->isEmpty()
        ? 'Product mappings cleared for this story.'
        : $productIds->count() . ' product mapping' . ($productIds->count() === 1 ? '' : 's') . ' saved for this story.');
})->name('sls.intelligence.updates.productMap');

Route::post('/sls/intelligence/updates/{countryUpdate}/mark-read', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'map_action_status' => ['required', 'string', 'in:no_action_required,action_taken,follow_up'],
        'map_action_note' => ['nullable', 'string', 'max:2000'],
        'reminder_due_at' => ['nullable', 'date'],
        'favorite_note' => ['nullable', 'string', 'max:5000'],
    ]);

    $payload = [
        'review_status' => $countryUpdate->review_status === 'rejected' ? 'rejected' : 'approved',
        'map_opened_at' => $countryUpdate->map_opened_at ?: now(),
        'map_opened_by_user_id' => $countryUpdate->map_opened_by_user_id ?: auth()->id(),
        'map_action_status' => $data['map_action_status'],
        'map_action_note' => $data['map_action_note'] ?? null,
        'map_processed_at' => now(),
        'map_processed_by_user_id' => auth()->id(),
    ];

    if ($data['map_action_status'] === 'follow_up') {
        $payload['is_favorite'] = true;
        $payload['favorited_at'] = $countryUpdate->favorited_at ?: now();
        $payload['favorite_note'] = $data['favorite_note'] ?? $countryUpdate->favorite_note ?? $data['map_action_note'] ?? null;
        $payload['reminder_due_at'] = filled($data['reminder_due_at'] ?? null)
            ? Carbon::parse($data['reminder_due_at'])
            : $countryUpdate->reminder_due_at;
        $payload['reminder_completed_at'] = null;
    }

    $countryUpdate->update($payload);

    return back()->with('status', $data['map_action_status'] === 'follow_up'
        ? 'Follow-up saved. It now appears in Favorites & Reminders.'
        : 'Story marked as processed. It remains filed under its country and tagged organizations.');
})->name('sls.intelligence.updates.markRead');

Route::get('/sls/intelligence/updates/{countryUpdate}/organization-search', function (Request $request, CountryUpdate $countryUpdate) {
    $query = trim((string) $request->query('q', ''));
    abort_if(Str::length($query) < 2, 422);

    $cleanText = fn (?string $value): string => SocialSecurityAdminNameCleaner::repairMojibake((string) $value);
    $normalize = fn (string $value): string => trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($cleanText($value)))));
    $queryNormalized = $normalize($query);
    $queryTokens = collect(explode(' ', $queryNormalized))->filter(fn ($token) => Str::length($token) >= 2)->values();
    $countryIso = $countryUpdate->country?->iso_code;
    $countryName = $countryUpdate->country?->name;

    $builder = MarketOrganization::query()
        ->select(['id', 'name', 'country', 'country_iso', 'industry', 'organization_type', 'website_url'])
        ->when($countryIso || $countryName, function ($builder) use ($countryIso, $countryName) {
            $builder->where(function ($nested) use ($countryIso, $countryName) {
                if ($countryIso) {
                    $nested->orWhere('country_iso', $countryIso);
                }
                if ($countryName) {
                    $nested->orWhere('country', $countryName);
                }
            });
        })
        ->limit(350);

    $countryCandidates = $builder->get();

    if ($countryCandidates->count() < 12) {
        $global = MarketOrganization::query()
            ->select(['id', 'name', 'country', 'country_iso', 'industry', 'organization_type', 'website_url'])
            ->where(function ($nested) use ($queryTokens, $query) {
                $nested->where('name', 'like', '%' . $query . '%');
                foreach ($queryTokens as $token) {
                    $nested->orWhere('name_normalized', 'like', '%' . $token . '%');
                    $nested->orWhere('name', 'like', '%' . $token . '%');
                }
            })
            ->limit(250)
            ->get();
        $countryCandidates = $countryCandidates->merge($global)->unique('id')->values();
    }

    $matches = $countryCandidates
        ->map(function (MarketOrganization $organization) use ($queryNormalized, $queryTokens, $normalize, $cleanText, $countryIso) {
            $displayName = $cleanText($organization->name);
            $displayCountry = $cleanText($organization->country);
            $name = $normalize($displayName);
            similar_text($queryNormalized, $name, $percent);
            $nameTokens = collect(explode(' ', $name))->filter(fn ($token) => Str::length($token) >= 2);
            $tokenHits = $queryTokens->filter(fn ($token) => $nameTokens->contains(fn ($candidate) => Str::contains($candidate, $token) || Str::contains($token, $candidate)))->count();
            $countryBoost = $countryIso && $organization->country_iso === $countryIso ? 18 : 0;
            $score = $percent + ($tokenHits * 14) + $countryBoost;

            return [
                'id' => $organization->id,
                'name' => $displayName,
                'country' => $displayCountry,
                'country_iso' => $organization->country_iso,
                'industry' => $organization->industry,
                'website_url' => $organization->website_url,
                'score' => round($score, 1),
            ];
        })
        ->sortByDesc('score')
        ->take(12)
        ->values();

    return response()->json($matches);
})->name('sls.intelligence.updates.organizationSearch');

Route::post('/sls/intelligence/updates/{countryUpdate}/organizations', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'market_organization_id' => ['nullable', 'integer', 'exists:market_organizations,id'],
        'organization_name' => ['nullable', 'string', 'max:255'],
        'context' => ['nullable', 'string', 'max:80'],
    ]);

    $organization = null;
    if (! empty($data['market_organization_id'])) {
        $organization = MarketOrganization::query()->find((int) $data['market_organization_id']);
    }

    if (! $organization) {
        $name = SocialSecurityAdminNameCleaner::repairMojibake(trim((string) ($data['organization_name'] ?? '')));
        abort_if($name === '', 422, 'Choose an organization or enter a new organization name.');
        $country = $countryUpdate->country;
        $fingerprint = hash('sha256', 'manual-story-org|' . ($country?->iso_code ?? '') . '|' . Str::lower(Str::ascii($name)));
        $organization = MarketOrganization::query()->firstOrCreate(
            ['source_fingerprint' => $fingerprint],
            [
                'name' => $name,
                'name_normalized' => Str::lower(trim(Str::ascii($name))),
                'organization_type' => 'government_agency',
                'industry' => 'Other',
                'country' => $country?->name,
                'country_raw' => $country?->name,
                'country_iso' => $country?->iso_code,
                'country_resolution_status' => $country ? 'resolved' : 'unknown',
                'region' => $country?->region,
                'status' => 'active',
                'lead_status' => 'researching',
                'lead_source' => 'manual intelligence story tag',
                'notes' => 'Created from source viewer while tagging intelligence item #' . $countryUpdate->id . '.',
            ]
        );
    }

    CountryUpdateOrganization::query()->updateOrCreate(
        [
            'country_update_id' => $countryUpdate->id,
            'market_organization_id' => $organization->id,
        ],
        ['context' => $data['context'] ?? 'mentioned']
    );

    return back()->with('status', 'Story tagged to ' . SocialSecurityAdminNameCleaner::repairMojibake((string) $organization->name) . '.');
})->name('sls.intelligence.updates.organizations.store');

Route::delete('/sls/intelligence/updates/{countryUpdate}/organizations/{organization}', function (CountryUpdate $countryUpdate, MarketOrganization $organization) {
    $countryUpdate->organizations()->detach($organization->id);

    return back()->with('status', 'Organization tag removed.');
})->name('sls.intelligence.updates.organizations.destroy');

Route::post('/sls/intelligence/updates/{countryUpdate}/extract-contacts', function (CountryUpdate $countryUpdate, SourceContactExtractionService $extractor) {
    $result = $extractor->extractForUpdate($countryUpdate->load('country'));

    $message = 'Contact extraction checked ' . $result['documents_checked'] . ' document(s), found '
        . $result['contacts_found'] . ' email reference(s), created ' . $result['contacts_created']
        . ', updated ' . $result['contacts_updated'] . '.';

    if (! empty($result['errors'])) {
        $message .= ' Some documents could not be read; see logs for details.';
    }

    return back()->with('status', $message);
})->name('sls.intelligence.updates.extractContacts');

Route::post('/sls/intelligence/updates/{countryUpdate}/check-award', function (CountryUpdate $countryUpdate, TenderAwardLookupService $awardLookup) {
    $result = $awardLookup->check($countryUpdate);

    $message = match ($result['status']) {
        'awarded' => 'Award record found and linked to this tender.',
        'not_found' => 'Award check completed. No award record was found yet.',
        default => 'Award check completed, but this source does not have a configured award lookup yet.',
    };

    return back()->with('status', $message);
})->name('sls.intelligence.updates.checkAward');

Route::get('/sls/intelligence/contacts', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $country = trim((string) $request->query('country', ''));
    $sourceDocumentId = (int) $request->query('source_document_id', 0);
    $sourceDocument = $sourceDocumentId > 0 ? SourceDocument::find($sourceDocumentId) : null;

    $contacts = IntelligenceContact::query()
        ->with('country', 'countryUpdate', 'intelligenceDocument', 'sourceDocument')
        ->when($sourceDocumentId > 0, fn ($builder) => $builder->where('source_document_id', $sourceDocumentId))
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($nested) use ($query) {
                $nested->where('email', 'like', '%' . $query . '%')
                    ->orWhere('person_name', 'like', '%' . $query . '%')
                    ->orWhere('organization', 'like', '%' . $query . '%')
                    ->orWhere('document_title', 'like', '%' . $query . '%')
                    ->orWhere('context_excerpt', 'like', '%' . $query . '%');
            });
        })
        ->when($country !== '', function ($builder) use ($country) {
            $builder->whereHas('country', fn ($countryQuery) => $countryQuery->where('name', 'like', '%' . $country . '%'));
        })
        ->latest('extracted_at')
        ->limit(300)
        ->get();

    return view('sls.intelligence.contacts', [
        'contacts' => $contacts,
        'query' => $query,
        'country' => $country,
        'sourceDocumentId' => $sourceDocumentId,
        'sourceDocument' => $sourceDocument,
    ]);
})->name('sls.intelligence.contacts');

Route::get('/sls/survey/universities', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $status = trim((string) $request->query('status', 'all'));

    $targets = UniversitySurveyTarget::query()
        ->withCount('contacts')
        ->when($query !== '', fn ($builder) => $builder->where(function ($nested) use ($query) {
            $nested->where('name', 'like', '%' . $query . '%')
                ->orWhere('country', 'like', '%' . $query . '%')
                ->orWhere('website_url', 'like', '%' . $query . '%')
                ->orWhere('domain', 'like', '%' . $query . '%');
        }))
        ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
        ->orderByRaw("FIELD(status, 'pending', 'error', 'crawled', 'disabled')")
        ->orderBy('country')
        ->orderBy('name')
        ->limit(250)
        ->get();

    return view('sls.survey.universities', [
        'targets' => $targets,
        'query' => $query,
        'status' => $status,
        'summary' => [
            'targets' => UniversitySurveyTarget::count(),
            'pending' => UniversitySurveyTarget::where('status', 'pending')->count(),
            'contacts' => \App\Models\UniversitySurveyContact::count(),
        ],
    ]);
})->name('sls.survey.universities');

Route::post('/sls/survey/universities/import', function (Request $request, UniversitySurveyCrawlerService $crawler) {
    $data = $request->validate([
        'targets' => ['required', 'string'],
    ]);

    $result = $crawler->importTargets($data['targets']);

    return back()->with('status', 'University targets imported. Created ' . $result['created'] . ', updated ' . $result['updated'] . '.');
})->name('sls.survey.universities.import');

Route::post('/sls/survey/universities/{target}/crawl', function (UniversitySurveyTarget $target, UniversitySurveyCrawlerService $crawler) {
    $result = $crawler->crawlTarget($target, 14);

    return back()->with('status', 'Crawled ' . $target->name . ': checked ' . $result['pages_checked'] . ' page(s), found/updated ' . $result['contacts_found'] . ' contact reference(s).');
})->name('sls.survey.universities.crawl');

Route::get('/sls/survey/university-contacts', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $role = trim((string) $request->query('role', 'all'));

    $contacts = UniversitySurveyContact::query()
        ->with('target')
        ->when($query !== '', fn ($builder) => $builder->where(function ($nested) use ($query) {
            $nested->where('email', 'like', '%' . $query . '%')
                ->orWhere('person_name', 'like', '%' . $query . '%')
                ->orWhere('job_title', 'like', '%' . $query . '%')
                ->orWhere('organization', 'like', '%' . $query . '%')
                ->orWhereHas('target', fn ($targetQuery) => $targetQuery
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('country', 'like', '%' . $query . '%'));
        }))
        ->when($role !== 'all', fn ($builder) => $builder->where('role_category', $role))
        ->orderByRaw("FIELD(role_category, 'leadership', 'hr', 'it', 'general')")
        ->latest('found_at')
        ->limit(500)
        ->get();

    return view('sls.survey.contacts', [
        'contacts' => $contacts,
        'query' => $query,
        'role' => $role,
    ]);
})->name('sls.survey.contacts');

$marketOrganizationTypes = [
    'school_district' => 'School district / K-12',
    'university' => 'University / higher education',
    'airlines' => 'Airlines',
    'utility' => 'Utility company',
    'financial_institution' => 'Financial Institution',
    'pension_fund' => 'Pension Funds',
    'oil_gas_company' => 'Oil & gas company',
    'national_oil_company' => 'National Oil Companies',
    'telecom' => 'Telecom operator',
    'government_agency' => 'Government agency',
    'procurement_portal' => 'Procurement portal',
    'donor_development_bank' => 'Donor / development bank',
    'private_company' => 'Private company',
    'other' => 'Other organization',
];

$marketIndustries = [
    'k12_education' => 'K-12 education',
    'higher_education' => 'Higher education',
    'utilities' => 'Utilities',
    'oil_gas' => 'Oil & gas',
    'telecom' => 'Telecom',
    'government' => 'Government / civil service',
    'healthcare' => 'Healthcare',
    'postal' => 'Postal service',
    'banking_finance' => 'Banking & finance',
    'social_security' => 'Social security',
    'pensions_retirement' => 'Pensions / retirement',
    'mining' => 'Mining',
    'transport_aviation' => 'Transport / aviation',
    'procurement' => 'Procurement marketplace',
    'development' => 'Development / donor',
    'other' => 'Other',
];

$marketOrganizationSubcategories = [
    'school_district' => 'School Districts',
    'commercial_bank' => 'Commercial Bank',
    'local_bank' => 'Local Bank',
    'state_bank' => 'State Bank',
    'foreign_bank' => 'Foreign Bank',
    'microfinance' => 'Microfinance',
    'central_bank' => 'Central Bank',
    'investment_bank' => 'Investment Bank',
    'development_bank' => 'Development Bank',
    'banking_other' => 'Banking / Finance Other',
    'pension_fund' => 'Pension Funds',
    'pension_fund_administrator' => 'Pension Fund Administrators',
    'closed_pension_fund_administrator' => 'Closed Pension Fund Administrators',
    'pension_fund_custodian' => 'Pension Fund Custodians',
    'beneficiary_fund' => 'Beneficiary Funds',
    'external_fund' => 'External Funds',
    'individual_retirement_fund' => 'Individual Retirement Funds',
    'preservation_fund' => 'Preservation Funds',
    'provident_fund' => 'Provident Funds',
    'umbrella_fund' => 'Umbrella Funds',
    'drilling_contractors' => 'Drilling Contractors',
    'national_oil_company' => 'National Oil Companies',
    'oilfield_services' => 'Oilfield Services',
    'airline_operator' => 'Airline Operators',
    'university' => 'Universities',
    'social_security_administration' => 'Social Security Administrations',
    'procurement_portal' => 'Procurement Portals',
    'other' => 'Other',
];

$marketCrawlerTypes = [
    'organization_discovery' => 'Organization discovery',
    'procurement_monitor' => 'Procurement monitor',
    'leadership_contact_crawler' => 'Leadership/contact crawler',
    'university_contact_crawler' => 'University contact crawler',
    'school_district_contact_crawler' => 'School district contact crawler',
    'utility_contact_crawler' => 'Utility contact crawler',
    'oil_gas_contact_crawler' => 'Oil & gas contact crawler',
    'social_security_contact_crawler' => 'Social security organization crawler',
    'national_procurement_crawler' => 'National procurement crawler',
    'bank_domain_crawler' => 'Bank domain enrichment crawler',
];

$marketLeadStatuses = [
    'unqualified' => 'Unqualified',
    'researching' => 'Researching',
    'target' => 'Target',
    'contacted' => 'Contacted',
    'engaged' => 'Engaged',
    'opportunity' => 'Opportunity',
    'proposal' => 'Proposal',
    'customer' => 'Customer',
    'not_relevant' => 'Not relevant',
];

$sanctionedCountryIsos = ['CU', 'IR', 'KP'];
$blockedOrganizationStatuses = ['sanctioned', 'duplicate'];

$seedMarketCrawlers = function () {
    if (! Schema::hasTable('market_crawlers')) {
        return;
    }

    collect([
        ['name' => 'University procurement and leadership crawler', 'crawler_key' => 'university_procurement_leadership', 'crawler_type' => 'university_contact_crawler', 'description' => 'Finds university procurement, leadership, HR, IT, and published institutional contact details.'],
        ['name' => 'School district procurement and leadership crawler', 'crawler_key' => 'school_district_procurement_leadership', 'crawler_type' => 'school_district_contact_crawler', 'description' => 'Finds school district procurement, superintendent, HR, IT, finance, and public contact details.'],
        ['name' => 'National procurement portal crawler', 'crawler_key' => 'national_procurement_portal', 'crawler_type' => 'national_procurement_crawler', 'description' => 'Checks official country procurement portals and records registration requirements.'],
        ['name' => 'Social security organization crawler', 'crawler_key' => 'social_security_organization', 'crawler_type' => 'social_security_contact_crawler', 'description' => 'Finds official social security organization websites, media/press pages, procurement pages, leadership, and public contacts.'],
        ['name' => 'Competitor and bidder enrichment crawler', 'crawler_key' => 'competitor_bidder_enrichment', 'crawler_type' => 'leadership_contact_crawler', 'description' => 'Enriches competitor and bidder accounts created from tender documents by checking websites, procurement/supplier pages, leadership, HR, IT, news, and public contacts.'],
        ['name' => 'Utility procurement and leadership crawler', 'crawler_key' => 'utility_procurement_leadership', 'crawler_type' => 'utility_contact_crawler', 'description' => 'Finds utility company procurement pages, executive leadership, HR, IT, and public contacts.'],
        ['name' => 'Oil and gas procurement crawler', 'crawler_key' => 'oil_gas_procurement_leadership', 'crawler_type' => 'oil_gas_contact_crawler', 'description' => 'Finds oil and gas company procurement, supplier, leadership, HR, IT, and public contact details.'],
        ['name' => 'Bank domain enrichment crawler', 'crawler_key' => 'bank_domain_enrichment', 'crawler_type' => 'bank_domain_crawler', 'description' => 'Guesses and verifies official bank domains, then queues accepted bank sites for press and announcements crawling.'],
    ])->each(fn (array $crawler) => MarketCrawler::firstOrCreate(
        ['crawler_key' => $crawler['crawler_key']],
        $crawler + ['is_enabled' => true],
    ));

    $socialSecurityCrawler = MarketCrawler::query()->where('crawler_key', 'social_security_organization')->first();

    if ($socialSecurityCrawler) {
        Country::query()
            ->whereNotNull('social_security_administration_name')
            ->where('social_security_administration_name', '<>', '')
            ->get()
            ->each(function (Country $country) use ($socialSecurityCrawler) {
                $iso = Str::upper((string) $country->iso_code);

                if (IntelligenceSource::query()->where('source_class', 'social_security_admin')->where('country_iso', $iso)->exists()) {
                    return;
                }

                $name = trim((string) $country->social_security_administration_name);

                MarketOrganization::updateOrCreate(
                    [
                        'source_fingerprint' => 'social-security-admin:' . $iso,
                    ],
                    [
                        'market_crawler_id' => $socialSecurityCrawler->id,
                        'name' => $name,
                        'name_normalized' => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString(),
                        'organization_type' => 'government_agency',
                        'industry' => 'social_security',
                        'organization_subcategory' => 'social_security_administration',
                        'country' => $country->name,
                        'country_raw' => $country->name,
                        'country_iso' => Str::upper((string) $country->iso_code),
                        'country_resolution_status' => 'resolved',
                        'region' => $country->region,
                        'status' => 'active',
                        'lead_status' => 'researching',
                        'lead_source' => 'country_social_security_directory',
                        'notes' => 'Seeded from country social security administration directory for crawler discovery.',
                        'last_crawler_name' => $socialSecurityCrawler->name,
                    ],
                );
            });

        IntelligenceSource::query()
            ->where('source_class', 'social_security_admin')
            ->whereNotNull('country_iso')
            ->where('country_iso', '<>', '')
            ->get()
            ->each(function (IntelligenceSource $source) use ($socialSecurityCrawler) {
                $iso = Str::upper((string) $source->country_iso);
                $country = Country::query()->where('iso_code', $iso)->first();
                $name = trim((string) $source->name);

                if ($name === '') {
                    return;
                }

                $url = trim((string) $source->url);
                $domain = trim((string) ($source->domain ?: parse_url($url, PHP_URL_HOST)));

                MarketOrganization::updateOrCreate(
                    [
                        'source_fingerprint' => 'social-security-source:' . $source->id,
                    ],
                    [
                        'market_crawler_id' => $socialSecurityCrawler->id,
                        'name' => $name,
                        'name_normalized' => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString(),
                        'organization_type' => 'government_agency',
                        'industry' => 'social_security',
                        'organization_subcategory' => 'social_security_administration',
                        'country' => $country?->name ?: $iso,
                        'country_raw' => $country?->name ?: $iso,
                        'country_iso' => $iso,
                        'country_resolution_status' => 'resolved',
                        'region' => $country?->region ?: $source->region,
                        'website_url' => $url ?: null,
                        'website_domain' => $domain ? Str::of($domain)->lower()->replace('www.', '')->toString() : null,
                        'status' => 'active',
                        'lead_status' => 'researching',
                        'lead_source' => 'managed_social_security_source',
                        'notes' => 'Seeded from managed social security source coverage. Access method: ' . ($source->access_method ?: 'not set'),
                        'last_crawler_name' => $socialSecurityCrawler->name,
                    ],
                );
            });
    }

    $managedSocialSecuritySourceIsos = MarketOrganization::query()
        ->where('lead_source', 'managed_social_security_source')
        ->whereNotNull('website_url')
        ->where('website_url', '<>', '')
        ->pluck('country_iso')
        ->map(fn ($iso) => Str::upper(trim((string) $iso)))
        ->filter()
        ->unique()
        ->values();

    if ($managedSocialSecuritySourceIsos->isNotEmpty()) {
        MarketOrganization::query()
            ->where('lead_source', 'country_social_security_directory')
            ->whereIn('country_iso', $managedSocialSecuritySourceIsos->all())
            ->update([
                'status' => 'duplicate',
                'lead_status' => 'not_relevant',
                'next_crawl_at' => null,
                'last_error' => 'Covered by managed social security source record for this country.',
            ]);
    }

    collect([
        'Instituto Nacional de Seguranca Social' => 'Instituto Nacional de SeguranÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§a Social',
        'Caisse Nationale de Securite Sociale du Burkina Faso' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale du Burkina Faso',
        'Institut National de Securite Sociale du Burundi' => 'Institut National de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale du Burundi',
        'Caisse Nationale de Securite Sociale du Benin' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale du BÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©nin',
        'Caisse Nationale de Securite Sociale RDC' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale RDC',
        'Caisse Nationale de Prevoyance Sociale Cote d Ivoire' => "Caisse Nationale de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale CÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â´te d'Ivoire",
        'Institution de Prevoyance Sociale - CGRAE' => 'Institution de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale - CGRAE',
        'Caisse Nationale de Prevoyance Sociale du Cameroun' => 'Caisse Nationale de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale du Cameroun',
        'Instituto Nacional de Previdencia Social Cabo Verde' => 'Instituto Nacional de PrevidÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âªncia Social Cabo Verde',
        'Caisse Nationale de Securite Sociale Djibouti' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Djibouti',
        'Caisse Nationale de Securite Sociale du Gabon' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale du Gabon',
        'Caisse Nationale de Securite Sociale de Guinee' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale de GuinÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©e',
        'Caisse Nationale de Securite Sociale Maroc' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Maroc',
        'Caisse Nationale de Prevoyance Sociale Madagascar' => 'Caisse Nationale de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale Madagascar',
        'Institut National de Prevoyance Sociale Mali' => 'Institut National de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale Mali',
        'Caisse Nationale de Securite Sociale Mauritanie' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Mauritanie',
        'Instituto Nacional de Seguranca Social Mocambique' => 'Instituto Nacional de SeguranÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§a Social MoÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ambique',
        'Caisse Nationale de Securite Sociale Niger' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Niger',
        'Caisse Nationale de Securite Sociale Togo' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Togo',
        'Caisse Nationale de Retraite et de Prevoyance Sociale Tunisie' => 'Caisse Nationale de Retraite et de PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©voyance Sociale Tunisie',
        'Caisse Nationale de Securite Sociale Tunisie' => 'Caisse Nationale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale Tunisie',
        'Sociale Verzekeringsbank Curacao' => 'Sociale Verzekeringsbank CuraÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ao',
        'Superintendencia de Pensiones Republica Dominicana' => 'Superintendencia de Pensiones RepÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica Dominicana',
        'Tesoreria de la Seguridad Social' => 'TesorerÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­a de la Seguridad Social',
        'Caisse Generale de Securite Sociale de la Guadeloupe' => 'Caisse GÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©nÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale de la Guadeloupe',
        'Office National d Assurance Vieillesse' => "Office National d'Assurance Vieillesse",
        'Caisse Generale de Securite Sociale de la Martinique' => 'Caisse GÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©nÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rale de SÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© Sociale de la Martinique',
        'Autoridad de Fiscalizacion y Control de Pensiones y Seguros' => 'Autoridad de FiscalizaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n y Control de Pensiones y Seguros',
        'Gestora Publica Bolivia' => 'Gestora PÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºblica Bolivia',
        'Instituto de Prevision Social Chile' => 'Instituto de PrevisiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n Social Chile',
        'Instituto Hondureno de Seguridad Social' => 'Instituto HondureÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±o de Seguridad Social',
        'ISSSTE Mexico' => 'ISSSTE MÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©xico',
        'Instituto Nicaraguense de Seguridad Social' => 'Instituto NicaragÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¼ense de Seguridad Social',
        'Caja de Seguro Social Panama' => 'Caja de Seguro Social PanamÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡',
        'Instituto de Prevision Social Paraguay' => 'Instituto de PrevisiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n Social Paraguay',
        'Instituto Salvadoreno del Seguro Social' => 'Instituto SalvadoreÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±o del Seguro Social',
        'Banco de Prevision Social Uruguay' => 'Banco de PrevisiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n Social Uruguay',
    ])->each(function (string $displayName, string $plainName) {
        MarketOrganization::query()
            ->where('name', $plainName)
            ->where('organization_type', 'government_agency')
            ->where('industry', 'government')
            ->where('organization_subcategory', 'social_security_administration')
            ->update([
                'name' => $displayName,
                'name_normalized' => Str::of(Str::ascii($displayName))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString(),
            ]);

        IntelligenceSource::query()
            ->where('name', $plainName)
            ->where('source_class', 'social_security_admin')
            ->update(['name' => $displayName]);
    });

    MarketOrganization::query()
        ->whereIn('country_iso', ['CU', 'IR', 'KP'])
        ->update([
            'status' => 'sanctioned',
            'lead_status' => 'not_relevant',
            'next_crawl_at' => null,
            'last_error' => 'Blocked as sanctioned country. Do not run discovery/crawl.',
        ]);
};

$normalizeCountryForOrganization = function (?string $value, ?string $explicitIso = null): array {
    static $dbCountries = null;
    static $countryNameIndex = null;

    $raw = trim((string) ($value ?? ''));
    $iso = $explicitIso ? Str::upper(trim($explicitIso)) : null;
    $normalizeName = fn (string $name): string => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

    if ($dbCountries === null) {
        $dbCountries = Country::query()
            ->whereNotNull('iso_code')
            ->get(['name', 'iso_code', 'region'])
            ->mapWithKeys(fn (Country $country) => [Str::upper((string) $country->iso_code) => $country]);
        $countryNameIndex = $dbCountries->mapWithKeys(fn (Country $country) => [$normalizeName((string) $country->name) => $country]);
    }

    $aliases = [
        'cote d ivoire' => 'CI',
        'ivory coast' => 'CI',
        'uae' => 'AE',
        'united arab emirates' => 'AE',
        'uk' => 'GB',
        'united kingdom' => 'GB',
        'usa' => 'US',
        'united states' => 'US',
        'democratic republic of the congo' => 'CD',
        'dr congo' => 'CD',
        'republic of the congo' => 'CG',
        'congo brazzaville' => 'CG',
    ];

    if ($raw === '' && ! $iso) {
        return [
            'raw' => null,
            'name' => 'Country pending',
            'iso' => null,
            'region' => null,
            'status' => 'missing',
        ];
    }

    if (! $iso && Str::length($raw) === 2 && ctype_alpha($raw)) {
        $iso = Str::upper($raw);
    }

    if (! $iso && $raw !== '') {
        $normalizedRaw = $normalizeName($raw);
        $iso = $aliases[$normalizedRaw] ?? $countryNameIndex->get($normalizedRaw)?->iso_code;
    }

    $name = null;
    $region = null;

    if ($iso) {
        $country = $dbCountries->get($iso);
        $name = $country?->name;
        $region = $country?->region;

        if (! $name && class_exists(\Locale::class)) {
            $localeName = \Locale::getDisplayRegion('-' . $iso, 'en');
            $name = $localeName !== '' ? $localeName : null;
        }
    }

    if (! $name && $raw !== '') {
        $normalizedRaw = $normalizeName($raw);
        if (isset($aliases[$normalizedRaw])) {
            $iso = $aliases[$normalizedRaw];
            $country = $dbCountries->get($iso);
            $name = $country?->name ?: (class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $iso, 'en') : null);
            $region = $country?->region;
        } else {
            $name = $raw;
        }
    }

    return [
        'raw' => $raw !== '' ? $raw : null,
        'name' => $name ?: 'Unknown',
        'iso' => $iso,
        'region' => $region,
        'status' => ($iso || ($name && $name !== 'Unknown')) ? 'resolved' : 'unresolved',
    ];
};

Route::get('/sls/organizations', function (Request $request) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $marketCrawlerTypes, $marketLeadStatuses, $seedMarketCrawlers, $normalizeCountryForOrganization) {
    $seedMarketCrawlers();

    $query = trim((string) $request->query('q', ''));
    $type = (string) $request->query('type', 'all');
    $industry = (string) $request->query('industry', 'all');
    $subcategory = (string) $request->query('subcategory', 'all');
    $country = trim((string) $request->query('country', ''));
    $crawlerId = (string) $request->query('crawler', 'all');
    $leadStatus = (string) $request->query('lead_status', 'all');
    $countryStatus = (string) $request->query('country_status', 'all');
    $studentsFrom = trim((string) $request->query('students_from', ''));
    $studentsTo = trim((string) $request->query('students_to', ''));
    $schoolDistrictState = trim((string) $request->query('school_state', ''));
    $usStateCodes = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
        'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'district of columbia' => 'DC',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA',
        'maine' => 'ME', 'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
        'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
        'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
        'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT',
        'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
    ];
    $schoolDistrictStateCode = $schoolDistrictState !== ''
        ? ($usStateCodes[Str::lower($schoolDistrictState)] ?? Str::upper($schoolDistrictState))
        : '';

    $organizations = MarketOrganization::query()
        ->with(['crawler', 'contacts'])
        ->withCount(['contacts', 'activities', 'tasks', 'communications'])
        ->when($query !== '', fn ($builder) => $builder->where(function ($inner) use ($query) {
            $inner->where('name', 'like', '%' . $query . '%')
                ->orWhere('website_domain', 'like', '%' . $query . '%')
                ->orWhere('notes', 'like', '%' . $query . '%');
        }))
        ->when($type !== 'all', fn ($builder) => $builder->where('organization_type', $type))
        ->when($industry !== 'all', fn ($builder) => $builder->where('industry', $industry))
        ->when($subcategory !== 'all', fn ($builder) => $builder->where('organization_subcategory', $subcategory))
        ->when($country !== '', fn ($builder) => $builder->where(function ($inner) use ($country, $normalizeCountryForOrganization) {
            $normalizedCountry = $normalizeCountryForOrganization($country);
            $inner->where('country', 'like', '%' . $country . '%')
                ->orWhere('country_raw', 'like', '%' . $country . '%')
                ->orWhere('country_iso', 'like', '%' . $country . '%')
                ->orWhere('country', 'like', '%' . $normalizedCountry['name'] . '%');

            if ($normalizedCountry['iso']) {
                $inner->orWhere('country_iso', $normalizedCountry['iso']);
            }
        }))
        ->when($crawlerId !== 'all', fn ($builder) => $builder->where('market_crawler_id', $crawlerId))
        ->when($leadStatus !== 'all', fn ($builder) => $builder->where('lead_status', $leadStatus))
        ->when($countryStatus !== 'all', fn ($builder) => $builder->where('country_resolution_status', $countryStatus))
        ->when($subcategory === 'school_district' && $schoolDistrictStateCode !== '', fn ($builder) => $builder
            ->where('country_iso', 'US')
            ->where('region', 'United States - ' . Str::limit($schoolDistrictStateCode, 2, '')))
        ->when($subcategory === 'school_district' && $studentsFrom !== '', fn ($builder) => $builder->where('student_count', '>=', max(0, (int) preg_replace('/[^\d]/', '', $studentsFrom))))
        ->when($subcategory === 'school_district' && $studentsTo !== '', fn ($builder) => $builder->where('student_count', '<=', max(0, (int) preg_replace('/[^\d]/', '', $studentsTo))))
        ->orderBy('country')
        ->orderBy('organization_type')
        ->orderBy('name')
        ->limit(250)
        ->get();

    return view('sls.organizations.index', [
        'organizations' => $organizations,
        'crawlers' => MarketCrawler::query()
            ->withMax('organizations', 'last_crawled_at')
            ->withCount('organizations')
            ->orderBy('name')
            ->get(),
        'types' => $marketOrganizationTypes,
        'industries' => $marketIndustries,
        'subcategories' => $marketOrganizationSubcategories,
        'crawlerTypes' => $marketCrawlerTypes,
        'leadStatuses' => $marketLeadStatuses,
        'filters' => compact('query', 'type', 'industry', 'subcategory', 'country', 'crawlerId', 'leadStatus', 'countryStatus', 'studentsFrom', 'studentsTo', 'schoolDistrictState'),
    ]);
})->name('sls.organizations.index');

Route::get('/sls/organizations/crawler-results', function (Request $request) use ($seedMarketCrawlers) {
    $seedMarketCrawlers();

    $query = trim((string) $request->query('q', ''));
    $crawlerId = (string) $request->query('crawler', 'all');
    $country = trim((string) $request->query('country', ''));
    $hasEmail = (string) $request->query('has_email', 'all');
    $hasProcurement = (string) $request->query('has_procurement', 'all');
    $hasLeadership = (string) $request->query('has_leadership', 'all');
    $hasNews = (string) $request->query('has_news', 'all');
    $contactQuality = (string) $request->query('contact_quality', 'all');
    $export = $request->query('export') === 'csv';
    $limit = $export ? 5000 : 750;

    $rows = MarketOrganization::query()
        ->leftJoin('market_organization_contacts as contacts', 'contacts.market_organization_id', '=', 'market_organizations.id')
        ->leftJoin('market_crawlers as crawlers', 'crawlers.id', '=', 'market_organizations.market_crawler_id')
        ->select([
            'market_organizations.id as organization_id',
            'market_organizations.name as organization_name',
            'market_organizations.country',
            'market_organizations.country_iso',
            'market_organizations.organization_type',
            'market_organizations.organization_subcategory',
            'market_organizations.website_url',
            'market_organizations.procurement_page_url',
            'market_organizations.leadership_page_url',
            'market_organizations.news_page_url',
            'market_organizations.last_crawled_at',
            'market_organizations.last_error',
            'crawlers.id as crawler_id',
            'crawlers.name as crawler_name',
            'contacts.id as contact_id',
            'contacts.contact_type',
            'contacts.person_name',
            'contacts.job_title',
            'contacts.email',
            'contacts.phone',
            'contacts.source_url as contact_source_url',
            'contacts.context_excerpt',
            'contacts.extracted_at',
            'contacts.verification_status',
        ])
        ->when($query !== '', fn ($builder) => $builder->where(function ($inner) use ($query) {
            $inner->where('market_organizations.name', 'like', '%' . $query . '%')
                ->orWhere('market_organizations.website_domain', 'like', '%' . $query . '%')
                ->orWhere('market_organizations.website_url', 'like', '%' . $query . '%')
                ->orWhere('market_organizations.procurement_page_url', 'like', '%' . $query . '%')
                ->orWhere('market_organizations.leadership_page_url', 'like', '%' . $query . '%')
                ->orWhere('market_organizations.news_page_url', 'like', '%' . $query . '%')
                ->orWhere('contacts.person_name', 'like', '%' . $query . '%')
                ->orWhere('contacts.job_title', 'like', '%' . $query . '%')
                ->orWhere('contacts.email', 'like', '%' . $query . '%');
        }))
        ->when($crawlerId !== 'all', fn ($builder) => $builder->where('market_organizations.market_crawler_id', $crawlerId))
        ->when($country !== '', fn ($builder) => $builder->where(function ($inner) use ($country) {
            $inner->where('market_organizations.country_iso', 'like', '%' . $country . '%')
                ->orWhere('market_organizations.country', 'like', '%' . $country . '%');
        }))
        ->when($hasEmail === 'yes', fn ($builder) => $builder->whereNotNull('contacts.email')->where('contacts.email', '<>', ''))
        ->when($hasEmail === 'no', fn ($builder) => $builder->where(function ($inner) {
            $inner->whereNull('contacts.email')->orWhere('contacts.email', '');
        }))
        ->when($hasProcurement === 'yes', fn ($builder) => $builder->whereNotNull('market_organizations.procurement_page_url')->where('market_organizations.procurement_page_url', '<>', ''))
        ->when($hasProcurement === 'no', fn ($builder) => $builder->where(function ($inner) {
            $inner->whereNull('market_organizations.procurement_page_url')->orWhere('market_organizations.procurement_page_url', '');
        }))
        ->when($hasLeadership === 'yes', fn ($builder) => $builder->whereNotNull('market_organizations.leadership_page_url')->where('market_organizations.leadership_page_url', '<>', ''))
        ->when($hasLeadership === 'no', fn ($builder) => $builder->where(function ($inner) {
            $inner->whereNull('market_organizations.leadership_page_url')->orWhere('market_organizations.leadership_page_url', '');
        }))
        ->when($hasNews === 'yes', fn ($builder) => $builder->whereNotNull('market_organizations.news_page_url')->where('market_organizations.news_page_url', '<>', ''))
        ->when($hasNews === 'no', fn ($builder) => $builder->where(function ($inner) {
            $inner->whereNull('market_organizations.news_page_url')->orWhere('market_organizations.news_page_url', '');
        }))
        ->when($contactQuality === 'needs_name_research', fn ($builder) => $builder->where('contacts.verification_status', 'needs_name_research'))
        ->when($contactQuality === 'published', fn ($builder) => $builder->where('contacts.verification_status', 'published'))
        ->when($contactQuality === 'no_contact', fn ($builder) => $builder->whereNull('contacts.id'))
        ->orderByDesc('market_organizations.last_crawled_at')
        ->orderBy('market_organizations.country_iso')
        ->orderBy('market_organizations.name')
        ->orderBy('contacts.person_name')
        ->limit($limit)
        ->get();

    if ($export) {
        $fileName = 'crawler-results-' . now('America/Chicago')->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'crawler',
                'organization',
                'country_iso',
                'country',
                'type',
                'subcategory',
                'website_url',
                'procurement_page_url',
                'leadership_page_url',
                'news_page_url',
                'last_crawled_at',
                'contact_type',
                'person_name',
                'job_title',
                'email',
                'phone',
                'contact_source_url',
                'context_excerpt',
                'verification_status',
                'extracted_at',
                'last_error',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->crawler_name,
                    $row->organization_name,
                    $row->country_iso,
                    $row->country,
                    $row->organization_type,
                    $row->organization_subcategory,
                    $row->website_url,
                    $row->procurement_page_url,
                    $row->leadership_page_url,
                    $row->news_page_url,
                    $row->last_crawled_at,
                    $row->contact_type,
                    $row->person_name,
                    $row->job_title,
                    $row->email,
                    $row->phone,
                    $row->contact_source_url,
                    $row->context_excerpt,
                    $row->verification_status,
                    $row->extracted_at,
                    $row->last_error,
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    $summary = [
        'rows' => $rows->count(),
        'contacts' => $rows->whereNotNull('contact_id')->count(),
        'emails' => $rows->filter(fn ($row) => filled($row->email))->count(),
        'needs_name_research' => $rows->filter(fn ($row) => $row->verification_status === 'needs_name_research')->count(),
        'procurement_urls' => $rows->unique('organization_id')->filter(fn ($row) => filled($row->procurement_page_url))->count(),
        'leadership_urls' => $rows->unique('organization_id')->filter(fn ($row) => filled($row->leadership_page_url))->count(),
        'news_urls' => $rows->unique('organization_id')->filter(fn ($row) => filled($row->news_page_url))->count(),
    ];

    return view('sls.organizations.crawler-results', [
        'rows' => $rows,
        'crawlers' => MarketCrawler::query()->orderBy('name')->get(),
        'filters' => compact('query', 'crawlerId', 'country', 'hasEmail', 'hasProcurement', 'hasLeadership', 'hasNews', 'contactQuality'),
        'summary' => $summary,
    ]);
})->name('sls.organizations.crawlerResults');

Route::get('/sls/organizations/crawlers', function () use ($seedMarketCrawlers) {
    try {
        abort_unless(Schema::hasTable('market_crawlers'), 503, 'Crawler tables have not been migrated yet.');

        $seedMarketCrawlers();

        $crawler = MarketCrawler::query()
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first()
            ?: MarketCrawler::query()->orderBy('id')->first();

        if (! $crawler) {
            return response()->view('sls.organizations.crawler-error', [
                'title' => 'No crawlers are configured yet',
                'message' => 'The crawler table exists, but no crawler records were found.',
                'crawlerId' => null,
                'exception' => null,
            ], 404);
        }

        return redirect()->route('sls.organizations.crawlers.show', $crawler);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->view('sls.organizations.crawler-error', [
            'title' => 'Crawler page could not load',
            'message' => 'SLS could not prepare the crawler landing page.',
            'crawlerId' => null,
            'exception' => $exception,
        ], 500);
    }
})->name('sls.organizations.crawlers.index');

Route::get('/sls/organizations/crawlers/{crawler}', function (Request $request, string $crawler, UniversityMarketCrawlerService $universityCrawlerService) use ($marketCrawlerTypes, $seedMarketCrawlers, $sanctionedCountryIsos, $blockedOrganizationStatuses) {
    try {
    $seedMarketCrawlers();

    abort_unless(Schema::hasTable('market_crawlers'), 503, 'Crawler tables have not been migrated yet.');

    $crawler = MarketCrawler::query()->whereKey($crawler)->first();

    if (! $crawler) {
        return redirect()
            ->route('sls.organizations.crawlers.index')
            ->with('status', 'That crawler no longer exists. Showing the first configured crawler instead.');
    }

    $crawler->refresh();
    $since = now()->subDays(5);

    $runs = MarketCrawlerRun::query()
        ->with('organization')
        ->where('market_crawler_id', $crawler->id)
        ->where('started_at', '>=', $since)
        ->orderByDesc('started_at')
        ->limit(250)
        ->get();

    $recentCrawls = MarketOrganization::query()
        ->where('market_crawler_id', $crawler->id)
        ->whereNotNull('last_crawled_at')
        ->where('last_crawled_at', '>=', $since)
        ->orderByDesc('last_crawled_at')
        ->limit(250)
        ->get();

    $crawlerFollowUps = \App\Models\MarketOrganizationTask::query()
        ->with('organization')
        ->where('task_type', 'crawler_follow_up')
        ->where('status', 'open')
        ->whereHas('organization', fn ($query) => $query->where('market_crawler_id', $crawler->id))
        ->latest()
        ->limit(100)
        ->get();

    $assignedOrganizations = MarketOrganization::query()
        ->where('market_crawler_id', $crawler->id)
        ->whereNotIn('country_iso', $sanctionedCountryIsos)
        ->where(function ($query) use ($blockedOrganizationStatuses) {
            $query->whereNull('status')
                ->orWhereNotIn('status', $blockedOrganizationStatuses);
        })
        ->count();

    $crawlerProfile = $universityCrawlerService->profile($crawler);
    $allCrawlers = MarketCrawler::query()
        ->orderBy('name')
        ->get();
    $targetOrganizationQuery = fn () => MarketOrganization::query()
        ->where('organization_type', $crawlerProfile['organization_type'])
        ->when($crawlerProfile['industry'] ?? null, fn ($query, $industry) => $query->where('industry', $industry))
        ->when($crawlerProfile['subcategory'] ?? null, fn ($query, $subcategory) => $query->where('organization_subcategory', $subcategory))
        ->whereNotIn('country_iso', $sanctionedCountryIsos)
        ->where(function ($query) use ($blockedOrganizationStatuses) {
            $query->whereNull('status')
                ->orWhereNotIn('status', $blockedOrganizationStatuses);
        });

    $crawlerCountryIsos = $targetOrganizationQuery()
        ->whereNotNull('country_iso')
        ->where('country_iso', '<>', '')
        ->distinct()
        ->pluck('country_iso')
        ->map(fn ($iso) => Str::upper(trim((string) $iso)))
        ->filter()
        ->values();
    $countriesByIso = Country::query()
        ->whereIn('iso_code', $crawlerCountryIsos)
        ->get()
        ->keyBy('iso_code');
    $crawlCountsByIso = $targetOrganizationQuery()
        ->whereIn('country_iso', $crawlerCountryIsos)
        ->selectRaw("country_iso, COUNT(*) AS total, SUM(CASE WHEN last_crawled_at IS NULL THEN 1 ELSE 0 END) AS not_crawled, SUM(CASE WHEN website_url IS NULL OR website_url = '' THEN 1 ELSE 0 END) AS missing_domain")
        ->groupBy('country_iso')
        ->get()
        ->keyBy('country_iso');
    $countryOptions = $crawlerCountryIsos
        ->map(function ($iso) use ($countriesByIso, $crawlCountsByIso) {
            $country = $countriesByIso->get($iso);
            $counts = $crawlCountsByIso->get($iso);

            return [
                'label' => $country?->name ?: $iso,
                'region' => $country?->region ?: 'Unassigned',
                'language' => Str::upper((string) ($country?->default_language_code ?: 'en')),
                'iso' => $iso,
                'total' => (int) ($counts?->total ?? 0),
                'not_crawled' => (int) ($counts?->not_crawled ?? 0),
                'missing_domain' => (int) ($counts?->missing_domain ?? 0),
            ];
        })
        ->sortBy(fn ($country) => $country['region'] . '|' . $country['label'])
        ->values();

    $discoveryPreview = collect();
    $crawlPreview = collect();
    $previewCountries = collect($request->query('preview_countries', []))
        ->when($request->query('preview_country'), fn ($countries) => $countries->push((string) $request->query('preview_country')))
        ->map(fn ($country) => trim((string) $country))
        ->filter()
        ->unique()
        ->values()
        ->all();

    $discoveryPreviewFilters = [
        'countries' => $previewCountries,
        'organization_limit' => (int) $request->query('preview_organization_limit', 25),
        'results_per_query' => (int) $request->query('preview_results_per_query', 5),
    ];
    $discoveryPreviewCoverage = collect();
    $crawlPreviewCountries = collect($request->query('crawl_preview_countries', []))
        ->map(fn ($country) => trim((string) $country))
        ->filter()
        ->unique()
        ->values()
        ->all();
    $crawlPreviewFilters = [
        'countries' => $crawlPreviewCountries,
        'organization_limit' => (int) $request->query('crawl_preview_organization_limit', 25),
        'page_limit' => (int) $request->query('crawl_preview_page_limit', 6),
    ];

    if ($request->boolean('preview_serpapi')) {
        $discoveryPreview = $universityCrawlerService->previewDiscoveryOrganizations(
            $crawler,
            $discoveryPreviewFilters['countries'] !== [] ? $discoveryPreviewFilters['countries'] : null,
            max(1, min(100, $discoveryPreviewFilters['organization_limit']))
        );
        $discoveryPreview->each(fn (MarketOrganization $organization) => $organization->setAttribute(
            'crawler_discovery_query',
            $universityCrawlerService->previewDiscoveryQuery($organization)
        ));
        $coverageCountries = $discoveryPreviewFilters['countries'] !== [] ? $discoveryPreviewFilters['countries'] : $crawlerCountryIsos->all();
        $discoveryPreviewCoverage = $targetOrganizationQuery()
            ->whereIn('country_iso', $coverageCountries)
            ->selectRaw("country_iso, COUNT(*) AS total, SUM(CASE WHEN website_url IS NULL OR website_url = '' THEN 1 ELSE 0 END) AS missing_website")
            ->groupBy('country_iso')
            ->orderBy('country_iso')
            ->get();
    }

    if ($request->boolean('preview_crawl')) {
        $crawlPreview = $universityCrawlerService->previewCrawlOrganizations(
            $crawler,
            $crawlPreviewFilters['countries'] !== [] ? $crawlPreviewFilters['countries'] : null,
            max(1, min(1000, $crawlPreviewFilters['organization_limit']))
        );
    }

    return view('sls.organizations.crawler-show', [
        'crawler' => $crawler,
        'crawlerTypes' => $marketCrawlerTypes,
        'recentCrawls' => $recentCrawls,
        'crawlerFollowUps' => $crawlerFollowUps,
        'runs' => $runs,
        'allCrawlers' => $allCrawlers,
        'assignedOrganizations' => $assignedOrganizations,
        'crawlerProfile' => $crawlerProfile,
        'since' => $since,
        'countryOptions' => $countryOptions,
        'discoveryPreview' => $discoveryPreview,
        'discoveryPreviewFilters' => $discoveryPreviewFilters,
        'discoveryPreviewCoverage' => $discoveryPreviewCoverage,
        'crawlPreview' => $crawlPreview,
        'crawlPreviewFilters' => $crawlPreviewFilters,
    ]);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->view('sls.organizations.crawler-error', [
            'title' => 'Crawler page could not load',
            'message' => 'SLS found the crawler route, but failed while building the crawler dashboard data.',
            'crawlerId' => is_scalar($crawler) ? (string) $crawler : null,
            'exception' => $exception,
        ], 500);
    }
})->name('sls.organizations.crawlers.show');

Route::post('/sls/organizations/crawlers/{crawler}/discovery', function (Request $request, MarketCrawler $crawler, UniversityMarketCrawlerService $service) {
    $crawlerProfile = $service->profile($crawler);

    $data = $request->validate([
        'countries' => ['nullable', 'array', 'max:50'],
        'countries.*' => ['string', 'max:120'],
        'organization_limit' => ['required', 'integer', 'min:1', 'max:100'],
        'max_calls' => ['required', 'integer', 'min:1', 'max:500'],
        'results_per_query' => ['required', 'integer', 'min:1', 'max:10'],
        'selected_organization_ids' => ['nullable', 'array', 'max:100'],
        'selected_organization_ids.*' => ['integer', 'exists:market_organizations,id'],
    ]);

    $selectedOrganizationIds = collect($data['selected_organization_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $selectedCountries = collect($data['countries'] ?? [])
        ->map(fn ($country) => trim((string) $country))
        ->filter()
        ->unique()
        ->values()
        ->all();
    $countryLabel = $selectedCountries === [] ? 'any' : implode(', ', $selectedCountries);

    $batchRun = MarketCrawlerRun::query()->create([
        'market_crawler_id' => $crawler->id,
        'run_type' => 'serpapi_discovery_batch',
        'status' => 'started',
        'query_text' => 'Background ' . $crawlerProfile['singular'] . ' main-website discovery pilot: up to '
            . (int) $data['organization_limit'] . ' ' . $crawlerProfile['plural'] . ', '
            . (int) $data['max_calls'] . ' SerpAPI calls, countries=' . $countryLabel
            . '. Only records missing a main website are selected.'
            . ($selectedOrganizationIds !== [] ? ' Using staged organization IDs: ' . implode(',', $selectedOrganizationIds) . '.' : ''),
        'started_at' => now(),
        'finished_at' => null,
        'result_payload' => [
            'countries' => $selectedCountries,
            'organization_limit' => (int) $data['organization_limit'],
            'max_calls' => (int) $data['max_calls'],
            'results_per_query' => (int) $data['results_per_query'],
            'selected_organization_ids' => $selectedOrganizationIds,
        ],
    ]);

    $php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
    $artisan = base_path('artisan');
    $log = storage_path('logs/crawler-discovery-pilot.log');
    $errorLog = storage_path('logs/crawler-discovery-pilot-error.log');
    $arguments = [
        $artisan,
        'sls:crawler-discovery-pilot',
        (string) $crawler->id,
        '--country=' . implode(',', $selectedCountries),
        '--organizations=' . (int) $data['organization_limit'],
        '--calls=' . (int) $data['max_calls'],
        '--results=' . (int) $data['results_per_query'],
        '--batch-id=' . $batchRun->id,
    ];

    if ($selectedOrganizationIds !== []) {
        $arguments[] = '--organization-ids=' . implode(',', $selectedOrganizationIds);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd.exe /C start "" /B '
            . escapeshellarg($php) . ' '
            . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log)
            . ' 2> ' . escapeshellarg($errorLog);
        pclose(popen($command, 'r'));
    } else {
        $unixCommand = escapeshellarg($php) . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log) . ' 2> ' . escapeshellarg($errorLog) . ' &';
        pclose(popen($unixCommand, 'r'));
    }

    return redirect()
        ->route('sls.organizations.crawlers.show', ['crawler' => $crawler->id])
        ->with('status', ucfirst($crawlerProfile['singular']) . ' discovery pilot started in the background. Refresh this page manually to see the SerpAPI query/result rows.');
})->name('sls.organizations.crawlers.discovery');

Route::post('/sls/organizations/crawlers/{crawler}/crawl', function (Request $request, MarketCrawler $crawler, UniversityMarketCrawlerService $service) {
    $crawlerProfile = $service->profile($crawler);

    $data = $request->validate([
        'countries' => ['nullable', 'array', 'max:50'],
        'countries.*' => ['string', 'max:120'],
        'organization_limit' => ['required', 'integer', 'min:1', 'max:1000'],
        'page_limit' => ['required', 'integer', 'min:1', 'max:25'],
        'selected_organization_ids' => ['nullable', 'array', 'max:1000'],
        'selected_organization_ids.*' => ['integer', 'exists:market_organizations,id'],
    ]);
    $selectedCountries = collect($data['countries'] ?? [])
        ->map(fn ($country) => trim((string) $country))
        ->filter()
        ->unique()
        ->values()
        ->all();
    $selectedOrganizationIds = collect($data['selected_organization_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $countryLabel = $selectedCountries === [] ? 'any' : implode(', ', $selectedCountries);
    $batchRun = MarketCrawlerRun::query()->create([
        'market_crawler_id' => $crawler->id,
        'run_type' => 'direct_crawl_batch',
        'status' => 'started',
        'query_text' => 'Background ' . $crawlerProfile['singular'] . ' direct crawl: up to '
            . (int) $data['organization_limit'] . ' ' . $crawlerProfile['plural'] . ', '
            . (int) $data['page_limit'] . ' pages per organization, countries=' . $countryLabel
            . ($selectedOrganizationIds !== [] ? '. Using staged organization IDs: ' . implode(',', $selectedOrganizationIds) . '.' : ''),
        'started_at' => now(),
        'finished_at' => null,
        'result_payload' => [
            'countries' => $selectedCountries,
            'organization_limit' => (int) $data['organization_limit'],
            'page_limit' => (int) $data['page_limit'],
            'selected_organization_ids' => $selectedOrganizationIds,
        ],
    ]);

    $php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
    $artisan = base_path('artisan');
    $log = storage_path('logs/crawler-crawl-pilot.log');
    $errorLog = storage_path('logs/crawler-crawl-pilot-error.log');
    $arguments = [
        $artisan,
        'sls:crawler-crawl-pilot',
        (string) $crawler->id,
        '--country=' . implode(',', $selectedCountries),
        '--organizations=' . (int) $data['organization_limit'],
        '--pages=' . (int) $data['page_limit'],
        '--batch-id=' . $batchRun->id,
    ];

    if ($selectedOrganizationIds !== []) {
        $arguments[] = '--organization-ids=' . implode(',', $selectedOrganizationIds);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd.exe /C start "" /B '
            . escapeshellarg($php) . ' '
            . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log)
            . ' 2> ' . escapeshellarg($errorLog);
        pclose(popen($command, 'r'));
    } else {
        $unixCommand = escapeshellarg($php) . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log) . ' 2> ' . escapeshellarg($errorLog) . ' &';
        pclose(popen($unixCommand, 'r'));
    }

    return redirect()
        ->route('sls.organizations.crawlers.show', ['crawler' => $crawler->id])
        ->with('status', ucfirst($crawlerProfile['singular']) . ' direct crawl started in the background. Refresh this page manually to see the started/completed batch row and individual crawl results.');
})->name('sls.organizations.crawlers.crawl');

Route::get('/sls/tasks', function (Request $request) {
    $status = $request->string('status')->toString() ?: 'open';
    $type = $request->string('type')->toString() ?: 'all';
    $query = trim($request->string('q')->toString());

    $tasks = SlsTask::query()
        ->with(['organization', 'countryUpdate'])
        ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
        ->when($type !== 'all', fn ($builder) => $builder->where('task_type', $type))
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($inner) use ($query) {
                $inner->where('title', 'like', '%' . $query . '%')
                    ->orWhere('notes', 'like', '%' . $query . '%')
                    ->orWhere('product_focus', 'like', '%' . $query . '%')
                    ->orWhere('country_iso', 'like', '%' . $query . '%');
            });
        })
        ->orderByRaw("status = 'completed' ASC")
        ->orderByRaw('due_at IS NULL ASC')
        ->orderBy('due_at')
        ->latest()
        ->paginate(100)
        ->withQueryString();

    return view('sls.tasks.index', [
        'tasks' => $tasks,
        'status' => $status,
        'type' => $type,
        'query' => $query,
        'taskTypes' => [
            'general' => 'General',
            'reminder' => 'Reminder',
            'system_feature' => 'System feature',
            'research' => 'Research',
            'crawler_setup' => 'Crawler setup',
            'data_build' => 'Data build',
        ],
        'statuses' => [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'waiting' => 'Waiting',
            'completed' => 'Completed',
        ],
        'priorities' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
    ]);
})->name('sls.tasks.index');

Route::post('/sls/tasks', function (Request $request) {
    $data = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'notes' => ['nullable', 'string'],
        'task_type' => ['required', 'string', 'max:80'],
        'status' => ['required', 'string', 'max:80'],
        'priority' => ['required', 'string', 'max:40'],
        'product_focus' => ['nullable', 'string', 'max:80'],
        'country_iso' => ['nullable', 'string', 'max:8'],
        'related_url' => ['nullable', 'url', 'max:2000'],
        'due_at' => ['nullable', 'date'],
    ]);

    $data['country_iso'] = filled($data['country_iso'] ?? null) ? Str::upper($data['country_iso']) : null;
    SlsTask::query()->create($data);

    return back()->with('status', 'Task created.');
})->name('sls.tasks.store');

Route::post('/sls/tasks/{task}/status', function (Request $request, SlsTask $task) {
    $data = $request->validate([
        'status' => ['required', 'in:open,in_progress,waiting,completed'],
    ]);

    $task->update([
        'status' => $data['status'],
        'completed_at' => $data['status'] === 'completed' ? now() : null,
    ]);

    return back()->with('status', 'Task updated.');
})->name('sls.tasks.status');

Route::get('/sls/crm/search', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $type = (string) $request->query('type', 'all');
    $country = trim((string) $request->query('country', ''));
    $status = trim((string) $request->query('status', 'all')) ?: 'all';
    $limit = 30;
    $matchedCountry = $query !== ''
        ? Country::query()
            ->where('name', $query)
            ->orWhere('iso_code', Str::upper($query))
            ->first()
        : null;
    $isCountrySearch = $query !== '' && $country === '' && $matchedCountry !== null;
    $effectiveCountry = $country !== '' ? $country : ($matchedCountry?->name ?? '');

    $searchable = function ($builder, array $columns, string $term) {
        if ($term === '') {
            return $builder;
        }

        return $builder->where(function ($inner) use ($columns, $term) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%' . $term . '%');
            }
        });
    };

    $countryFilter = function ($builder, array $columns) use ($effectiveCountry) {
        if ($effectiveCountry === '') {
            return $builder;
        }

        return $builder->where(function ($inner) use ($columns, $effectiveCountry) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%' . $effectiveCountry . '%');
            }
        });
    };

    $sections = collect();
    $summary = [
        'accounts' => 0,
        'contacts' => 0,
        'opportunities' => 0,
        'tasks' => 0,
        'activities' => 0,
        'communications' => 0,
        'ocr_drafts' => 0,
    ];

    $include = fn (string $section) => $type === 'all' || $type === $section;

    if ($query !== '' && $include('accounts')) {
        $accounts = MarketOrganization::query()
            ->with('crawler')
            ->withCount(['contacts', 'activities', 'tasks', 'communications'])
            ->when(! $isCountrySearch, fn ($builder) => $searchable($builder, ['name', 'website_domain', 'website_url', 'organization_type', 'industry', 'organization_subcategory', 'notes', 'lead_status', 'lead_source'], $query))
            ->tap(fn ($builder) => $countryFilter($builder, ['country', 'country_raw', 'country_iso', 'region']))
            ->when($status !== 'all', fn ($builder) => $builder->where('lead_status', $status))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $summary['accounts'] = $accounts->count();
        $sections->push(['key' => 'accounts', 'label' => 'Accounts', 'items' => $accounts]);
    }

    if ($query !== '' && $include('contacts')) {
        $contacts = MarketOrganizationContact::query()
            ->with('organization')
            ->when(! $isCountrySearch, fn ($builder) => $builder->where(function ($builder) use ($query) {
                $builder->where('person_name', 'like', '%' . $query . '%')
                    ->orWhere('job_title', 'like', '%' . $query . '%')
                    ->orWhere('email', 'like', '%' . $query . '%')
                    ->orWhere('phone', 'like', '%' . $query . '%')
                    ->orWhere('notes', 'like', '%' . $query . '%')
                    ->orWhere('context_excerpt', 'like', '%' . $query . '%')
                    ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%' . $query . '%'));
            }))
            ->when($effectiveCountry !== '', fn ($builder) => $builder->whereHas('organization', fn ($org) => $org
                ->where('country', 'like', '%' . $effectiveCountry . '%')
                ->orWhere('country_raw', 'like', '%' . $effectiveCountry . '%')
                ->orWhere('country_iso', 'like', '%' . $effectiveCountry . '%')))
            ->when($status !== 'all', fn ($builder) => $builder->where('verification_status', $status))
            ->orderBy('person_name')
            ->limit($limit)
            ->get();

        $summary['contacts'] = $contacts->count();
        $sections->push(['key' => 'contacts', 'label' => 'Contacts', 'items' => $contacts]);
    }

    if ($query !== '' && $include('opportunities')) {
        $updates = CountryUpdate::query()
            ->with(['country', 'topic'])
            ->when($isCountrySearch, fn ($builder) => $builder->where('country_id', $matchedCountry->id))
            ->when(! $isCountrySearch, fn ($builder) => $builder->where(function ($builder) use ($query) {
                $builder->where('title_english', 'like', '%' . $query . '%')
                    ->orWhere('title_original', 'like', '%' . $query . '%')
                    ->orWhere('title', 'like', '%' . $query . '%')
                    ->orWhere('summary', 'like', '%' . $query . '%')
                    ->orWhere('source_name', 'like', '%' . $query . '%')
                    ->orWhereHas('country', fn ($countryQuery) => $countryQuery->where('name', 'like', '%' . $query . '%'));
            }))
            ->when(! $isCountrySearch && $effectiveCountry !== '', fn ($builder) => $builder->whereHas('country', fn ($countryQuery) => $countryQuery
                ->where('name', 'like', '%' . $effectiveCountry . '%')
                ->orWhere('iso_code', 'like', '%' . $effectiveCountry . '%')))
            ->when($status !== 'all', fn ($builder) => $builder->where('review_status', $status))
            ->when($status === 'all', fn ($builder) => $builder->where('review_status', '<>', 'rejected'))
            ->latest('publication_date')
            ->latest('retrieved_at')
            ->limit($limit)
            ->get();

        $countryOpportunities = CountryUpdateOpportunity::query()
            ->with(['countryUpdate.country', 'product'])
            ->when($isCountrySearch, fn ($builder) => $builder->whereHas('countryUpdate', fn ($update) => $update->where('country_id', $matchedCountry->id)->where('review_status', '<>', 'rejected')))
            ->when(! $isCountrySearch, fn ($builder) => $builder->where(function ($builder) use ($query) {
                $builder->where('issue_area', 'like', '%' . $query . '%')
                    ->orWhere('opportunity_stage', 'like', '%' . $query . '%')
                    ->orWhere('issue_summary', 'like', '%' . $query . '%')
                    ->orWhere('product_alignment', 'like', '%' . $query . '%')
                    ->orWhere('suggested_email', 'like', '%' . $query . '%')
                    ->orWhereHas('countryUpdate', fn ($update) => $update
                        ->where('title_english', 'like', '%' . $query . '%')
                        ->orWhere('title_original', 'like', '%' . $query . '%'));
            }))
            ->when(! $isCountrySearch && $effectiveCountry !== '', fn ($builder) => $builder->whereHas('countryUpdate.country', fn ($countryQuery) => $countryQuery
                ->where('name', 'like', '%' . $effectiveCountry . '%')
                ->orWhere('iso_code', 'like', '%' . $effectiveCountry . '%')))
            ->latest()
            ->limit($limit)
            ->get();

        $summary['opportunities'] = $updates->count() + $countryOpportunities->count();
        $sections->push(['key' => 'opportunities', 'label' => 'Opportunities and Intelligence', 'items' => $updates, 'extra' => $countryOpportunities]);
    }

    if ($query !== '' && $include('tasks')) {
        $tasks = MarketOrganizationTask::query()
            ->with('organization')
            ->where(function ($builder) use ($query) {
                $builder->where('title', 'like', '%' . $query . '%')
                    ->orWhere('notes', 'like', '%' . $query . '%')
                    ->orWhere('task_type', 'like', '%' . $query . '%')
                    ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%' . $query . '%'));
            })
            ->when($country !== '', fn ($builder) => $builder->whereHas('organization', fn ($org) => $org
                ->where('country', 'like', '%' . $country . '%')
                ->orWhere('country_raw', 'like', '%' . $country . '%')
                ->orWhere('country_iso', 'like', '%' . $country . '%')))
            ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->orderByRaw("status = 'completed' ASC")
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        $summary['tasks'] = $tasks->count();
        $sections->push(['key' => 'tasks', 'label' => 'Tasks and Reminders', 'items' => $tasks]);
    }

    if ($query !== '' && $include('activities')) {
        $activities = MarketOrganizationActivity::query()
            ->with('organization')
            ->where(function ($builder) use ($query) {
                $builder->where('activity_type', 'like', '%' . $query . '%')
                    ->orWhere('subject', 'like', '%' . $query . '%')
                    ->orWhere('body', 'like', '%' . $query . '%')
                    ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%' . $query . '%'));
            })
            ->when($country !== '', fn ($builder) => $builder->whereHas('organization', fn ($org) => $org
                ->where('country', 'like', '%' . $country . '%')
                ->orWhere('country_raw', 'like', '%' . $country . '%')
                ->orWhere('country_iso', 'like', '%' . $country . '%')))
            ->latest('activity_at')
            ->limit($limit)
            ->get();

        $summary['activities'] = $activities->count();
        $sections->push(['key' => 'activities', 'label' => 'Activities and Notes', 'items' => $activities]);
    }

    if ($query !== '' && $include('communications')) {
        $communications = MarketOrganizationCommunication::query()
            ->with(['organization', 'contact'])
            ->where(function ($builder) use ($query) {
                $builder->where('channel', 'like', '%' . $query . '%')
                    ->orWhere('direction', 'like', '%' . $query . '%')
                    ->orWhere('subject', 'like', '%' . $query . '%')
                    ->orWhere('body_excerpt', 'like', '%' . $query . '%')
                    ->orWhere('from_address', 'like', '%' . $query . '%')
                    ->orWhere('to_address', 'like', '%' . $query . '%')
                    ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%' . $query . '%'))
                    ->orWhereHas('contact', fn ($contact) => $contact->where('person_name', 'like', '%' . $query . '%'));
            })
            ->when($country !== '', fn ($builder) => $builder->whereHas('organization', fn ($org) => $org
                ->where('country', 'like', '%' . $country . '%')
                ->orWhere('country_raw', 'like', '%' . $country . '%')
                ->orWhere('country_iso', 'like', '%' . $country . '%')))
            ->latest('sent_or_received_at')
            ->limit($limit)
            ->get();

        $summary['communications'] = $communications->count();
        $sections->push(['key' => 'communications', 'label' => 'Communications', 'items' => $communications]);
    }

    if ($query !== '' && $include('ocr_drafts')) {
        $drafts = DirectoryImageEntry::query()
            ->with(['batch', 'organization'])
            ->whereNull('market_organization_id')
            ->where(function ($builder) use ($query) {
                $builder->where('organization_name', 'like', '%' . $query . '%')
                    ->orWhere('address_text', 'like', '%' . $query . '%')
                    ->orWhere('country_raw', 'like', '%' . $query . '%')
                    ->orWhere('country_normalized', 'like', '%' . $query . '%')
                    ->orWhere('website', 'like', '%' . $query . '%')
                    ->orWhere('email', 'like', '%' . $query . '%')
                    ->orWhere('phone', 'like', '%' . $query . '%')
                    ->orWhere('executive_text', 'like', '%' . $query . '%')
                    ->orWhere('raw_text', 'like', '%' . $query . '%');
            })
            ->when($country !== '', fn ($builder) => $builder->where(function ($inner) use ($country) {
                $inner->where('country_normalized', 'like', '%' . $country . '%')
                    ->orWhere('country_raw', 'like', '%' . $country . '%')
                    ->orWhere('country_iso', 'like', '%' . $country . '%');
            }))
            ->when($status !== 'all', fn ($builder) => $builder->where('review_status', $status))
            ->latest()
            ->limit($limit)
            ->get();

        $summary['ocr_drafts'] = $drafts->count();
        $sections->push(['key' => 'ocr_drafts', 'label' => 'OCR Drafts Not Yet Promoted', 'items' => $drafts]);
    }

    return view('sls.crm.search', [
        'query' => $query,
        'type' => $type,
        'country' => $country,
        'status' => $status,
        'sections' => $sections,
        'summary' => $summary,
        'total' => array_sum($summary),
    ]);
})->name('sls.crm.search');

Route::post('/sls/organizations/import', function (Request $request) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $seedMarketCrawlers, $normalizeCountryForOrganization) {
    set_time_limit(0);
    $seedMarketCrawlers();

    $data = $request->validate([
        'organization_type' => ['required', 'string', 'max:80'],
        'industry' => ['required', 'string', 'max:120'],
        'organization_subcategory' => ['nullable', 'string', 'max:120'],
        'default_country' => ['nullable', 'string', 'max:120'],
        'market_crawler_id' => ['nullable', 'integer', 'exists:market_crawlers,id'],
        'organizations' => ['nullable', 'string'],
        'organization_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:102400'],
    ]);

    $rawOrganizations = (string) ($data['organizations'] ?? '');

    if ($request->hasFile('organization_file')) {
        $rawOrganizations .= "\n" . file_get_contents($request->file('organization_file')->getRealPath());
    }

    if (trim($rawOrganizations) === '') {
        return back()->with('status', 'Paste organization rows or choose a CSV/TXT file to import.');
    }

    if (! array_key_exists($data['organization_type'], $marketOrganizationTypes) || ! array_key_exists($data['industry'], $marketIndustries) || (filled($data['organization_subcategory'] ?? null) && ! array_key_exists($data['organization_subcategory'], $marketOrganizationSubcategories))) {
        return back()->with('status', 'Organization type or industry is not valid.');
    }

    $created = 0;
    $updated = 0;

    $rows = collect(preg_split('/\r\n|\r|\n/', $rawOrganizations))
        ->map(fn (string $line) => trim($line))
        ->filter()
        ->values();

    $isSchoolDistrictImport = ($data['organization_type'] ?? '') === 'school_district'
        || ($data['industry'] ?? '') === 'k12_education'
        || ($data['organization_subcategory'] ?? '') === 'school_district';
    $headerParts = $rows->isNotEmpty()
        ? collect(str_getcsv((string) $rows->first()))->map(fn ($part) => Str::of((string) $part)->replace("\xEF\xBB\xBF", '')->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString())->values()
        : collect();
    $hasHeader = $headerParts->contains(fn (string $header) => in_array($header, [
        'district_name', 'school_district', 'agency_name', 'lea_name', 'name', 'organization_name',
            'state', 'state_name', 'state_code', 'st', 'website', 'web_site', 'url', 'phone', 'telephone',
    ], true));

    if ($isSchoolDistrictImport && $hasHeader) {
        $fieldValue = function (array $row, array $aliases): string {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $row) && trim((string) $row[$alias]) !== '') {
                    return trim((string) $row[$alias]);
                }
            }

            return '';
        };

        $rows->skip(1)->each(function (string $line) use ($data, &$created, &$updated, $normalizeCountryForOrganization, $headerParts, $fieldValue) {
            $values = collect(str_getcsv($line))->map(fn ($part) => trim((string) $part))->values();
            $row = [];

            foreach ($headerParts as $index => $header) {
                $row[$header] = (string) ($values->get($index) ?? '');
            }

            $name = $fieldValue($row, ['district_name', 'school_district', 'agency_name', 'lea_name', 'organization_name', 'name', 'district']);

            if ($name === '') {
                return;
            }

            $state = $fieldValue($row, ['state_code', 'state', 'state_name', 'st', 'mailing_state', 'mstate', 'location_state', 'lstate']);
            $website = $fieldValue($row, ['website', 'web_site', 'url', 'site', 'domain']);
            $phone = $fieldValue($row, ['phone', 'telephone', 'phone_number', 'tel']);
            $city = $fieldValue($row, ['city', 'mailing_city', 'mcity', 'location_city', 'lcity']);
            $street = $fieldValue($row, ['location_street1', 'street', 'address', 'mailing_street1', 'mailing_address', 'mstreet1', 'location_address', 'lstreet1']);
            $zip = $fieldValue($row, ['zip', 'zipcode', 'zip_code', 'mzip', 'lzip']);
            $districtId = $fieldValue($row, ['district_nces_id', 'district_nces_id_string', 'district_id', 'lea_id', 'leaid', 'nces_id', 'state_id']);
            $stateAgencyNumber = $fieldValue($row, ['st_leaid', 'state_agency_no']);
            $schoolYear = $fieldValue($row, ['school_year']);
            $statusText = $fieldValue($row, ['school_year_status_description', 'updated_status_text']);
            $leaType = $fieldValue($row, ['lea_type_description', 'lea_type_code']);
            $level = $fieldValue($row, ['level_', 'igoffered']);
            $county = $fieldValue($row, ['internal_county_mapping', 'county']);
            $schoolCount = $fieldValue($row, ['number_of_schools']);
            $studentCount = $fieldValue($row, ['number_of_students']);
            $normalizedCountry = $normalizeCountryForOrganization('United States', 'US');
            $normalizedUrl = filled($website)
                ? (Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://' . $website)
                : null;
            $domain = filled($normalizedUrl) ? parse_url($normalizedUrl, PHP_URL_HOST) : null;
            $notes = collect([
                $districtId !== '' ? 'NCES district ID: ' . $districtId : null,
                $stateAgencyNumber !== '' ? 'State LEA ID: ' . $stateAgencyNumber : null,
                $schoolYear !== '' ? 'School year: ' . $schoolYear : null,
                $statusText !== '' ? 'Status: ' . $statusText : null,
                $leaType !== '' ? 'LEA type: ' . $leaType : null,
                $level !== '' ? 'Grade level: ' . $level : null,
                $county !== '' ? 'County mapping: ' . $county : null,
                $schoolCount !== '' ? 'Number of schools: ' . $schoolCount : null,
                $studentCount !== '' ? 'Number of students: ' . $studentCount : null,
                $street !== '' || $city !== '' || $state !== '' || $zip !== '' ? 'Address: ' . trim(collect([$street, $city, $state, $zip])->filter()->implode(', ')) : null,
                'Imported as US school district account. Contacts and procurement links still need crawler enrichment.',
            ])->filter()->implode("\n");
            $fingerprint = hash('sha256', Str::lower('school_district|' . $name . '|' . $state . '|' . ($districtId ?: $domain ?: $city)));

            $organization = MarketOrganization::updateOrCreate(
                ['source_fingerprint' => $fingerprint],
                [
                    'market_crawler_id' => $data['market_crawler_id'] ?? null,
                    'name' => Str::limit($name, 255, ''),
                    'name_normalized' => Str::lower(Str::limit($name, 500, '')),
                    'organization_type' => 'school_district',
                    'industry' => 'k12_education',
                    'organization_subcategory' => 'school_district',
                    'country' => $normalizedCountry['name'],
                    'country_raw' => 'United States',
                    'country_iso' => 'US',
                    'country_resolution_status' => 'resolved',
                    'region' => $state !== '' ? 'United States - ' . $state : 'United States',
                    'website_url' => $normalizedUrl,
                    'website_domain' => $domain ? Str::of($domain)->lower()->replace('www.', '')->toString() : null,
                    'organization_phone' => $phone ?: null,
                    'student_count' => $studentCount !== '' ? (int) preg_replace('/[^\d]/', '', $studentCount) : null,
                    'status' => 'active',
                    'lead_status' => 'researching',
                    'lead_source' => 'school_district_csv_import',
                    'notes' => $notes,
                    'last_crawler_name' => optional(MarketCrawler::find($data['market_crawler_id'] ?? null))->name,
                    'source_fingerprint' => $fingerprint,
                ],
            );

            $organization->wasRecentlyCreated ? $created++ : $updated++;
        });

        return back()->with('status', 'Imported ' . $created . ' school district account(s), updated ' . $updated . '. Contact and procurement discovery can be handled by the school district crawler next.');
    }

    $rows->each(function (string $line) use ($data, &$created, &$updated, $normalizeCountryForOrganization) {
            $parts = collect(str_getcsv($line))->map(fn ($part) => trim((string) $part))->values();
            $firstCell = Str::of((string) ($parts->get(0) ?? ''))->replace("\xEF\xBB\xBF", '')->trim()->toString();
            $parts = $parts->replace([0 => $firstCell]);

            if (in_array(Str::lower($firstCell), ['organization name', 'airline name', 'name'], true)) {
                return;
            }

            $url = $parts->first(fn (string $part) => Str::contains($part, ['http://', 'https://', 'www.']));
            $first = (string) ($parts->get(0) ?? '');
            $second = (string) ($parts->get(1) ?? '');
            $countryIso = Str::length($first) === 2 && ctype_alpha($first) ? Str::upper($first) : null;
            $isAirlineList = in_array(($data['organization_type'] ?? ''), ['airlines', 'private_company'], true)
                && ($data['industry'] ?? '') === 'transport_aviation'
                && blank($url);

            $name = match (true) {
                $isAirlineList => $first,
                $countryIso && $second !== '' => $second,
                default => $parts->first(fn (string $part) => $part !== $url) ?? '',
            };

            if ($name === '' && filled($url)) {
                $name = parse_url(Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://' . $url, PHP_URL_HOST) ?: $url;
            }

            if ($name === '') {
                return;
            }

            $country = $isAirlineList
                ? (string) ($parts->get(2) ?? '')
                : ($countryIso ?: $parts
                    ->filter(fn (string $part) => $part !== $name && $part !== $url)
                    ->first());
            $country = trim((string) $country);
            if ($country === '' && filled($data['default_country'] ?? null)) {
                $country = (string) $data['default_country'];
            }
            $normalizedCountry = $normalizeCountryForOrganization($country, $countryIso);
            $additionalName = $isAirlineList ? (string) ($parts->get(1) ?? '') : '';
            $comment = $isAirlineList ? (string) ($parts->get(3) ?? '') : '';
            $normalizedUrl = filled($url)
                ? (Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://' . $url)
                : null;
            $domain = filled($normalizedUrl) ? parse_url($normalizedUrl, PHP_URL_HOST) : null;
            $fingerprint = hash('sha256', Str::lower($name . '|' . ($normalizedCountry['iso'] ?? $normalizedCountry['name']) . '|' . ($domain ?? '')));

            $organization = MarketOrganization::updateOrCreate(
                ['source_fingerprint' => $fingerprint],
                [
                    'market_crawler_id' => $data['market_crawler_id'] ?? null,
                    'name' => Str::limit($name, 255, ''),
                    'name_normalized' => Str::lower(Str::limit($name, 500, '')),
                    'organization_type' => $data['organization_type'],
                    'industry' => $data['industry'],
                    'organization_subcategory' => $data['organization_subcategory'] ?? null,
                    'country' => $normalizedCountry['name'],
                    'country_raw' => $normalizedCountry['raw'],
                    'country_iso' => $normalizedCountry['iso'],
                    'country_resolution_status' => $normalizedCountry['status'],
                    'region' => $normalizedCountry['region'],
                    'website_url' => $normalizedUrl,
                    'website_domain' => $domain ? Str::of($domain)->lower()->replace('www.', '')->toString() : null,
                    'status' => 'active',
                    'lead_status' => ($data['organization_type'] ?? '') === 'pension_fund' ? 'researching' : 'unqualified',
                    'lead_source' => ($data['organization_type'] ?? '') === 'pension_fund' ? 'pension_fund_pdf_import' : 'manual_import',
                    'notes' => trim(collect([
                        $additionalName !== '' ? 'Additional name: ' . $additionalName : null,
                        $comment !== '' ? 'Comment: ' . $comment : null,
                    ])->filter()->implode("\n")) ?: null,
                    'last_crawler_name' => optional(MarketCrawler::find($data['market_crawler_id'] ?? null))->name,
                    'source_fingerprint' => $fingerprint,
                ],
            );

            $organization->wasRecentlyCreated ? $created++ : $updated++;
    });

    return back()->with('status', 'Imported ' . $created . ' organization(s), updated ' . $updated . '.');
})->name('sls.organizations.import');

Route::get('/sls/organizations/{organization}/edit', function (MarketOrganization $organization) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $marketLeadStatuses) {
    $organization->load(['contacts' => fn ($query) => $query->orderBy('contact_type')->orderBy('person_name')]);

    return view('sls.organizations.edit', [
        'organization' => $organization,
        'types' => $marketOrganizationTypes,
        'industries' => $marketIndustries,
        'subcategories' => $marketOrganizationSubcategories,
        'leadStatuses' => $marketLeadStatuses,
    ]);
})->name('sls.organizations.edit');

Route::post('/sls/organizations/{organization}', function (Request $request, MarketOrganization $organization) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $marketLeadStatuses) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'organization_type' => ['required', 'string', 'max:80'],
        'industry' => ['required', 'string', 'max:80'],
        'organization_subcategory' => ['nullable', 'string', 'max:120'],
        'country' => ['nullable', 'string', 'max:120'],
        'country_iso' => ['nullable', 'string', 'max:2'],
        'region' => ['nullable', 'string', 'max:80'],
        'website_url' => ['nullable', 'string', 'max:2048'],
        'organization_phone' => ['nullable', 'string', 'max:120'],
        'procurement_page_url' => ['nullable', 'string', 'max:2048'],
        'leadership_page_url' => ['nullable', 'string', 'max:2048'],
        'hr_page_url' => ['nullable', 'string', 'max:2048'],
        'it_page_url' => ['nullable', 'string', 'max:2048'],
        'news_page_url' => ['nullable', 'string', 'max:2048'],
        'lead_status' => ['required', 'string', 'max:80'],
        'lead_source' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:12000'],
        'contacts' => ['array'],
        'contacts.*.id' => ['nullable', 'integer'],
        'contacts.*.delete' => ['nullable', 'boolean'],
        'contacts.*.contact_type' => ['nullable', 'string', 'max:80'],
        'contacts.*.person_name' => ['nullable', 'string', 'max:255'],
        'contacts.*.job_title' => ['nullable', 'string', 'max:255'],
        'contacts.*.email' => ['nullable', 'email', 'max:255'],
        'contacts.*.phone' => ['nullable', 'string', 'max:120'],
        'contacts.*.source_url' => ['nullable', 'string', 'max:2048'],
        'contacts.*.notes' => ['nullable', 'string', 'max:4000'],
        'new_contacts' => ['array'],
        'new_contacts.*.contact_type' => ['nullable', 'string', 'max:80'],
        'new_contacts.*.person_name' => ['nullable', 'string', 'max:255'],
        'new_contacts.*.job_title' => ['nullable', 'string', 'max:255'],
        'new_contacts.*.email' => ['nullable', 'email', 'max:255'],
        'new_contacts.*.phone' => ['nullable', 'string', 'max:120'],
        'new_contacts.*.source_url' => ['nullable', 'string', 'max:2048'],
        'new_contacts.*.notes' => ['nullable', 'string', 'max:4000'],
    ]);

    $normalizeUrl = function (?string $url): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return $url;
    };
    $domainFromUrl = function (?string $url): ?string {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? Str::of($host)->lower()->replaceStart('www.', '')->toString() : null;
    };
    $normalizeName = fn (string $name): string => Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    $contactHasContent = fn (array $contact): bool => collect([
        $contact['contact_type'] ?? null,
        $contact['person_name'] ?? null,
        $contact['job_title'] ?? null,
        $contact['email'] ?? null,
        $contact['phone'] ?? null,
        $contact['source_url'] ?? null,
        $contact['notes'] ?? null,
    ])->contains(fn ($value) => filled($value));

    DB::transaction(function () use ($data, $organization, $normalizeUrl, $domainFromUrl, $normalizeName, $contactHasContent) {
        $websiteUrl = $normalizeUrl($data['website_url'] ?? null);

        $organization->update([
            'name' => $data['name'],
            'name_normalized' => $normalizeName($data['name']),
            'organization_type' => $data['organization_type'],
            'industry' => $data['industry'],
            'organization_subcategory' => $data['organization_subcategory'] ?? null,
            'country' => $data['country'] ?? null,
            'country_iso' => filled($data['country_iso'] ?? null) ? Str::upper($data['country_iso']) : null,
            'region' => $data['region'] ?? null,
            'website_url' => $websiteUrl,
            'website_domain' => $domainFromUrl($websiteUrl),
            'organization_phone' => $data['organization_phone'] ?? null,
            'procurement_page_url' => $normalizeUrl($data['procurement_page_url'] ?? null),
            'leadership_page_url' => $normalizeUrl($data['leadership_page_url'] ?? null),
            'hr_page_url' => $normalizeUrl($data['hr_page_url'] ?? null),
            'it_page_url' => $normalizeUrl($data['it_page_url'] ?? null),
            'news_page_url' => $normalizeUrl($data['news_page_url'] ?? null),
            'lead_status' => $data['lead_status'],
            'lead_source' => $data['lead_source'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach (($data['contacts'] ?? []) as $contactData) {
            $contact = MarketOrganizationContact::query()
                ->where('market_organization_id', $organization->id)
                ->find($contactData['id'] ?? null);

            if (! $contact) {
                continue;
            }

            if (! empty($contactData['delete'])) {
                $contact->delete();
                continue;
            }

            if (! $contactHasContent($contactData)) {
                continue;
            }

            $contact->update([
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'person_name' => $contactData['person_name'] ?? null,
                'job_title' => $contactData['job_title'] ?? null,
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'source_url' => $normalizeUrl($contactData['source_url'] ?? null),
                'notes' => $contactData['notes'] ?? null,
                'verification_status' => $contact->verification_status ?: 'manual',
            ]);
        }

        foreach (($data['new_contacts'] ?? []) as $contactData) {
            if (! $contactHasContent($contactData)) {
                continue;
            }

            $organization->contacts()->create([
                'market_crawler_id' => $organization->market_crawler_id,
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'person_name' => $contactData['person_name'] ?? null,
                'job_title' => $contactData['job_title'] ?? null,
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'source_url' => $normalizeUrl($contactData['source_url'] ?? null),
                'notes' => $contactData['notes'] ?? null,
                'verification_status' => 'manual',
                'extracted_at' => now(),
                'source_fingerprint' => hash('sha256', 'manual|' . $organization->id . '|' . Str::lower((string) ($contactData['email'] ?? $contactData['person_name'] ?? microtime(true)))),
            ]);
        }
    });

    return redirect()
        ->route('sls.organizations.show', $organization)
        ->with('status', 'Organization updated.');
})->name('sls.organizations.update');

Route::get('/sls/organizations/{organization}', function (MarketOrganization $organization) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $marketLeadStatuses) {
    $organization->load([
        'crawler',
        'contacts' => fn ($query) => $query->orderBy('contact_type')->orderBy('person_name'),
        'activities' => fn ($query) => $query->latest('activity_at')->latest(),
        'tasks' => fn ($query) => $query->orderByRaw("status = 'completed' ASC")->orderBy('due_at'),
        'communications' => fn ($query) => $query->latest('sent_or_received_at')->latest(),
        'crawlerRuns' => fn ($query) => $query->with('crawler')->latest('started_at')->limit(25),
    ]);

    return view('sls.organizations.show', [
        'organization' => $organization,
        'types' => $marketOrganizationTypes,
        'industries' => $marketIndustries,
        'subcategories' => $marketOrganizationSubcategories,
        'leadStatuses' => $marketLeadStatuses,
    ]);
})->name('sls.organizations.show');

Route::post('/sls/organizations/{organization}/lead-status', function (Request $request, MarketOrganization $organization) use ($marketLeadStatuses) {
    $data = $request->validate([
        'lead_status' => ['required', 'string', 'max:80'],
        'lead_source' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:4000'],
    ]);

    if (! array_key_exists($data['lead_status'], $marketLeadStatuses)) {
        return back()->with('status', 'Lead status is not valid.');
    }

    $organization->update($data);

    return back()->with('status', 'Lead status updated.');
})->name('sls.organizations.leadStatus.update');

Route::post('/sls/organizations/{organization}/activities', function (Request $request, MarketOrganization $organization) {
    $data = $request->validate([
        'activity_type' => ['required', 'string', 'max:80'],
        'subject' => ['nullable', 'string', 'max:255'],
        'body' => ['nullable', 'string', 'max:4000'],
        'activity_at' => ['nullable', 'date'],
    ]);

    $organization->activities()->create($data + [
        'activity_at' => $data['activity_at'] ?? now(),
        'logged_by' => 'local_user',
    ]);

    return back()->with('status', 'Activity logged.');
})->name('sls.organizations.activities.store');

Route::post('/sls/organizations/{organization}/tasks', function (Request $request, MarketOrganization $organization) {
    $data = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:4000'],
        'task_type' => ['required', 'string', 'max:80'],
        'due_at' => ['nullable', 'date'],
    ]);

    $organization->tasks()->create($data + ['status' => 'open']);

    return back()->with('status', 'Task created.');
})->name('sls.organizations.tasks.store');

Route::post('/sls/organizations/tasks/{task}/complete', function (MarketOrganizationTask $task) {
    $task->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    return back()->with('status', 'Task completed.');
})->name('sls.organizations.tasks.complete');

Route::post('/sls/organizations/{organization}/communications', function (Request $request, MarketOrganization $organization) {
    $data = $request->validate([
        'channel' => ['required', 'string', 'max:80'],
        'direction' => ['required', 'in:inbound,outbound'],
        'subject' => ['nullable', 'string', 'max:255'],
        'body_excerpt' => ['nullable', 'string', 'max:4000'],
        'from_address' => ['nullable', 'email', 'max:255'],
        'to_address' => ['nullable', 'email', 'max:255'],
        'sent_or_received_at' => ['nullable', 'date'],
        'source_reference' => ['nullable', 'string', 'max:1000'],
    ]);

    $organization->communications()->create($data + [
        'sent_or_received_at' => $data['sent_or_received_at'] ?? now(),
    ]);
    $organization->update(['last_contacted_at' => now()]);

    return back()->with('status', 'Communication logged.');
})->name('sls.organizations.communications.store');

Route::get('/sls/email-accounts', function () {
    return view('sls.organizations.email-accounts', [
        'accounts' => MarketEmailAccount::query()->orderBy('email_address')->get(),
    ]);
})->name('sls.emailAccounts.index');

Route::post('/sls/email-accounts', function (Request $request) {
    $data = $request->validate([
        'account_name' => ['required', 'string', 'max:255'],
        'email_address' => ['required', 'email', 'max:255'],
        'provider' => ['nullable', 'string', 'max:80'],
    ]);

    MarketEmailAccount::updateOrCreate(
        ['email_address' => $data['email_address']],
        $data + ['sync_status' => 'not_configured'],
    );

    return back()->with('status', 'Email account placeholder added. Mailbox sync connector still needs to be configured.');
})->name('sls.emailAccounts.store');

Route::post('/sls/organizations/crawlers', function (Request $request) use ($marketCrawlerTypes) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'crawler_type' => ['required', 'string', 'max:80'],
        'description' => ['nullable', 'string', 'max:2000'],
    ]);

    if (! array_key_exists($data['crawler_type'], $marketCrawlerTypes)) {
        return back()->with('status', 'Crawler type is not valid.');
    }

    MarketCrawler::firstOrCreate([
        'crawler_key' => Str::slug($data['name'], '_'),
    ], [
        'name' => $data['name'],
        'crawler_type' => $data['crawler_type'],
        'description' => $data['description'] ?? null,
        'is_enabled' => true,
    ]);

    return back()->with('status', 'Crawler registered.');
})->name('sls.organizations.crawlers.store');

Route::get('/sls/directory-images', function () use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories, $seedMarketCrawlers) {
    $seedMarketCrawlers();

    return view('sls.directory-images.index', [
        'batches' => DirectoryImageBatch::query()->withCount(['pages', 'entries'])->latest()->limit(50)->get(),
        'types' => $marketOrganizationTypes,
        'industries' => $marketIndustries,
        'subcategories' => $marketOrganizationSubcategories,
        'crawlers' => MarketCrawler::query()->orderBy('name')->get(),
    ]);
})->name('sls.directoryImages.index');

Route::post('/sls/directory-images', function (Request $request, DirectoryImageImportService $importer) use ($marketOrganizationTypes, $marketIndustries, $marketOrganizationSubcategories) {
    $data = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'organization_type' => ['required', 'string', 'max:80'],
        'industry' => ['required', 'string', 'max:120'],
        'organization_subcategory' => ['nullable', 'string', 'max:120'],
        'market_crawler_id' => ['nullable', 'integer', 'exists:market_crawlers,id'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'images' => ['required', 'array', 'max:50'],
        'images.*' => ['file', 'mimes:jpg,jpeg,png', 'max:12288'],
    ]);

    if (! array_key_exists($data['organization_type'], $marketOrganizationTypes) || ! array_key_exists($data['industry'], $marketIndustries) || (filled($data['organization_subcategory'] ?? null) && ! array_key_exists($data['organization_subcategory'], $marketOrganizationSubcategories))) {
        return back()->with('status', 'Organization type or industry is not valid.');
    }

    $batch = $importer->createBatch($request->file('images', []), $data);

    return redirect()->route('sls.directoryImages.show', $batch)->with('status', 'Uploaded ' . $batch->image_count . ' image(s). Click Process OCR when ready.');
})->name('sls.directoryImages.store');

Route::get('/sls/directory-images/{batch}', function (DirectoryImageBatch $batch) {
    $batch->load(['pages' => fn ($query) => $query->orderBy('sort_order')->orderBy('original_filename'), 'entries' => fn ($query) => $query->orderBy('id')]);

    return response()->view('sls.directory-images.show', [
        'batch' => $batch,
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->name('sls.directoryImages.show');

Route::get('/sls/directory-images/{batch}/export', function (DirectoryImageBatch $batch) {
    $batch->load(['entries.page' => fn ($query) => $query->orderBy('sort_order')->orderBy('original_filename')]);
    $filename = Str::slug($batch->title ?: 'directory-image-batch-' . $batch->id) . '-draft-entries.csv';

    return response()->streamDownload(function () use ($batch) {
        $handle = fopen('php://output', 'w');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'entry_id',
            'page_file',
            'review_status',
            'organization_name',
            'country_normalized',
            'country_raw',
            'country_iso',
            'website',
            'email',
            'phone',
            'executive_text',
            'address_text',
            'confidence',
            'raw_text_reference',
        ]);

        foreach ($batch->entries()->with('page')->orderBy('id')->cursor() as $entry) {
            fputcsv($handle, [
                $entry->id,
                $entry->page?->original_filename,
                $entry->review_status,
                $entry->organization_name,
                $entry->country_normalized,
                $entry->country_raw,
                $entry->country_iso,
                $entry->website,
                $entry->email,
                $entry->phone,
                $entry->executive_text,
                $entry->address_text,
                $entry->confidence,
                $entry->raw_text,
            ]);
        }

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->name('sls.directoryImages.export');

Route::post('/sls/directory-images/{batch}/import-corrections', function (Request $request, DirectoryImageBatch $batch) {
    $data = $request->validate([
        'corrections' => ['required', 'file', 'mimes:csv,txt', 'max:12288'],
    ]);

    $path = $request->file('corrections')->getRealPath();
    $handle = fopen($path, 'r');

    if (! $handle) {
        return back()->with('status', 'Could not read the uploaded correction file.');
    }

    $header = fgetcsv($handle);

    if (! is_array($header)) {
        fclose($handle);

        return back()->with('status', 'The correction file did not contain a CSV header row.');
    }

    $header = collect($header)
        ->map(fn ($value) => Str::of((string) $value)->replace("\xEF\xBB\xBF", '')->lower()->snake()->toString())
        ->all();

    $allowedColumns = [
        'organization_name',
        'country_normalized',
        'country_raw',
        'country_iso',
        'website',
        'email',
        'phone',
        'executive_text',
        'address_text',
    ];

    $updated = 0;
    $skipped = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $record = [];

        foreach ($header as $index => $column) {
            $record[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        $entryId = (int) ($record['entry_id'] ?? 0);

        if ($entryId <= 0) {
            $skipped++;
            continue;
        }

        $entry = DirectoryImageEntry::query()
            ->where('directory_image_batch_id', $batch->id)
            ->whereKey($entryId)
            ->first();

        if (! $entry) {
            $skipped++;
            continue;
        }

        $updates = collect($allowedColumns)
            ->filter(fn (string $column) => array_key_exists($column, $record))
            ->mapWithKeys(fn (string $column) => [$column => $record[$column] === '' ? null : $record[$column]])
            ->all();

        if ($updates === []) {
            $skipped++;
            continue;
        }

        $entry->update($updates + [
            'review_status' => $entry->review_status === 'imported' ? 'imported' : 'needs_review',
        ]);

        $updated++;
    }

    fclose($handle);

    return back()->with('status', 'Imported spreadsheet corrections. Updated ' . $updated . ' draft entrie(s); skipped ' . $skipped . ' row(s).');
})->name('sls.directoryImages.importCorrections');

Route::post('/sls/directory-images/{batch}/process', function (DirectoryImageBatch $batch, DirectoryImageImportService $importer) {
    $result = $importer->process($batch);

    return back()->with('status', 'Processed ' . $result['pages_processed'] . ' image(s), found ' . $result['entries_found'] . ' draft entrie(s). Review before importing.');
})->name('sls.directoryImages.process');

Route::patch('/sls/directory-image-entries/{entry}', function (Request $request, DirectoryImageEntry $entry) {
    $data = $request->validate([
        'organization_name' => ['nullable', 'string', 'max:255'],
        'country_normalized' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'email' => ['nullable', 'string', 'max:255', 'regex:/^\S+@\S+\.\S+$/'],
        'phone' => ['nullable', 'string', 'max:80'],
        'executive_text' => ['nullable', 'string'],
        'address_text' => ['nullable', 'string'],
    ]);

    $entry->update($data + ['review_status' => $entry->review_status === 'imported' ? 'imported' : 'needs_review']);

    return back()->with('status', 'Saved draft edits for ' . ($entry->organization_name ?: 'entry #' . $entry->id) . '.');
})->name('sls.directoryImageEntries.update');

Route::post('/sls/directory-image-entries/{entry}/import', function (Request $request, DirectoryImageEntry $entry, DirectoryImageImportService $importer) {
    $data = $request->validate([
        'organization_name' => ['nullable', 'string', 'max:255'],
        'country_normalized' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'email' => ['nullable', 'string', 'max:255', 'regex:/^\S+@\S+\.\S+$/'],
        'phone' => ['nullable', 'string', 'max:80'],
    ]);

    $organization = $importer->importEntry($entry, $data);

    return back()->with('status', 'Imported ' . $organization->name . ' into the organization directory.');
})->name('sls.directoryImageEntries.import');

Route::post('/sls/intelligence/priorities', function (Request $request) {
    $data = $request->validate([
        'country_iso' => ['required', 'string', 'max:8'],
        'country_name' => ['nullable', 'string', 'max:255'],
        'focus' => ['required', 'string', 'max:80'],
    ]);

    IntelligenceMonitorPriority::query()->updateOrCreate(
        [
            'country_iso' => strtoupper((string) $data['country_iso']),
            'focus' => $data['focus'],
            'status' => 'pending',
        ],
        [
            'country_name' => $data['country_name'] ?? null,
            'requested_at' => now(),
        ],
    );

    return back()->with('status', ($data['country_name'] ?? $data['country_iso']) . ' was moved to the front of the ' . $data['focus'] . ' monitor queue.');
})->name('sls.intelligence.priorities.store');

Route::post('/sls/intelligence/updates/{countryUpdate}/journalist', function (CountryUpdate $countryUpdate, JournalistDiscoveryService $discovery) {
    $article = $discovery->capture($countryUpdate);
    $journalist = $article->journalist;

    return back()->with('status', 'Added ' . ($journalist?->name ?: 'journalist') . ' to the journalist directory for this story.');
})->name('sls.intelligence.journalists.capture');

Route::get('/sls/intelligence/journalists', function (Request $request) {
    $search = trim((string) $request->query('q', ''));
    $journalists = Journalist::query()
        ->with(['publicationCountry', 'articles.countryUpdate.country'])
        ->withCount('articles')
        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('publication_name', 'like', '%' . $search . '%')
                    ->orWhere('publication_country_name', 'like', '%' . $search . '%');
            });
        })
        ->latest('last_seen_at')
        ->latest('updated_at')
        ->paginate(25)
        ->withQueryString();

    return view('sls.intelligence.journalists', compact('journalists', 'search'));
})->name('sls.intelligence.journalists.index');

Route::get('/sls/intelligence/journalists/{journalist}', function (Journalist $journalist) {
    $journalist->load(['publicationCountry', 'articles.countryUpdate.country']);

    return view('sls.intelligence.journalist-profile', compact('journalist'));
})->name('sls.intelligence.journalists.show');

Route::patch('/sls/intelligence/journalists/{journalist}', function (Request $request, Journalist $journalist) {
    $data = $request->validate([
        'name' => ['nullable', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'publication_name' => ['nullable', 'string', 'max:255'],
        'publication_country_name' => ['nullable', 'string', 'max:255'],
        'profile_summary' => ['nullable', 'string'],
        'background' => ['nullable', 'string'],
        'education' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
    ]);

    $data['name_normalized'] = Str::lower(trim(Str::ascii((string) ($data['name'] ?? $journalist->name))));
    $journalist->update($data);

    return back()->with('status', 'Journalist profile updated.');
})->name('sls.intelligence.journalists.update');

Route::patch('/sls/intelligence/journalist-articles/{journalistArticle}', function (Request $request, JournalistArticle $journalistArticle) {
    $data = $request->validate([
        'journalist_id' => ['required', 'integer', 'exists:journalists,id'],
        'praise_note' => ['nullable', 'string'],
        'outreach_status' => ['nullable', 'string', 'max:80'],
    ]);

    $journalistArticle->update($data);

    return back()->with('status', 'Journalist article note updated.');
})->name('sls.intelligence.journalistArticles.update');
Route::post('/sls/intelligence/updates/{countryUpdate}/opportunity', function (Request $request, CountryUpdate $countryUpdate, CountryStoryOpportunityService $opportunities) {
    $data = $request->validate([
        'product_id' => ['required', 'integer', 'exists:products,id'],
    ]);
    $product = Product::query()->find((int) $data['product_id']);
    $opportunity = $opportunities->createForProduct($countryUpdate->load('country'), $product);

    return redirect()
        ->route('sls.opportunities.show', $opportunity)
        ->with('status', ($product?->name ?? 'Product') . ' opportunity draft created from the selected intelligence item.');
})->name('sls.intelligence.opportunities.store');

Route::post('/sls/intelligence/updates/{countryUpdate}/country', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'country_id' => ['required', 'integer', 'exists:countries,id'],
        'return_to' => ['nullable', 'string', 'max:2000'],
    ]);

    $countryUpdate->update(['country_id' => $data['country_id']]);

    $returnTo = (string) ($data['return_to'] ?? '');

    if ($returnTo !== '' && Str::startsWith($returnTo, url('/'))) {
        return redirect($returnTo)->with('status', 'Country updated for ' . ('INT-' . str_pad((string) $countryUpdate->id, 5, '0', STR_PAD_LEFT)) . '.');
    }

    return back()->with('status', 'Country updated for ' . ('INT-' . str_pad((string) $countryUpdate->id, 5, '0', STR_PAD_LEFT)) . '.');
})->name('sls.intelligence.updates.country');

Route::post('/sls/intelligence/updates/{countryUpdate}/drop', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'reason_code' => ['required', 'string', 'in:not_relevant,wrong_product,wrong_country,duplicate,old_or_awarded,spam_or_scrape,other'],
        'reason' => ['nullable', 'string', 'max:1000'],
        'return_to' => ['nullable', 'string', 'max:2000'],
    ]);

    $countryUpdate->update([
        'review_status' => 'rejected',
        'rejection_reason_code' => $data['reason_code'],
        'rejection_reason' => $data['reason'] ?? null,
        'rejected_at' => now(),
    ]);

    $serial = 'INT-' . str_pad((string) $countryUpdate->id, 5, '0', STR_PAD_LEFT);
    $returnTo = (string) ($data['return_to'] ?? '');
    $safeReturn = $returnTo !== '' && (
        Str::startsWith($returnTo, url('/'))
        || Str::startsWith($returnTo, $request->getSchemeAndHttpHost())
    );

    if ($safeReturn) {
        return redirect($returnTo)->with('status', $serial . ' was dropped from active review.');
    }

    return back()->with('status', $serial . ' was dropped from active review.');
})->name('sls.intelligence.updates.drop');

Route::post('/sls/intelligence/updates/{countryUpdate}/restore', function (Request $request, CountryUpdate $countryUpdate) {
    $data = $request->validate([
        'return_to' => ['nullable', 'string', 'max:2000'],
    ]);

    $countryUpdate->update([
        'review_status' => 'unreviewed',
        'rejection_reason_code' => null,
        'rejection_reason' => null,
        'rejected_at' => null,
    ]);

    $serial = 'INT-' . str_pad((string) $countryUpdate->id, 5, '0', STR_PAD_LEFT);
    $returnTo = (string) ($data['return_to'] ?? '');
    $safeReturn = $returnTo !== '' && (
        Str::startsWith($returnTo, url('/'))
        || Str::startsWith($returnTo, $request->getSchemeAndHttpHost())
    );

    if ($safeReturn) {
        return redirect($returnTo)->with('status', $serial . ' was restored to active review.');
    }

    return back()->with('status', $serial . ' was restored to active review.');
})->name('sls.intelligence.updates.restore');

Route::get('/sls/opportunities', function () {
    return view('sls.opportunities.index', [
        'opportunities' => CountryUpdateOpportunity::query()
            ->with('countryUpdate.country', 'product')
            ->latest()
            ->limit(100)
            ->get(),
    ]);
})->name('sls.opportunities.index');

Route::get('/sls/opportunities/{opportunity}', function (CountryUpdateOpportunity $opportunity) {
    return view('sls.opportunities.show', [
        'opportunity' => $opportunity->load('countryUpdate.country', 'product'),
        'knowledgeChunks' => KnowledgeChunk::query()
            ->with('sourceDocument', 'product')
            ->whereIn('id', $opportunity->knowledge_chunk_ids ?? [])
            ->get(),
        'demoMoments' => DemoFeatureMoment::query()
            ->with('demoSession.product', 'product')
            ->whereIn('id', $opportunity->demo_feature_moment_ids ?? [])
            ->get(),
        'demoFrames' => DemoFrame::query()
            ->whereIn('id', $opportunity->demo_frame_ids ?? [])
            ->orderBy('timestamp_ms')
            ->get(),
    ]);
})->name('sls.opportunities.show');

Route::post('/sls/opportunities/{opportunity}/email', function (Request $request, CountryUpdateOpportunity $opportunity) {
    $data = $request->validate([
        'suggested_email' => ['required', 'string', 'max:12000'],
    ]);

    $opportunity->update([
        'suggested_email' => $data['suggested_email'],
    ]);

    return back()->with('status', 'Email draft saved.');
})->name('sls.opportunities.email.update');

Route::post('/sls/opportunities/{opportunity}/email/regenerate', function (CountryUpdateOpportunity $opportunity, OpportunityEmailDraftService $emailDrafts) {
    $opportunity->load('countryUpdate.country', 'product');
    $chunks = KnowledgeChunk::query()
        ->whereIn('id', $opportunity->knowledge_chunk_ids ?? [])
        ->get();

    $fallback = $opportunity->suggested_email ?: 'Subject: Follow-up on ' . ($opportunity->countryUpdate?->title_english ?: $opportunity->countryUpdate?->title ?: 'recent item');
    $draft = $emailDrafts->draft(
        $opportunity->countryUpdate,
        (string) $opportunity->issue_area,
        (string) $opportunity->issue_summary,
        (string) $opportunity->product_alignment,
        $chunks,
        $opportunity->product,
        $fallback,
    );

    $opportunity->update(['suggested_email' => $draft]);

    return back()->with('status', $draft === $fallback ? 'Kept existing draft because AI drafting was unavailable.' : 'AI email draft regenerated.');
})->name('sls.opportunities.email.regenerate');

$seedIntelligenceRegistries = function () use ($allMapCountries) {
    if (Schema::hasTable('intelligence_sources')) {
        collect(config('country_intelligence.development_partner_sources', []))
            ->each(function (array $source) {
                IntelligenceSource::updateOrCreate([
                    'country_iso' => null,
                    'domain' => $source['domain'] ?? null,
                    'url' => $source['url'] ?? null,
                ], [
                    'name' => $source['name'] ?? $source['domain'] ?? 'Unnamed donor source',
                    'source_class' => 'donor_tender_portal',
                    'procurement_portal_type' => $source['procurement_portal_type'] ?? 'donor_global',
                    'focus' => 'tenders',
                    'access_method' => $source['access_method'] ?? null,
                    'connector' => $source['connector'] ?? null,
                    'registration_status' => $source['registration_status'] ?? 'none',
                    'registration_notes' => $source['registration_notes'] ?? null,
                    'is_enabled' => true,
                ]);
            });

        collect(config('country_intelligence.news_aggregator_sources', []))
            ->each(function (array $source) {
                IntelligenceSource::updateOrCreate([
                    'country_iso' => null,
                    'domain' => $source['domain'] ?? null,
                    'url' => $source['url'] ?? null,
                ], [
                    'name' => $source['name'] ?? $source['domain'] ?? 'Unnamed news aggregator',
                    'source_class' => 'news_aggregator',
                    'procurement_portal_type' => 'not_applicable',
                    'focus' => 'news',
                    'access_method' => $source['access_method'] ?? null,
                    'connector' => $source['connector'] ?? null,
                    'registration_status' => $source['registration_status'] ?? 'none',
                    'registration_notes' => $source['registration_notes'] ?? null,
                    'is_enabled' => true,
                ]);
            });

        $mapCountries = $allMapCountries()->keyBy('iso');
        collect(array_replace_recursive(
            config('country_intelligence.monitored_countries', []),
            config('country_intelligence.countries', [])
        ))
            ->each(function (array $countryConfig, string $iso) use ($mapCountries) {
                foreach ($countryConfig['sources'] ?? [] as $source) {
                    $sourceText = Str::lower(implode(' ', [
                        $source['category'] ?? '',
                        $source['name'] ?? '',
                        $source['domain'] ?? '',
                        $source['url'] ?? '',
                    ]));
                    $sourceClass = match (true) {
                        Str::contains($sourceText, ['tender', 'procurement', 'rfp', 'gojep', 'ppc.gov', 'senoffre']) => 'central_tender_portal',
                        Str::contains($sourceText, ['social security', 'socialsecurity', 'seguro social', 'seguridad social', 'seguros sociales', 'prevision social', 'prevision', 'previdencia', 'pensiones', 'pension board', 'national insurance', 'ipres', 'cnss', 'nssf', 'anses', 'gestora', 'aps.gob', 'inss', 'imss', 'issste', 'iess', 'igss', 'ihss', 'onp', 'bps', 'ivss', 'colpensiones', 'spensiones']) => 'social_security_admin',
                        Str::contains($sourceText, ['ministry', 'ministere', 'labour', 'labor', 'government', 'gazette']) => 'government',
                        default => 'local_media',
                    };

                    IntelligenceSource::updateOrCreate([
                        'country_iso' => strtoupper((string) $iso),
                        'domain' => $source['domain'] ?? null,
                        'url' => $source['url'] ?? null,
                    ], [
                        'country_iso' => strtoupper((string) $iso),
                        'region' => $mapCountries->get(strtoupper((string) $iso))['region_group'] ?? null,
                        'name' => $source['name'] ?? $source['domain'] ?? 'Unnamed source',
                        'source_class' => $sourceClass,
                        'procurement_portal_type' => $source['procurement_portal_type'] ?? ($sourceClass === 'central_tender_portal' ? 'government' : 'not_applicable'),
                        'focus' => $sourceClass === 'central_tender_portal' ? 'tenders' : 'news',
                        'access_method' => $source['access_method'] ?? null,
                        'connector' => $source['type'] ?? $source['connector'] ?? null,
                        'registration_status' => $source['registration_status'] ?? 'unknown',
                        'registration_notes' => $source['registration_notes'] ?? null,
                        'is_enabled' => true,
                    ]);
                }
            });
    }

    if (Schema::hasTable('intelligence_keywords')) {
        collect(config('country_intelligence.focuses', []))
            ->each(function (array $focusConfig, string $focus) {
                collect($focusConfig['terms'] ?? [])
                    ->merge($focusConfig['strong_signals'] ?? [])
                    ->unique()
                    ->each(fn (string $term) => IntelligenceKeyword::firstOrCreate([
                        'focus' => $focus,
                        'term' => $term,
                        'language_code' => 'en',
                    ], [
                        'category' => 'core',
                        'is_enabled' => true,
                    ]));
            });

        collect(config('country_intelligence.source_discovery_terms', []))
            ->each(function (array $terms, string $languageCode) {
                collect($terms)
                    ->unique()
                    ->each(function (string $term) use ($languageCode) {
                        $category = match (true) {
                            Str::contains(Str::lower($term), ['procurement', 'marches publics', 'contratacao', 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â´ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂªÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âª']) => 'procurement_portal',
                            Str::contains(Str::lower($term), ['civil service', 'public service', 'fonction publique', 'funcao publica', 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â®ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©']) => 'civil_service',
                            Str::contains(Str::lower($term), ['ministry', 'ministere', 'ministerio', 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â²ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¹ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©', 'labour', 'labor', 'travail', 'trabalho']) => 'labour_ministry',
                            default => 'social_security_admin',
                        };

                        IntelligenceKeyword::firstOrCreate([
                            'focus' => 'source_discovery',
                            'term' => $term,
                            'language_code' => $languageCode,
                        ], [
                            'category' => $category,
                            'is_enabled' => true,
                        ]);
                    });
            });
    }
};

Route::get('/sls/intelligence/sources', function (Request $request) use ($allMapCountries, $seedIntelligenceRegistries) {
    $seedIntelligenceRegistries();
    $region = Str::of((string) $request->query('region', 'all'))->lower()->toString();
    $statusFilter = Str::of((string) $request->query('status', 'all'))->lower()->toString();
    $sourceClassFilter = (string) $request->query('source_class', 'all');
    $focusFilter = (string) $request->query('focus', 'all');
    $accessFilter = (string) $request->query('access', 'all');
    $portalTypeFilter = (string) $request->query('portal_type', 'all');
    $registrationFilter = (string) $request->query('registration', 'all');
    $queueFilter = Str::of((string) $request->query('queue', 'all'))->lower()->toString();
    $mapCountries = $allMapCountries();
    $austinTz = 'America/Chicago';
    $sourceNextRunMap = function ($scheduledCountries, array $slots, int $cycleSize): array {
        $countries = collect($scheduledCountries)->sortBy('iso')->keyBy('iso');
        $countryCount = $countries->count();

        if ($countryCount === 0 || $slots === []) {
            return [];
        }

        $timezone = 'America/Chicago';
        $now = now($timezone);
        $slotsPerDay = count($slots);
        $totalBatches = (int) ceil($countryCount / max(1, $cycleSize));
        $nextRuns = [];

        for ($dayOffset = 0; $dayOffset <= $totalBatches + 2; $dayOffset++) {
            $date = $now->copy()->addDays($dayOffset);

            foreach ($slots as $slotIndex => $runTime) {
                $runAt = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $runTime, $timezone);

                if ($runAt->lessThanOrEqualTo($now)) {
                    continue;
                }

                $batchIndex = (((max(0, $runAt->dayOfYear - 1) * $slotsPerDay) + $slotIndex) % $totalBatches);
                $selectedCountries = $countries
                    ->slice($batchIndex * max(1, $cycleSize), max(1, $cycleSize))
                    ->keys();

                foreach ($selectedCountries as $iso) {
                    if (! isset($nextRuns[$iso])) {
                        $nextRuns[$iso] = $runAt->copy();
                    }
                }

                if (count($nextRuns) >= $countryCount) {
                    break 2;
                }
            }
        }

        return $nextRuns;
    };
    $nextTimeTodayOrTomorrow = function (string $time) use ($austinTz): Carbon {
        $now = now($austinTz);
        $runAt = Carbon::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $austinTz);

        return $runAt->lessThanOrEqualTo($now) ? $runAt->addDay() : $runAt;
    };
    $crawlerSetting = function (string $key, mixed $default = null): mixed {
        try {
            if (! Schema::hasTable('crawler_settings')) {
                return $default;
            }

            $value = DB::table('crawler_settings')->where('setting_key', $key)->value('setting_value');

            return filled($value) ? $value : $default;
        } catch (Throwable) {
            return $default;
        }
    };
    $crawlerTimeList = function (string $key, array $default) use ($crawlerSetting): array {
        return collect(explode(',', (string) $crawlerSetting($key, implode(',', $default))))
            ->map(fn (string $time) => trim($time))
            ->filter(fn (string $time) => preg_match('/^\d{2}:\d{2}$/', $time) === 1)
            ->values()
            ->all();
    };
    $nextGlobalHrmsSweep = $nextTimeTodayOrTomorrow((string) $crawlerSetting('global_hrms_tender_sweep_time', env('SLS_GLOBAL_HRMS_TENDER_SWEEP_TIME', '02:35')));
    $nextGlobalErmsSweep = $nextTimeTodayOrTomorrow((string) $crawlerSetting('global_erms_tender_sweep_time', env('SLS_GLOBAL_ERMS_TENDER_SWEEP_TIME', '03:05')));
    $nextGlobalEbpcSweep = $nextTimeTodayOrTomorrow((string) $crawlerSetting('global_ebpc_tender_sweep_time', env('SLS_GLOBAL_EBPC_TENDER_SWEEP_TIME', '03:15')));
    $nextGlobalSocialSweep = $nextTimeTodayOrTomorrow((string) $crawlerSetting('global_social_tender_sweep_time', env('SLS_GLOBAL_SOCIAL_TENDER_SWEEP_TIME', '02:55')));
    $allSourceCountries = $allMapCountries();
    $socialSecuritySourceNextRuns = $sourceNextRunMap(
        $allSourceCountries,
        $crawlerTimeList('social_security_daily_slots', config('country_intelligence.daily_slots', [])),
        (int) $crawlerSetting('daily_batch_size', config('country_intelligence.daily_batch_size', 1)),
    );
    $hrmsSourceNextRuns = $sourceNextRunMap(
        $allSourceCountries->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true))->values(),
        $crawlerTimeList('hrms_tender_slots', config('country_intelligence.hrms_tender_slots', [])),
        1,
    );
    $sectorSourceNextRuns = $sourceNextRunMap(
        $allSourceCountries->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true))->values(),
        $crawlerTimeList('sector_tender_slots', config('country_intelligence.sector_tender_slots', [])),
        1,
    );

    if (in_array($region, ['africa', 'caribbean', 'asia', 'latin_america', 'north_america', 'europe'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => Str::lower($country['region_group']) === str_replace('_', ' ', $region))
            ->values();
    } elseif (in_array($region, ['africa_asia', 'asia_africa'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean', 'caribbean_africa_asia'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america', 'africa_asia_latin_america_caribbean'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_caribbean_latin_america_north_america', 'africa_asia_caribbean_latin_america_north_america_europe', 'all_core_regions'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'], true))
            ->values();
    } elseif (in_array($region, ['africa_asia_latin_america', 'latin_america_africa_asia'], true)) {
        $mapCountries = $mapCountries
            ->filter(fn (array $country) => in_array(Str::lower($country['region_group']), ['africa', 'asia', 'latin america'], true))
            ->values();
    }

    $latestRunsByCountryIso = CountryMonitorRun::query()
        ->with('country')
        ->latest('finished_at')
        ->get()
        ->filter(fn (CountryMonitorRun $run) => filled($run->country?->iso_code))
        ->unique(fn (CountryMonitorRun $run) => strtoupper((string) $run->country->iso_code))
        ->keyBy(fn (CountryMonitorRun $run) => strtoupper((string) $run->country->iso_code));
    $latestGlobalRun = CountryMonitorRun::query()->latest('finished_at')->first();
    $publishedSinceForSourceCounts = now()->subDays(120)->startOfDay();
    $tenderSignalTermsForSourceCounts = [
        'tender',
        'procurement',
        'rfp',
        'request for proposal',
        'request for proposals',
        'request for bids',
        'invitation for bids',
        'expression of interest',
        'expressions of interest',
        'bidding document',
        'bid notice',
        'consultancy services',
        'consulting services',
    ];
    $sourceDomainMatches = function (string $candidateHost, string $sourceDomain): bool {
        $candidateHost = Str::of($candidateHost)->lower()->replace('www.', '')->toString();
        $sourceDomain = Str::of($sourceDomain)->lower()->replace('www.', '')->toString();

        return $candidateHost !== ''
            && $sourceDomain !== ''
            && ($candidateHost === $sourceDomain || Str::endsWith($candidateHost, '.' . $sourceDomain));
    };
    $capturedTenderUpdates = CountryUpdate::query()
        ->select(['id', 'title', 'title_english', 'title_original', 'summary', 'source_name', 'source_url', 'publication_date'])
        ->where('review_status', '<>', 'rejected')
        ->get()
        ->filter(function (CountryUpdate $update) use ($tenderSignalTermsForSourceCounts) {
            $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->title_original . ' ' . $update->summary . ' ' . $update->source_name . ' ' . $update->source_url);

            return Str::contains($text, $tenderSignalTermsForSourceCounts);
        })
        ->unique(fn (CountryUpdate $update) => filled($update->source_url) ? Str::lower((string) $update->source_url) : 'update:' . $update->id)
        ->values();

    $allManagedSources = Schema::hasTable('intelligence_sources')
        ? IntelligenceSource::query()
            ->when(in_array($region, ['africa', 'caribbean', 'asia', 'latin_america', 'north_america', 'europe'], true), fn ($query) => $query->where(function ($regionQuery) use ($region) {
                $regionQuery->whereNull('country_iso')->orWhere('region', Str::title(str_replace('_', ' ', $region)));
            }))
            ->orderByRaw("country_iso IS NULL DESC")
            ->orderBy('region')
            ->orderBy('country_iso')
            ->orderBy('source_class')
            ->orderBy('name')
            ->get()
            ->map(function (IntelligenceSource $source) use ($latestRunsByCountryIso, $latestGlobalRun, $nextGlobalHrmsSweep, $nextGlobalErmsSweep, $nextGlobalEbpcSweep, $nextGlobalSocialSweep, $socialSecuritySourceNextRuns, $hrmsSourceNextRuns, $sectorSourceNextRuns, $capturedTenderUpdates, $publishedSinceForSourceCounts, $sourceDomainMatches) {
                $lastRun = $source->country_iso
                    ? $latestRunsByCountryIso->get(strtoupper((string) $source->country_iso))
                    : $latestGlobalRun;

                $source->computed_last_checked_at = $source->last_checked_at ?? $lastRun?->finished_at;
                $sourceIso = strtoupper((string) $source->country_iso);
                $source->computed_next_checked_at = $source->force_next_at ?: match (true) {
                    filled($source->country_iso) && in_array($source->focus, ['hrms_tenders', 'tenders'], true) => $hrmsSourceNextRuns[$sourceIso] ?? null,
                    filled($source->country_iso) && in_array($source->focus, ['erms_tenders', 'ebpc_tenders'], true) => $hrmsSourceNextRuns[$sourceIso] ?? null,
                    filled($source->country_iso) && $source->focus === 'sector_tenders' => $sectorSourceNextRuns[$sourceIso] ?? null,
                    filled($source->country_iso) => $socialSecuritySourceNextRuns[$sourceIso] ?? null,
                    $source->focus === 'erms_tenders' => $nextGlobalErmsSweep,
                    $source->focus === 'ebpc_tenders' => $nextGlobalEbpcSweep,
                    in_array($source->focus, ['hrms_tenders', 'tenders', 'sector_tenders'], true) => $nextGlobalHrmsSweep,
                    $source->focus === 'social_security' => $nextGlobalSocialSweep,
                    default => $nextGlobalHrmsSweep,
                };
                $source->schedule_explanation = filled($source->country_iso)
                    ? 'Country cycle based on region/focus'
                    : 'Global donor sweep';
                $source->access_setup_label = match (true) {
                    $source->connector === 'ted' => 'Official API: TED',
                    $source->connector === 'usaid_business_forecast' => 'Official API: USAID',
                    $source->connector === 'idb_procurement' => 'Official API: IDB',
                    $source->connector === 'tendersgo_api' => 'API credentials needed: TendersGo',
                    $source->connector === 'assortis_portal' => 'Aggregator portal/API check: Assortis',
                    $source->connector === 'world_bank' || Str::contains(Str::lower((string) $source->domain), 'search.worldbank.org') => 'Official API: World Bank',
                    $source->connector === 'wordpress' => 'WordPress API',
                    $source->connector === 'adb_procurement' => 'ADB indexed fallback; portal blocked',
                    $source->connector === 'afdb_procurement' => 'AfDB indexed procurement search',
                    $source->connector === 'giz_procurement' => 'GIZ procurement search',
                    $source->connector === 'kfw_gtai_search' => 'KfW/GTAI indexed tender search',
                    $source->connector === 'ebrd_ecepp' => 'Official HTML table: EBRD ECEPP',
                    $source->connector === 'eib_procurement' => 'Official JSON endpoint: EIB',
                    $source->connector === 'portal_html' && Str::contains(Str::lower((string) $source->domain), 'developmentaid.org') => 'Aggregator portal search',
                    $source->connector === 'portal_html' => 'Basic portal scan',
                    $source->connector === 'site_search' => 'Basic website search',
                    $source->connector === 'manual_review' || $source->access_method === 'manual_review' => 'Manual connector needed',
                    $source->access_method === 'official_api' => 'Official API',
                    $source->access_method === 'official_portal' => 'Basic portal scan',
                    $source->access_method === 'website_search' => 'Basic website search',
                    $source->access_method === 'basic_crawler' || $source->connector === 'site_crawler' => 'Basic website crawler',
                    $source->access_method === 'rss_detected' => 'RSS/feed detected',
                    $source->access_method === 'rss' || $source->connector === 'rss' => 'RSS feed',
                    $source->registration_status === 'required' => 'Registration required',
                    filled($source->access_method) || filled($source->connector) => trim(collect([$source->access_method, $source->connector])->filter()->implode(' / ')),
                    default => 'None configured',
                };
                $source->connection_status_label = match (true) {
                    $source->access_setup_label === 'Manual connector needed' => 'Manual intervention needed',
                    $source->access_setup_label === 'None configured' => 'Not configured',
                    blank($source->computed_last_checked_at) => 'Configured, not checked yet',
                    filled($source->last_error) => 'Configured, last check had an issue',
                    default => 'Configured',
                };
                $source->connection_status_class = match ($source->connection_status_label) {
                    'Configured' => 'good',
                    'Configured, not checked yet' => 'warn',
                    default => 'bad',
                };
                $sourceDomain = Str::of((string) $source->domain)->lower()->replace('www.', '')->toString();
                $source->audit_count = Schema::hasTable('intelligence_source_audits')
                    ? IntelligenceSourceAudit::query()
                        ->where(function ($query) use ($source, $sourceDomain) {
                            $query->where('intelligence_source_id', $source->id);

                            if ($sourceDomain !== '') {
                                $query->orWhereRaw('LOWER(REPLACE(COALESCE(domain, ""), "www.", "")) = ?', [$sourceDomain])
                                    ->orWhereRaw('LOWER(REPLACE(COALESCE(domain, ""), "www.", "")) LIKE ?', ['%.' . $sourceDomain]);
                            }
                        })
                        ->count()
                    : 0;
                $sourceName = Str::lower(trim((string) $source->name));
                $sourceDomain = Str::of((string) $source->domain)->lower()->replace('www.', '')->toString();
                $sourceUrlHost = Str::of(parse_url((string) $source->url, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString();
                $matchedTenders = $capturedTenderUpdates
                    ->filter(function (CountryUpdate $update) use ($sourceName, $sourceDomain, $sourceUrlHost, $sourceDomainMatches) {
                        $updateSourceName = Str::lower(trim((string) $update->source_name));
                        $updateHost = (string) parse_url((string) $update->source_url, PHP_URL_HOST);

                        return ($sourceName !== '' && $updateSourceName === $sourceName)
                            || $sourceDomainMatches($updateHost, $sourceDomain)
                            || $sourceDomainMatches($updateHost, $sourceUrlHost);
                    })
                    ->values();
                $source->captured_tenders_total_count = $matchedTenders->count();
                $source->captured_tenders_last_120_count = $matchedTenders
                    ->filter(fn (CountryUpdate $update) => $update->publication_date && $update->publication_date->greaterThanOrEqualTo($publishedSinceForSourceCounts))
                    ->count();
                if ($source->captured_tenders_total_count === 0 && $source->audit_count > 0) {
                    $source->connection_status_label = 'No parsed tender output';
                    $source->connection_status_class = 'warn';
                }

                return $source;
            })
        : collect();

    $managedSources = $allManagedSources
        ->filter(fn (IntelligenceSource $source) => match ($statusFilter) {
            'enabled' => $source->is_enabled,
            'disabled' => ! $source->is_enabled,
            default => true,
        })
        ->filter(fn (IntelligenceSource $source) => $sourceClassFilter === 'all' || $source->source_class === $sourceClassFilter)
        ->filter(fn (IntelligenceSource $source) => $portalTypeFilter === 'all' || $source->procurement_portal_type === $portalTypeFilter)
        ->filter(fn (IntelligenceSource $source) => $registrationFilter === 'all' || $source->registration_status === $registrationFilter)
        ->filter(fn (IntelligenceSource $source) => $focusFilter === 'all' || $source->focus === $focusFilter)
        ->filter(fn (IntelligenceSource $source) => $accessFilter === 'all' || Str::slug((string) $source->access_setup_label) === $accessFilter)
        ->filter(fn (IntelligenceSource $source) => match ($queueFilter) {
            'forced' => filled($source->force_next_at),
            'normal' => blank($source->force_next_at),
            default => true,
        })
        ->values();

    $sourcesByCountryIso = $managedSources
        ->filter(fn (IntelligenceSource $source) => filled($source->country_iso))
        ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso));
    $rows = $mapCountries->map(function (array $country) use ($sourcesByCountryIso) {
        $sources = $sourcesByCountryIso->get(strtoupper((string) $country['iso']), collect());

        return [
            'name' => $country['name'],
            'iso' => $country['iso'],
            'region' => $country['region_group'],
            'sources' => $sources,
            'has_social_security_admin' => $sources->contains('source_class', 'social_security_admin'),
            'has_ministry' => $sources->contains('source_class', 'government'),
            'has_tender' => $sources->contains('source_class', 'central_tender_portal'),
            'has_media' => $sources->contains('source_class', 'local_media'),
        ];
    })->sortBy(['region', 'name'])->values();

    return view('sls.intelligence.sources', [
        'rows' => $rows,
        'region' => $region,
        'completeCount' => $rows->filter(fn (array $row) => $row['has_social_security_admin'] && $row['has_ministry'] && $row['has_tender'] && $row['has_media'])->count(),
        'managedSources' => $managedSources,
        'allManagedSources' => $allManagedSources,
        'countriesForSourceForm' => $allMapCountries()->sortBy('name')->values(),
        'filters' => [
            'status' => $statusFilter,
            'source_class' => $sourceClassFilter,
            'focus' => $focusFilter,
            'access' => $accessFilter,
            'portal_type' => $portalTypeFilter,
            'registration' => $registrationFilter,
            'queue' => $queueFilter,
        ],
        'accessOptions' => $allManagedSources
            ->pluck('access_setup_label')
            ->filter()
            ->unique()
            ->sort()
            ->values(),
        'sourceClassOptions' => [
            'social_security_admin' => 'Social security administration',
            'government' => 'Ministry / government',
            'central_tender_portal' => 'Central tender portal',
            'donor_tender_portal' => 'Donor / development bank tender portal',
            'oil_gas_company' => 'Oil & gas company / procurement',
            'local_media' => 'Local media / business news',
            'news_aggregator' => 'News aggregator',
        ],
        'procurementPortalOptions' => [
            'not_applicable' => 'Not applicable',
            'government' => 'Government / official public procurement',
            'private_general' => 'Private-sector / general marketplace',
            'education_sector' => 'Education / university sector portal',
            'donor_global' => 'Global donor / development bank',
            'utility_sector' => 'Utility / infrastructure sector portal',
            'oil_gas_sector' => 'Oil & gas company / sector portal',
        ],
        'registrationOptions' => [
            'none' => 'No registration needed',
            'unknown' => 'Unknown / not checked',
            'optional' => 'Registration optional',
            'required' => 'Registration required',
            'api_key_required' => 'API key required',
            'paid_or_subscription' => 'Paid/subscription access',
            'blocked' => 'Blocked / restricted access',
        ],
    ]);
})->name('sls.intelligence.sources');

Route::post('/sls/intelligence/sources', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'url' => ['nullable', 'url', 'max:1000'],
        'domain' => ['nullable', 'string', 'max:255'],
        'country_iso' => ['nullable', 'string', 'max:8'],
        'region' => ['nullable', 'string', 'max:255'],
        'source_class' => ['required', 'string', 'max:80'],
        'procurement_portal_type' => ['nullable', 'string', 'max:80'],
        'focus' => ['required', 'in:both,news,tenders,social_security,hrms_tenders,erms_tenders,ebpc_tenders,sector_tenders'],
        'access_method' => ['nullable', 'string', 'max:80'],
        'connector' => ['nullable', 'string', 'max:80'],
        'registration_status' => ['nullable', 'string', 'max:80'],
        'registration_notes' => ['nullable', 'string', 'max:2000'],
    ]);

    if (blank($data['domain'] ?? null) && filled($data['url'] ?? null)) {
        $data['domain'] = parse_url($data['url'], PHP_URL_HOST);
    }

    $data['country_iso'] = filled($data['country_iso'] ?? null) ? strtoupper((string) $data['country_iso']) : null;
    $data['procurement_portal_type'] = $data['procurement_portal_type'] ?? 'not_applicable';
    $data['registration_status'] = $data['registration_status'] ?? 'unknown';
    $data['is_enabled'] = true;

    IntelligenceSource::create($data);

    return back()->with('status', 'Intelligence source added.');
})->name('sls.intelligence.sources.store');

Route::post('/sls/intelligence/sources/auto-configure', function () {
    $updated = 0;

    IntelligenceSource::query()
        ->where(function ($query) {
            $query->whereNull('access_method')->orWhereNull('connector');
        })
        ->chunkById(100, function ($sources) use (&$updated) {
            foreach ($sources as $source) {
                $domain = Str::lower((string) $source->domain);
                $class = (string) $source->source_class;
                $changes = [];

                if (blank($source->access_method)) {
                    $changes['access_method'] = match (true) {
                        Str::contains($domain, 'worldbank.org') => 'official_api',
                        Str::contains($domain, 'ted.europa.eu') => 'official_api',
                        Str::contains($domain, 'usaid.gov') => 'official_api',
                        Str::contains($domain, 'ecepp.ebrd.com') || Str::contains($domain, 'ebrd.com') => 'official_html_table',
                        Str::contains($domain, 'eib.org') => 'official_json_endpoint',
                        Str::contains($domain, 'developmentaid.org') => 'aggregator_portal',
                        Str::contains($domain, 'assortis.com') => 'aggregator_portal_api_check',
                        in_array($class, ['donor_tender_portal', 'central_tender_portal'], true) => 'official_portal',
                        in_array($class, ['local_media', 'news_aggregator'], true) => 'website_search',
                        default => 'manual_review',
                    };
                }

                if (blank($source->connector)) {
                    $changes['connector'] = match (true) {
                        Str::contains($domain, 'worldbank.org') => 'world_bank',
                        Str::contains($domain, 'ted.europa.eu') => 'ted',
                        Str::contains($domain, 'usaid.gov') => 'usaid_business_forecast',
                        Str::contains($domain, 'ecepp.ebrd.com') || Str::contains($domain, 'ebrd.com') => 'ebrd_ecepp',
                        Str::contains($domain, 'eib.org') => 'eib_procurement',
                        Str::contains($domain, 'adb.org') => 'adb_procurement',
                        Str::contains($domain, 'afdb.org') => 'afdb_procurement',
                        Str::contains($domain, 'assortis.com') => 'assortis_portal',
                        Str::contains($domain, 'developmentaid.org') => 'portal_html',
                        in_array($class, ['donor_tender_portal', 'central_tender_portal'], true) => 'portal_html',
                        in_array($class, ['local_media', 'news_aggregator'], true) => 'site_search',
                        default => 'manual_review',
                    };
                }

                if ($changes !== []) {
                    $source->update($changes);
                    $updated++;
                }
            }
        });

    return back()->with('status', $updated . ' source access setup value(s) auto-configured. Sources marked manual_review still need a custom connector.');
})->name('sls.intelligence.sources.autoConfigure');

Route::post('/sls/intelligence/sources/{source}/toggle', function (IntelligenceSource $source) {
    $source->update(['is_enabled' => ! $source->is_enabled]);

    return back()->with('status', 'Intelligence source updated.');
})->name('sls.intelligence.sources.toggle');

Route::post('/sls/intelligence/sources/{source}/run-next', function (IntelligenceSource $source) {
    $source->update([
        'force_next_at' => now(),
        'is_enabled' => true,
    ]);

    if ($source->country_iso && Schema::hasTable('intelligence_monitor_priorities')) {
        $focus = in_array($source->focus, ['hrms_tenders', 'sector_tenders', 'social_security'], true)
            ? $source->focus
            : ($source->focus === 'tenders' ? 'hrms_tenders' : 'social_security');

        IntelligenceMonitorPriority::query()->updateOrCreate(
            [
                'country_iso' => strtoupper((string) $source->country_iso),
                'focus' => $focus,
                'status' => 'pending',
            ],
            [
                'country_name' => $source->country_iso,
                'requested_at' => now(),
            ],
        );
    }

    return back()->with('status', $source->name . ' was moved to the front of the next applicable monitor run.');
})->name('sls.intelligence.sources.runNext');

Route::post('/sls/intelligence/sources/{source}/check-now', function (IntelligenceSource $source, IntelligenceSourceCheckerService $checker) {
    $source->update(['is_enabled' => true]);
    $result = $checker->check($source->fresh());

    $message = ($result['ok'] ? 'Checked ' : 'Check failed for ')
        . $source->name
        . '. Useful link/feed count: '
        . $result['items_found']
        . '.';

    if (! $result['ok'] && $result['error']) {
        $message .= ' Issue: ' . $result['error'];
    }

    return back()->with('status', $message);
})->name('sls.intelligence.sources.checkNow');

Route::get('/sls/intelligence/sources/{source}/audit', function (IntelligenceSource $source) {
    $domain = Str::of((string) $source->domain)->lower()->replace('www.', '')->toString();
    $audits = Schema::hasTable('intelligence_source_audits')
        ? IntelligenceSourceAudit::query()
            ->where(function ($query) use ($source, $domain) {
                $query->where('intelligence_source_id', $source->id);

                if ($domain !== '') {
                    $query->orWhereRaw('LOWER(REPLACE(COALESCE(domain, ""), "www.", "")) = ?', [$domain])
                        ->orWhereRaw('LOWER(REPLACE(COALESCE(domain, ""), "www.", "")) LIKE ?', ['%.' . $domain]);
                }
            })
            ->latest('checked_at')
            ->limit(25)
            ->get()
        : collect();

    return view('sls.intelligence.source-audit', [
        'source' => $source,
        'audits' => $audits,
        'austinTz' => 'America/Chicago',
    ]);
})->name('sls.intelligence.sources.audit');

Route::get('/sls/intelligence/sources/{source}/setup-help', function (IntelligenceSource $source) {
    $domain = Str::lower((string) $source->domain);
    $accessLabel = match (true) {
        $source->connector === 'ted' => 'Official API: TED',
        $source->connector === 'usaid_business_forecast' => 'Official API: USAID',
        $source->connector === 'idb_procurement' => 'Official API: IDB',
        $source->connector === 'tendersgo_api' => 'API credentials needed: TendersGo',
        $source->connector === 'assortis_portal' => 'Aggregator portal/API check: Assortis',
        $source->connector === 'world_bank' || Str::contains($domain, 'search.worldbank.org') => 'Official API: World Bank',
        $source->connector === 'wordpress' => 'WordPress API',
        $source->connector === 'adb_procurement' => 'ADB indexed fallback; portal blocked',
        $source->connector === 'afdb_procurement' => 'AfDB indexed procurement search',
        $source->connector === 'kfw_gtai_search' => 'KfW/GTAI indexed tender search',
        $source->connector === 'giz_procurement' => 'GIZ procurement search',
        $source->connector === 'ebrd_ecepp' => 'Official HTML table: EBRD ECEPP',
        $source->connector === 'eib_procurement' => 'Official JSON endpoint: EIB',
        $source->connector === 'portal_html' && Str::contains($domain, 'developmentaid.org') => 'Aggregator portal search',
        $source->connector === 'portal_html' => 'Basic portal scan',
        $source->connector === 'site_search' => 'Basic website search',
        $source->connector === 'manual_review' || $source->access_method === 'manual_review' => 'Manual connector needed',
        blank($source->connector) && blank($source->access_method) => 'None configured',
        default => trim(collect([$source->access_method, $source->connector])->filter()->implode(' / ')),
    };

    $needs = match ($accessLabel) {
        'API credentials needed: TendersGo' => [
            'TendersGo has been added as a global tender aggregator source, but live ingestion needs the API credentials and endpoint details.',
            'Please provide the API endpoint, API key/authentication method, and one sample search request/response from TendersGo.',
            'Once those are available, I will map the approved tender keyword searches to the API and normalize results by country, title, date, source link, and relevance.',
        ],
        'Aggregator portal/API check: Assortis' => [
            'Assortis has been added as a global tender aggregator source. Initial public inspection found tender/database and daily alert pages, but no public API documentation was obvious.',
            'For now, the connector uses public portal/indexed-search fallbacks and records audit entries showing what was attempted.',
            'If your Assortis account provides API access, send the API endpoint, authentication method/key, and one sample search request/response so I can switch this from public-search leads to proper API ingestion.',
        ],
        'Manual connector needed' => [
            'A human needs to inspect this site and decide whether it has an API, RSS feed, searchable HTML page, downloadable CSV/Excel notices, or requires login.',
            'If it requires login/API registration, provide the registration URL, account/API key details, and any usage restrictions.',
            'If it has a public search page, provide or approve the search URL pattern and I can turn it into a connector.',
        ],
        'None configured' => [
            'This source has not yet been assigned an access method.',
            'I need to know whether it should be monitored as an official API, RSS feed, public portal search, basic website search, or manual-only source.',
        ],
        'Configured, not checked yet' => [
            'This source is configured, but no monitor run has reached it yet.',
            'Use Check next to move it to the front of the applicable cycle, then open Audit response after it runs.',
        ],
        'ADB indexed fallback; portal blocked' => [
            'ADB has public procurement pages, but the local monitor is currently receiving blocked/403 responses from the direct ADB tender pages.',
            'The connector therefore uses indexed searches over adb.org for the four tender categories, and the audit response shows the exact query and whether anything was returned.',
            'If ADB provides a usable API/feed or if an account-based procurement feed is available, provide the endpoint or access details and I will replace this fallback with a proper direct connector.',
        ],
        'KfW/GTAI indexed tender search' => [
            'KfW Development Bank says operational tender notices are published through the GTAI KfW tender portal.',
            'Direct GTAI access from this local monitor currently returns bot protection, so the connector uses indexed GTAI/KfW tender searches for the approved product-theme keywords.',
            'If GTAI provides a permitted API/feed or account-based export, provide the endpoint or access details and I will replace the indexed fallback with a direct connector.',
        ],
        'Official HTML table: EBRD ECEPP' => [
            'EBRD project procurement notices are pulled from the public ECEPP notice table, not from a generic EBRD landing page.',
            'The connector parses notice rows, titles, notice types, publication dates, closing dates, status, and metadata, then applies the approved SSAS/HRMS/ERMS/EBPC tender filters.',
            'Audit response shows the ECEPP table URL and how many table rows were parsed versus how many qualified for the selected focus.',
        ],
        'Official JSON endpoint: EIB' => [
            'EIB procurement notices are pulled from the EIB website JSON endpoint used by its procurement search page.',
            'The connector queries the endpoint using the approved tender keywords and normalizes title, URL, publication date, status/type metadata, and description.',
            'Audit response shows the exact JSON query URL and the raw response returned by EIB.',
        ],
        default => [
            'This source has an access method configured.',
            'Open Audit response to see the latest request URL, response excerpt, status, and any parsing result.',
        ],
    };

    return view('sls.intelligence.source-setup-help', [
        'source' => $source,
        'accessLabel' => $accessLabel,
        'needs' => $needs,
    ]);
})->name('sls.intelligence.sources.setupHelp');

Route::post('/sls/intelligence/sources/{source}/delete', function (IntelligenceSource $source) {
    $source->delete();

    return back()->with('status', 'Intelligence source removed.');
})->name('sls.intelligence.sources.delete');

$crawlerSettingDefinitions = fn (): array => [
    'scheduled_region_scope' => [
        'label' => 'Scheduled region scope',
        'description' => 'Region key or comma-separated region names used by scheduled crawlers.',
        'value_type' => 'string',
        'default' => 'africa_asia_caribbean_latin_america_north_america_europe',
    ],
    'verify_ssl' => [
        'label' => 'Verify source SSL certificates',
        'description' => 'Use true in production unless a source/API requires relaxed certificate checks.',
        'value_type' => 'boolean',
        'default' => config('country_intelligence.verify_ssl', false) ? 'true' : 'false',
    ],
    'gdelt_endpoint' => [
        'label' => 'GDELT news API endpoint',
        'description' => 'Endpoint used for indexed news and source-specific news/tender searches.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.gdelt_endpoint', 'https://api.gdeltproject.org/api/v2/doc/doc'),
    ],
    'scheduled_max_results' => [
        'label' => 'Scheduled max results per country',
        'description' => 'Maximum candidate items kept for each scheduled country/focus run.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.scheduled_max_results', 3),
    ],
    'default_max_results' => [
        'label' => 'Manual run max results',
        'description' => 'Default item limit when a monitor is run manually without another max value.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.default_max_results', 10),
    ],
    'max_queries_per_country' => [
        'label' => 'News queries per country',
        'description' => 'Maximum general news search queries sent for each country/focus run.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.max_queries_per_country', 16),
    ],
    'development_partner_max_queries_per_country' => [
        'label' => 'Donor portal queries per focus',
        'description' => 'Maximum donor/development partner domain searches per focus run.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.development_partner_max_queries_per_country', 8),
    ],
    'gdelt_timespan' => [
        'label' => 'News search recency window',
        'description' => 'GDELT timespan, for example 7d, 30d, 3m. Keeps general news searches fresh.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.gdelt_timespan', '30d'),
    ],
    'news_recent_publication_days' => [
        'label' => 'News publication freshness days',
        'description' => 'Maximum age for non-tender news items by publication date. Use 0 to disable. Tender/procurement-looking items are not limited by this setting.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.news_recent_publication_days', 90),
    ],
    'news_aggregator_queries_per_country' => [
        'label' => 'News aggregator queries per country',
        'description' => 'Maximum Google/Bing News RSS query patterns tried for each country during social-security news crawls.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.news_aggregator_queries_per_country', 6),
    ],
    'news_aggregator_results_per_query' => [
        'label' => 'News aggregator results per query',
        'description' => 'Maximum RSS items read from each news aggregator query before country/relevance/freshness filtering.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.news_aggregator_results_per_query', 10),
    ],
    'world_bank_procurement_endpoint' => [
        'label' => 'World Bank procurement endpoint',
        'description' => 'Official World Bank procurement notice API endpoint.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.world_bank_procurement_endpoint', 'https://search.worldbank.org/api/v2/procnotices'),
    ],
    'world_bank_recent_notice_days' => [
        'label' => 'World Bank notice freshness days',
        'description' => 'How far back World Bank notices can be considered current when no open deadline is captured.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.world_bank_recent_notice_days', 45),
    ],
    'ted_search_endpoint' => [
        'label' => 'TED search endpoint',
        'description' => 'Official EU TED notices search endpoint.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.ted_search_endpoint', 'https://api.ted.europa.eu/v3/notices/search'),
    ],
    'usaid_business_forecast_endpoint' => [
        'label' => 'USAID business forecast endpoint',
        'description' => 'USAID public business forecast dataset endpoint.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.usaid_business_forecast_endpoint', 'https://data.usaid.gov/resource/qdtq-s66e.json'),
    ],
    'sam_gov_opportunities_endpoint' => [
        'label' => 'SAM.gov opportunities endpoint',
        'description' => 'SAM.gov contract opportunities API endpoint.',
        'value_type' => 'string',
        'default' => (string) config('country_intelligence.sam_gov_opportunities_endpoint', 'https://api.sam.gov/opportunities/v2/search'),
    ],
    'sam_gov_api_key' => [
        'label' => 'SAM.gov API key',
        'description' => 'API key for SAM.gov opportunity searches. Leave blank if SAM.gov should be skipped.',
        'value_type' => 'secret',
        'default' => (string) env('SAM_GOV_API_KEY', env('SAM_API_KEY', '')),
    ],
    'daily_batch_size' => [
        'label' => 'Countries per scheduled news slot',
        'description' => 'How many countries the rotating news monitor checks in each scheduled slot.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.daily_batch_size', 1),
    ],
    'social_security_daily_slots' => [
        'label' => 'Social security/news country slots',
        'description' => 'Comma-separated HH:MM times. These rotate through countries by batch size.',
        'value_type' => 'csv_times',
        'default' => implode(',', config('country_intelligence.daily_slots', [])),
    ],
    'hrms_tender_slots' => [
        'label' => 'HRMS/ERMS/EBPC tender slots',
        'description' => 'Comma-separated HH:MM base times. ERMS and EBPC are staggered from these slots.',
        'value_type' => 'csv_times',
        'default' => implode(',', config('country_intelligence.hrms_tender_slots', [])),
    ],
    'sector_tender_slots' => [
        'label' => 'Sector tender slots',
        'description' => 'Comma-separated HH:MM times for sector tender searches.',
        'value_type' => 'csv_times',
        'default' => implode(',', config('country_intelligence.sector_tender_slots', [])),
    ],
    'social_protection_profile_weekly_day' => [
        'label' => 'ILO profile weekly day',
        'description' => 'Laravel weekly day number for ILO country-profile checks. 0 Sunday, 1 Monday, 2 Tuesday, etc.',
        'value_type' => 'integer',
        'default' => '1',
    ],
    'social_protection_profile_weekly_time' => [
        'label' => 'ILO profile weekly time',
        'description' => 'Weekly HH:MM server-time run for ILO country-profile checks.',
        'value_type' => 'time',
        'default' => (string) config('country_intelligence.social_protection_profile_weekly_time', '04:10'),
    ],
    'ilo_social_protection_project_weekly_day' => [
        'label' => 'ILO project discovery weekly day',
        'description' => 'Laravel weekly day number for ILO project discovery. 0 Sunday, 1 Monday, 2 Tuesday, etc.',
        'value_type' => 'integer',
        'default' => '2',
    ],
    'ilo_social_protection_project_weekly_time' => [
        'label' => 'ILO project discovery weekly time',
        'description' => 'Weekly HH:MM server-time run for ILO project discovery.',
        'value_type' => 'time',
        'default' => (string) config('country_intelligence.ilo_social_protection_project_weekly_time', '04:35'),
    ],
    'ilo_social_protection_project_weekly_country_limit' => [
        'label' => 'ILO project country limit',
        'description' => 'Maximum countries checked per ILO project discovery run.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.ilo_social_protection_project_weekly_country_limit', 25),
    ],
    'ilo_social_protection_project_queries_per_country' => [
        'label' => 'ILO project queries per country',
        'description' => 'Maximum SerpAPI queries per country for ILO project discovery.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.ilo_social_protection_project_queries_per_country', 3),
    ],
    'ilo_social_protection_project_results_per_query' => [
        'label' => 'ILO project results per query',
        'description' => 'Maximum indexed-search results kept per ILO project discovery query.',
        'value_type' => 'integer',
        'default' => (string) config('country_intelligence.ilo_social_protection_project_results_per_query', 5),
    ],
    'global_hrms_tender_sweep_time' => [
        'label' => 'Global HRMS tender sweep time',
        'description' => 'Daily HH:MM server-time run for global HRMS tender sweep.',
        'value_type' => 'time',
        'default' => env('SLS_GLOBAL_HRMS_TENDER_SWEEP_TIME', '02:35'),
    ],
    'global_erms_tender_sweep_time' => [
        'label' => 'Global ERMS tender sweep time',
        'description' => 'Daily HH:MM server-time run for global ERMS tender sweep.',
        'value_type' => 'time',
        'default' => env('SLS_GLOBAL_ERMS_TENDER_SWEEP_TIME', '03:05'),
    ],
    'global_ebpc_tender_sweep_time' => [
        'label' => 'Global EBPC tender sweep time',
        'description' => 'Daily HH:MM server-time run for global EBPC tender sweep.',
        'value_type' => 'time',
        'default' => env('SLS_GLOBAL_EBPC_TENDER_SWEEP_TIME', '03:15'),
    ],
    'global_social_tender_sweep_time' => [
        'label' => 'Global social security tender sweep time',
        'description' => 'Daily HH:MM server-time run for global social-security tender sweep.',
        'value_type' => 'time',
        'default' => env('SLS_GLOBAL_SOCIAL_TENDER_SWEEP_TIME', '02:55'),
    ],
    'global_social_news_sweep_time' => [
        'label' => 'Global social security news sweep time',
        'description' => 'Daily HH:MM server-time run for global social-security news sweep.',
        'value_type' => 'time',
        'default' => env('SLS_GLOBAL_SOCIAL_NEWS_SWEEP_TIME', '03:20'),
    ],
    'global_tender_sweep_max_results' => [
        'label' => 'Global tender sweep max results',
        'description' => 'Maximum results used by each global tender sweep.',
        'value_type' => 'integer',
        'default' => '80',
    ],
    'global_social_news_max_results' => [
        'label' => 'Global news sweep max results',
        'description' => 'Maximum results used by the global social-security news sweep.',
        'value_type' => 'integer',
        'default' => '120',
    ],
    'daily_backup_time' => [
        'label' => 'Daily backup time',
        'description' => 'Daily HH:MM server-time run for local SLS backup.',
        'value_type' => 'time',
        'default' => env('SLS_DAILY_BACKUP_TIME', '03:00'),
    ],
    'daily_backup_timezone' => [
        'label' => 'Daily backup timezone',
        'description' => 'Timezone used for the daily backup schedule.',
        'value_type' => 'string',
        'default' => env('SLS_DAILY_BACKUP_TIMEZONE', 'America/Chicago'),
    ],
    'tender_document_process_limit' => [
        'label' => 'Tender document process limit',
        'description' => 'Maximum tender documents processed per scheduled processing run.',
        'value_type' => 'integer',
        'default' => (string) env('SLS_TENDER_DOCUMENT_PROCESS_LIMIT', 20),
    ],
    'title_translation_backfill_limit' => [
        'label' => 'Title translation backfill limit',
        'description' => 'Maximum update titles translated per scheduled backfill run.',
        'value_type' => 'integer',
        'default' => (string) env('SLS_TITLE_TRANSLATION_BACKFILL_LIMIT', 100),
    ],
    'crawler_contact_clean_limit' => [
        'label' => 'Crawler contact clean limit',
        'description' => 'Maximum crawler contacts cleaned per scheduled cleanup run.',
        'value_type' => 'integer',
        'default' => (string) env('SLS_CRAWLER_CONTACT_CLEAN_LIMIT', 5000),
    ],
    'crawler_contact_resolve_limit' => [
        'label' => 'Crawler contact resolve limit',
        'description' => 'Maximum crawler contact names resolved per scheduled research run.',
        'value_type' => 'integer',
        'default' => (string) env('SLS_CRAWLER_CONTACT_RESOLVE_LIMIT', 200),
    ],
    'worker_stale_minutes' => [
        'label' => 'Worker stale threshold minutes',
        'description' => 'Minutes without a completed monitor run before worker health check warns.',
        'value_type' => 'integer',
        'default' => (string) env('SLS_WORKER_STALE_MINUTES', 45),
    ],
    'serpapi_key' => [
        'label' => 'SerpAPI key',
        'description' => 'API key used by source discovery and indexed-search discovery crawlers.',
        'value_type' => 'secret',
        'default' => (string) env('SERPAPI_KEY', ''),
    ],
    'serpapi_pilot_limit' => [
        'label' => 'SerpAPI pilot country limit',
        'description' => 'Default country limit when SerpAPI discovery is run without an explicit limit.',
        'value_type' => 'integer',
        'default' => (string) env('SERPAPI_PILOT_LIMIT', 0),
    ],
    'serpapi_verify_ssl' => [
        'label' => 'Verify SerpAPI SSL certificates',
        'description' => 'Use true in production unless local certificate handling requires disabling verification.',
        'value_type' => 'boolean',
        'default' => filter_var(env('SERPAPI_VERIFY_SSL', true), FILTER_VALIDATE_BOOL) ? 'true' : 'false',
    ],
    'serpapi_ca_bundle' => [
        'label' => 'SerpAPI CA bundle path',
        'description' => 'Optional CA bundle path used by SerpAPI-backed discovery crawlers.',
        'value_type' => 'string',
        'default' => (string) env('SERPAPI_CA_BUNDLE', ''),
    ],
];

$seedCrawlerSettings = function () use ($crawlerSettingDefinitions): void {
    if (! Schema::hasTable('crawler_settings')) {
        return;
    }

    foreach ($crawlerSettingDefinitions() as $key => $definition) {
        CrawlerSetting::query()->firstOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $definition['default'],
                'value_type' => $definition['value_type'],
                'label' => $definition['label'],
                'description' => $definition['description'],
            ],
        );
    }
};

Route::get('/sls/intelligence/crawler-settings', function () use ($seedCrawlerSettings, $crawlerSettingDefinitions) {
    $seedCrawlerSettings();

    $settings = CrawlerSetting::query()
        ->orderByRaw("FIELD(setting_key, '" . implode("','", array_keys($crawlerSettingDefinitions())) . "')")
        ->get()
        ->keyBy('setting_key');

    return view('sls.intelligence.crawler-settings', [
        'settings' => $settings,
        'definitions' => $crawlerSettingDefinitions(),
    ]);
})->name('sls.intelligence.crawlerSettings');

Route::post('/sls/intelligence/crawler-settings', function (Request $request) use ($seedCrawlerSettings, $crawlerSettingDefinitions) {
    $seedCrawlerSettings();
    $definitions = $crawlerSettingDefinitions();
    $data = $request->validate([
        'settings' => ['required', 'array'],
        'settings.*' => ['nullable', 'string', 'max:5000'],
    ]);

    foreach (($data['settings'] ?? []) as $key => $value) {
        if (! array_key_exists($key, $definitions)) {
            continue;
        }

        $definition = $definitions[$key];
        $value = trim((string) $value);

        if ($definition['value_type'] === 'integer') {
            $value = (string) max(0, (int) $value);
        }

        if ($definition['value_type'] === 'boolean') {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized === null) {
                return back()->withErrors([$key => $definition['label'] . ' must be true or false.'])->withInput();
            }

            $value = $normalized ? 'true' : 'false';
        }

        if ($definition['value_type'] === 'time' && preg_match('/^\d{2}:\d{2}$/', $value) !== 1) {
            return back()->withErrors([$key => $definition['label'] . ' must use HH:MM format.'])->withInput();
        }

        if ($definition['value_type'] === 'csv_times') {
            $times = collect(explode(',', $value))
                ->map(fn (string $time) => trim($time))
                ->filter();

            if ($times->contains(fn (string $time) => preg_match('/^\d{2}:\d{2}$/', $time) !== 1)) {
                return back()->withErrors([$key => $definition['label'] . ' must be a comma-separated list of HH:MM times.'])->withInput();
            }

            $value = $times->implode(',');
        }

        CrawlerSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'value_type' => $definition['value_type'],
                'label' => $definition['label'],
                'description' => $definition['description'],
            ],
        );
    }

    return back()->with('status', 'Crawler settings saved. The scheduler will pick up these settings on its next minute tick.');
})->name('sls.intelligence.crawlerSettings.update');

Route::get('/sls/intelligence/keywords', function () use ($seedIntelligenceRegistries) {
    $seedIntelligenceRegistries();
    $focusFilter = (string) request('focus', 'source_discovery');
    $languageFilter = (string) request('language', 'all');
    $categoryFilter = (string) request('category', 'all');

    $keywords = IntelligenceKeyword::query()
        ->when($focusFilter !== 'all', fn ($query) => $query->where('focus', $focusFilter))
        ->when($languageFilter !== 'all', fn ($query) => $query->where('language_code', $languageFilter))
        ->when($categoryFilter !== 'all', fn ($query) => $query->where('category', $categoryFilter))
        ->orderBy('focus')
        ->orderBy('category')
        ->orderBy('language_code')
        ->orderBy('term')
        ->get();

    return view('sls.intelligence.keywords', [
        'keywords' => $keywords,
        'focuses' => collect(config('country_intelligence.focuses', []))
            ->merge([
                'source_discovery' => [
                    'label' => 'Source Discovery',
                    'description' => 'SerpAPI seed terms for finding official social security, labour, civil service, and procurement sources.',
                ],
            ])
            ->all(),
        'focusFilter' => $focusFilter,
        'languageFilter' => $languageFilter,
        'categoryFilter' => $categoryFilter,
        'languages' => IntelligenceKeyword::query()->select('language_code')->distinct()->orderBy('language_code')->pluck('language_code'),
        'categories' => IntelligenceKeyword::query()->select('category')->distinct()->orderBy('category')->pluck('category')->filter()->values(),
    ]);
})->name('sls.intelligence.keywords');

Route::post('/sls/intelligence/keywords', function (Request $request) {
    $data = $request->validate([
        'focus' => ['required', 'string', 'max:80'],
        'term' => ['required', 'string', 'max:255'],
        'language_code' => ['required', 'string', 'max:12'],
        'category' => ['nullable', 'string', 'max:80'],
    ]);

    IntelligenceKeyword::updateOrCreate(
        ['focus' => $data['focus'], 'term' => $data['term'], 'language_code' => $data['language_code']],
        ['category' => $data['category'] ?? 'custom', 'is_enabled' => true],
    );

    return back()->with('status', 'Keyword added.');
})->name('sls.intelligence.keywords.store');

Route::post('/sls/intelligence/keywords/{keyword}/toggle', function (IntelligenceKeyword $keyword) {
    $keyword->update(['is_enabled' => ! $keyword->is_enabled]);

    return back()->with('status', 'Keyword updated.');
})->name('sls.intelligence.keywords.toggle');

Route::post('/sls/intelligence/keywords/{keyword}/delete', function (IntelligenceKeyword $keyword) {
    $keyword->delete();

    return back()->with('status', 'Keyword removed.');
})->name('sls.intelligence.keywords.delete');

Route::get('/sls/demo-media', function () use ($orderedProducts) {
    $tesseractPath = (string) env('TESSERACT_PATH', '');

    return view('sls.demo-media.index', [
        'products' => $orderedProducts(),
        'ffmpegReady' => is_file(base_path('tools/ffmpeg/bin/ffmpeg.exe')),
        'whisperReady' => is_file(base_path('tools/whisper/transcribe.py')) && is_dir(base_path('tools/python-whisper/faster_whisper')),
        'tesseractReady' => ($tesseractPath !== '' && is_file($tesseractPath)) || is_file(base_path('tools/tesseract/tesseract.exe')) || (bool) (new ExecutableFinder())->find('tesseract'),
        'demoSessions' => DemoSession::query()
            ->with('product')
            ->withCount(['frames', 'transcriptSegments', 'featureMoments'])
            ->latest()
            ->limit(25)
            ->get(),
        'sessionCount' => DemoSession::count(),
        'frameCount' => DemoFrame::count(),
        'featureMomentCount' => DemoFeatureMoment::count(),
    ]);
})->name('sls.demo-media.index');

Route::post('/sls/demo-media', function (Request $request) {
    $data = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
        'language_code' => ['required', 'in:en,fr,es,pt,nl,ar'],
        'video_files' => ['required', 'array', 'min:1', 'max:5'],
        'video_files.*' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:1048576'],
        'transcript_file' => ['nullable', 'file', 'mimes:txt,vtt,srt,csv,md', 'max:20480'],
        'process_after_upload' => ['nullable', 'boolean'],
    ]);

    $uploadedVideos = collect($request->file('video_files', []));
    $sessions = collect();

    foreach ($uploadedVideos as $index => $videoFile) {
        $videoPath = $videoFile->store('demo-media/videos');
        $transcriptPath = $index === 0 && $request->hasFile('transcript_file')
            ? $request->file('transcript_file')->store('demo-media/transcripts')
            : null;
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '' || $uploadedVideos->count() > 1) {
            $title = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $title = Str::of($title)
                ->replace(['_', '-'], ' ')
                ->squish()
                ->title()
                ->toString();
        }

        $sessions->push(DemoSession::create([
            'product_id' => $data['product_id'] ?? null,
            'title' => $title,
            'source_type' => 'product_demo_video',
            'language_code' => $data['language_code'],
            'video_storage_path' => $videoPath,
            'transcript_storage_path' => $transcriptPath,
            'processing_status' => $request->boolean('process_after_upload', true) ? 'queued' : 'uploaded',
            'processing_notes' => $request->boolean('process_after_upload', true)
                ? 'Queued for sequential demo media processing. The batch worker will transcribe, extract screenshots, run OCR, and build moments.'
                : 'Uploaded. Frame extraction, OCR, and transcript alignment are pending.',
        ]));
    }

    if ($request->boolean('process_after_upload', true) && $sessions->isNotEmpty()) {
        $ids = $sessions->pluck('id')->implode(',');
        $command = 'start /B "" "' . PHP_BINARY . '" "' . base_path('artisan') . '" sls:process-demo-media-batch "' . $ids . '"';
        pclose(popen($command, 'r'));
    }

    $status = $sessions->count() . ' demo video(s) registered.';

    if ($request->boolean('process_after_upload', true)) {
        $status .= ' Sequential processing has started in the background.';
    }

    return redirect()
        ->route('sls.demo-media.index')
        ->with('status', $status);
})->name('sls.demo-media.store');

Route::get('/sls/demo-media/{demoSession}', function (DemoSession $demoSession) {
    $frames = $demoSession->frames()
        ->where('review_status', '!=', 'rejected')
        ->orderBy('timestamp_ms')
        ->get();

    return view('sls.demo-media.show', [
        'session' => $demoSession->load('product'),
        'allFrames' => $frames,
        'framesById' => $frames->keyBy('id'),
        'firstFrames' => $frames->take(5),
        'lastFrames' => $frames->slice(max(0, $frames->count() - 5))->values(),
        'segments' => $demoSession->transcriptSegments()
            ->orderBy('start_ms')
            ->get(),
        'moments' => $demoSession->featureMoments()
            ->orderBy('start_ms')
            ->get(),
    ]);
})->name('sls.demo-media.show');

Route::post('/sls/demo-media/{demoSession}/process', function (DemoSession $demoSession, DemoMediaIngestionService $ingestion) {
    try {
        $result = $ingestion->process($demoSession);

        return back()->with('status', 'Processed demo media: ' . $result['frames'] . ' frame(s), ' . $result['transcript_segments'] . ' transcript segment(s), ' . ($result['blank_frames_rejected'] ?? 0) . ' blank/loading screenshot(s) excluded, ' . $result['feature_moments'] . ' searchable moment(s).');
    } catch (\Throwable $exception) {
        return back()->with('status', 'Demo media processing could not run yet: ' . $exception->getMessage());
    }
})->name('sls.demo-media.process');

Route::post('/sls/demo-media/{demoSession}/approve-moments', function (DemoSession $demoSession, DemoMediaIngestionService $ingestion) {
    $count = $ingestion->approveMoments($demoSession);

    return back()->with('status', $count . ' demo moment(s) approved for chat screenshot retrieval.');
})->name('sls.demo-media.approve-moments');

Route::get('/sls/demo-media/frames/{demoFrame}', function (DemoFrame $demoFrame) {
    abort_unless($demoFrame->image_storage_path, 404);

    $path = Storage::path($demoFrame->image_storage_path);

    abort_unless(is_file($path), 404);

    return response()->file($path);
})->name('sls.demo-media.frames.show');

$documentIntakeOptions = function (): array {
    return [
        'categories' => [
            'contact_list' => 'Contact list',
            'prospect_research' => 'Prospect research',
            'competitor_research' => 'Competitor research',
            'account_background' => 'Account background',
            'country_background' => 'Country background',
            'organization_background' => 'Organization background',
            'investor_partner_research' => 'Investor / partner research',
            'tender_or_opportunity' => 'Tender or opportunity',
            'market_study' => 'Market study',
            'general_reference' => 'General reference',
        ],
        'actions' => [
            'file_only' => 'File only',
            'searchable_library' => 'File and make searchable',
            'extract_contacts' => 'Extract contacts only',
            'searchable_and_extract' => 'Make searchable and extract contacts',
        ],
        'relationships' => [
            'prospect' => 'Prospect',
            'account' => 'Account',
            'competitor' => 'Competitor',
            'partner' => 'Potential partner',
            'investor' => 'Potential investor',
            'acquisition_suitor' => 'Potential acquisition suitor',
            'supplier' => 'Supplier',
            'other' => 'Other',
        ],
        'sourceTypes' => [
            'manual' => 'Manual / general document',
            'rfp' => 'Tender / RFP document',
            'country_specific_information' => 'Country background',
            'organization_specific_information' => 'Organization background',
            'brochure' => 'Brochure or profile',
            'news' => 'News or article',
            'policy' => 'Policy or regulation',
            'law' => 'Law',
            'email_attachment' => 'Email attachment',
        ],
        'languages' => [
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
            'de' => 'German',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'ar' => 'Arabic',
            'other' => 'Other / mixed',
        ],
    ];
};

$sourceTypeForIntakeCategory = function (string $category): string {
    return match ($category) {
        'country_background' => 'country_specific_information',
        'account_background', 'competitor_research', 'organization_background', 'investor_partner_research', 'prospect_research' => 'organization_specific_information',
        'tender_or_opportunity' => 'rfp',
        'market_study' => 'manual',
        default => 'manual',
    };
};

Route::get('/sls/knowledge', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $documents = SourceDocument::with('products')
        ->withCount([
            'chunks',
            'chunks as approved_chunks_count' => fn ($query) => $query->where('approval_status', 'approved'),
            'chunks as pending_chunks_count' => fn ($query) => $query->where('approval_status', 'unreviewed'),
            'chunks as rejected_chunks_count' => fn ($query) => $query->where('approval_status', 'rejected'),
        ])
        ->when($query !== '', function ($builder) use ($query) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

            $builder->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('original_filename', 'like', $like)
                    ->orWhere('source_type', 'like', $like)
                    ->orWhere('intake_category', 'like', $like)
                    ->orWhere('related_organization_name', 'like', $like)
                    ->orWhere('intake_notes', 'like', $like)
                    ->orWhere(function ($searchableDocument) use ($like) {
                        $searchableDocument
                            ->where(function ($actionQuery) {
                                $actionQuery->whereNull('intake_action')
                                    ->orWhereIn('intake_action', ['searchable_library', 'searchable_and_extract']);
                            })
                            ->whereHas('chunks', fn ($chunkQuery) => $chunkQuery->where('chunk_text', 'like', $like));
                    });
            });
        })
        ->latest()
        ->limit(100)
        ->get();

    return view('sls.knowledge.index', [
        'documents' => $documents,
        'query' => $query,
        'documentCount' => SourceDocument::count(),
        'chunkCount' => KnowledgeChunk::count(),
        'approvedChunkCount' => KnowledgeChunk::where('approval_status', 'approved')->count(),
        'pendingChunkCount' => KnowledgeChunk::where('approval_status', 'unreviewed')->count(),
    ]);
})->name('sls.knowledge.index');

$nextUploadTitle = function (string $title): string {
    $replacementCount = 0;
    $nextTitle = preg_replace_callback('/\bPart\s*([:#-]?\s*)(\d+)\b/i', function (array $matches) {
        $separator = $matches[1] !== '' ? $matches[1] : ' ';

        return 'Part' . $separator . ((int) $matches[2] + 1);
    }, $title, 1, $replacementCount);

    if ($replacementCount > 0) {
        return $nextTitle;
    }

    if (preg_match('/\b(manual|guide|handbook|documentation)\b/i', $title)) {
        return rtrim($title) . ' - Part 2';
    }

    return $title;
};

$uploadPartTitle = function (?string $title, int $offset, ?string $filename = null, int $fileCount = 1): string {
    $filenameTitle = $filename
        ? pathinfo($filename, PATHINFO_FILENAME)
        : '';
    $baseTitle = trim($filenameTitle) !== '' ? trim($filenameTitle) : trim((string) $title);

    if (! Str::startsWith(Str::lower($baseTitle), 'interact ssas - manual -')) {
        $baseTitle = 'Interact SSAS - Manual - ' . preg_replace('/^interact\s+ssas\s*-\s*manual\s*-\s*/i', '', $baseTitle);
    }

    $baseTitle = preg_replace('/\s+/', ' ', trim($baseTitle));

    if (preg_match('/\bPart\s*([:#-]?\s*)(\d+)\b/i', $baseTitle)) {
        return $baseTitle;
    }

    if ($fileCount > 1 || preg_match('/\bPart\s*([:#-]?\s*)\d+\b/i', $title)) {
        return rtrim($baseTitle) . ' - Part ' . ($offset + 1);
    }

    return $baseTitle;
};

Route::get('/sls/knowledge/upload', function (Request $request) use ($orderedProducts, $nextUploadTitle) {
    $previousUpload = $request->boolean('blank')
        ? null
        : SourceDocument::query()
            ->with('products:id')
            ->latest()
            ->first();

    return view('sls.knowledge.upload', [
        'products' => $orderedProducts(),
        'uploadDefaults' => $previousUpload ? [
            'title' => $nextUploadTitle($previousUpload->title),
            'source_type' => $previousUpload->source_type,
            'language_code' => $previousUpload->language_code,
            'source_date' => $previousUpload->source_date?->toDateString(),
            'products' => $previousUpload->products->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'based_on_title' => $previousUpload->title,
        ] : null,
    ]);
})->name('sls.knowledge.upload');

Route::post('/sls/knowledge/upload', function (Request $request, KnowledgeIngestionService $ingestion) use ($uploadPartTitle) {
    $data = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'source_type' => ['required', 'in:manual,email,email_attachment,rfp,brochure,implementation_note,security_document,webinar_transcript,product_demo_transcript,country_specific_information,organization_specific_information'],
        'language_code' => ['required', 'in:en,fr,es,pt'],
        'source_date' => ['nullable', 'date'],
        'products' => ['required', 'array', 'min:1'],
        'products.*' => ['integer', 'exists:products,id'],
        'source_files' => ['required', 'array', 'min:1', 'max:10'],
        'source_files.*' => ['required', 'file', 'mimes:pdf,txt,csv,md,html,htm,eml,docx,xlsx', 'max:51200'],
    ]);

    $uploadedFiles = collect($request->file('source_files', []))->values();

    $sources = $uploadedFiles
        ->values()
        ->map(function ($file, int $index) use ($ingestion, $data, $uploadPartTitle, $uploadedFiles) {
            $fileData = $data;
            $fileData['title'] = $uploadPartTitle($data['title'], $index, $file->getClientOriginalName(), $uploadedFiles->count());

            return $ingestion->ingest($file, $fileData);
        });

    $source = $sources->last();

    if ($sources->count() > 1) {
        return redirect()
            ->route('sls.knowledge.batch', ['sources' => $sources->pluck('id')->implode(',')])
            ->with('status', $sources->count() . ' source file(s) uploaded and chunked for review.');
    }

    return redirect()
        ->route('sls.knowledge.show', $source)
        ->with('status', $sources->count() . ' source file(s) uploaded and chunked for review.');
})->name('sls.knowledge.store');

Route::get('/sls/documents/intake', function () use ($orderedProducts, $documentIntakeOptions) {
    return view('sls.documents.intake', [
        'products' => $orderedProducts(),
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code']),
        'options' => $documentIntakeOptions(),
    ]);
})->name('sls.documents.intake');

Route::post('/sls/documents/intake', function (
    Request $request,
    KnowledgeIngestionService $ingestion,
    DocumentContactExtractionService $contactExtraction,
) use ($documentIntakeOptions, $sourceTypeForIntakeCategory) {
    $options = $documentIntakeOptions();
    $extractingActions = ['extract_contacts', 'searchable_and_extract'];

    $data = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'source_type' => ['nullable', 'in:' . implode(',', array_keys($options['sourceTypes']))],
        'intake_category' => ['required', 'in:' . implode(',', array_keys($options['categories']))],
        'intake_action' => ['required', 'in:' . implode(',', array_keys($options['actions']))],
        'contact_relationship_type' => ['nullable', 'in:' . implode(',', array_keys($options['relationships']))],
        'related_country_id' => ['nullable', 'integer', 'exists:countries,id'],
        'related_organization_name' => ['nullable', 'string', 'max:255'],
        'language_code' => ['required', 'in:' . implode(',', array_keys($options['languages']))],
        'source_date' => ['nullable', 'date'],
        'source_url' => ['nullable', 'url', 'max:2048'],
        'intake_notes' => ['nullable', 'string', 'max:5000'],
        'products' => ['nullable', 'array'],
        'products.*' => ['integer', 'exists:products,id'],
        'source_files' => ['required', 'array', 'min:1', 'max:10'],
        'source_files.*' => ['required', 'file', 'mimes:pdf,txt,csv,md,html,htm,eml,docx,xlsx', 'max:51200'],
    ]);

    if (in_array($data['intake_action'], $extractingActions, true) && empty($data['contact_relationship_type'])) {
        return back()
            ->withErrors(['contact_relationship_type' => 'Choose how contacts from this document should be classified.'])
            ->withInput();
    }

    $uploadedFiles = collect($request->file('source_files', []))->values();
    $sources = collect();
    $contactsCreated = 0;
    $contactsUpdated = 0;

    foreach ($uploadedFiles as $index => $file) {
        $filenameTitle = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $prefix = trim((string) ($data['title'] ?? ''));
        $title = $prefix !== ''
            ? ($uploadedFiles->count() > 1 ? $prefix . ' - ' . ($index + 1) . ' - ' . $filenameTitle : $prefix)
            : $filenameTitle;

        $fileData = $data;
        $fileData['title'] = Str::limit($title, 250, '');
        $fileData['source_type'] = $data['source_type'] ?: $sourceTypeForIntakeCategory($data['intake_category']);
        $fileData['products'] = $data['products'] ?? [];

        $source = $ingestion->ingest($file, $fileData);
        $sources->push($source);

        if (in_array($data['intake_action'], $extractingActions, true)) {
            $result = $contactExtraction->extract($source);
            $contactsCreated += $result['created'];
            $contactsUpdated += $result['updated'];
        }
    }

    $status = $sources->count() . ' document(s) uploaded';
    if (in_array($data['intake_action'], $extractingActions, true)) {
        $status .= '; ' . $contactsCreated . ' new contact(s) captured and ' . $contactsUpdated . ' updated';
    }
    $status .= '.';

    if ($sources->count() > 1) {
        return redirect()
            ->route('sls.knowledge.batch', ['sources' => $sources->pluck('id')->implode(',')])
            ->with('status', $status);
    }

    return redirect()
        ->route('sls.knowledge.show', $sources->first())
        ->with('status', $status);
})->name('sls.documents.intake.store');

Route::get('/sls/social-security-systems/import', function () {
    return view('sls.documents.social-security-systems', [
        'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_code']),
        'candidates' => SocialSecurityAdminCandidate::query()
            ->with('sourceDocument', 'country', 'marketOrganization', 'contexts')
            ->withCount('contexts')
            ->latest()
            ->limit(300)
            ->get(),
    ]);
})->name('sls.socialSecuritySystems.import');

$buildSocialSecurityAdminCsv = function (): string {
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Candidate ID',
        'Status',
        'Country',
        'ISO',
        'Organization',
        'Context ID',
        'Raw Role',
        'Raw Programmes',
        'Normalized Roles',
        'Programme L1',
        'Programme L2',
        'Employer Types',
        'Special System Mentioned',
        'Special System Employer Types',
        'Needs Enrichment',
        'Classification Notes',
        'Confidence Score',
        'Source Document',
        'Source Document ID',
        'Evidence Excerpt',
    ]);

    SocialSecurityAdminCandidate::query()
        ->with(['country', 'sourceDocument', 'contexts'])
        ->orderBy('country_name')
        ->orderBy('organization_name')
        ->chunk(200, function ($candidates) use ($out) {
            foreach ($candidates as $candidate) {
                foreach ($candidate->contexts as $context) {
                    fputcsv($out, [
                        $candidate->id,
                        $candidate->status,
                        $candidate->country?->name ?? $candidate->country_name,
                        $candidate->country_iso,
                        $candidate->organization_name,
                        $context->id,
                        $context->role_in_programme,
                        $context->related_programmes,
                        collect($context->normalized_roles ?? [])->implode('; '),
                        collect($context->programme_l1 ?? [])->implode('; '),
                        collect($context->programme_l2 ?? [])->implode('; '),
                        collect($context->employer_types ?? [])->implode('; '),
                        $context->special_system_mentioned ? 'Yes' : 'No',
                        collect($context->special_system_employer_types ?? [])->implode('; '),
                        $context->needs_enrichment ? 'Yes' : 'No',
                        $context->classification_notes,
                        $context->confidence_score,
                        $candidate->sourceDocument?->title,
                        $candidate->source_document_id,
                        $context->evidence_excerpt ?: $candidate->evidence_excerpt,
                    ]);
                }
            }
        });

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return $csv ?: '';
};

Route::get('/sls/social-security-systems/export', function () use ($buildSocialSecurityAdminCsv) {
    $fileName = 'social-security-admin-organizations-' . now('America/Chicago')->format('Ymd-His') . '.csv';

    return response($buildSocialSecurityAdminCsv(), 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    ]);
})->name('sls.socialSecuritySystems.export');

Route::get('/sls/social-security-systems/export-file', function () use ($buildSocialSecurityAdminCsv) {
    $fileName = 'social-security-admin-organizations-' . now('America/Chicago')->format('Ymd-His') . '.csv';
    $directory = public_path('sls-exports');

    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . $fileName, $buildSocialSecurityAdminCsv());

    return redirect()
        ->route('sls.socialSecuritySystems.import')
        ->with('status', 'Export file created: ' . url('sls-exports/' . $fileName))
        ->with('export_url', url('sls-exports/' . $fileName));
})->name('sls.socialSecuritySystems.exportFile');

Route::post('/sls/social-security-systems/import', function (
    Request $request,
    KnowledgeIngestionService $ingestion,
    SocialSecurityAdminDocumentService $adminExtractor,
) {
    $data = $request->validate([
        'related_country_id' => ['nullable', 'integer', 'exists:countries,id'],
        'source_files' => ['required', 'array', 'min:1', 'max:50'],
        'source_files.*' => ['required', 'file', 'mimes:pdf,txt,csv,md,html,htm,docx,xlsx', 'max:51200'],
    ]);

    $uploadedFiles = collect($request->file('source_files', []))->values();
    $created = 0;
    $updated = 0;
    $found = 0;
    $sources = collect();
    $fallbackCountry = ! empty($data['related_country_id']) ? Country::find((int) $data['related_country_id']) : null;

    foreach ($uploadedFiles as $file) {
        $filenameTitle = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $country = $fallbackCountry ?: $adminExtractor->countryFromFilename($file->getClientOriginalName());

        $source = $ingestion->ingest($file, [
            'title' => Str::limit($filenameTitle, 250, ''),
            'source_type' => 'country_specific_information',
            'intake_category' => 'country_intelligence',
            'intake_action' => 'searchable_library',
            'contact_relationship_type' => null,
            'related_country_id' => $country?->id,
            'related_organization_name' => null,
            'intake_notes' => 'Imported for extraction of social security administrative organizations.',
            'language_code' => Str::lower((string) ($country?->default_language_code ?: 'en')),
            'products' => [],
        ]);

        $sources->push($source);
        $result = $adminExtractor->extract($source, $country);
        $created += $result['created'];
        $updated += $result['updated'];
        $found += $result['found'];
    }

    return redirect()
        ->route('sls.socialSecuritySystems.import')
        ->with('status', $sources->count() . ' document(s) uploaded; ' . $found . ' administrative organization context row(s) found; ' . $created . ' organization(s) staged, ' . $updated . ' organization update(s).');
})->name('sls.socialSecuritySystems.import.store');

Route::post('/sls/social-security-systems/candidates/{candidate}/approve', function (
    SocialSecurityAdminCandidate $candidate,
    SocialSecurityAdminDocumentService $adminExtractor,
) {
    $organization = $adminExtractor->approve($candidate->load('country', 'sourceDocument'));

    return back()->with('status', $candidate->organization_name . ' approved and linked to CRM organization #' . $organization->id . '.');
})->name('sls.socialSecuritySystems.candidates.approve');

Route::post('/sls/social-security-systems/candidates/{candidate}/reject', function (SocialSecurityAdminCandidate $candidate) {
    $candidate->update(['status' => 'rejected']);

    return back()->with('status', $candidate->organization_name . ' rejected.');
})->name('sls.socialSecuritySystems.candidates.reject');

Route::get('/sls/knowledge/batch/review', function (Request $request) {
    $sourceIds = collect(explode(',', (string) $request->query('sources')))
        ->map(fn (string $id) => (int) trim($id))
        ->filter()
        ->unique()
        ->values();

    abort_if($sourceIds->isEmpty(), 404);

    $documents = SourceDocument::query()
        ->with('products', 'chunks')
        ->whereIn('id', $sourceIds)
        ->orderByRaw('FIELD(id, ' . $sourceIds->implode(',') . ')')
        ->get();

    abort_if($documents->isEmpty(), 404);

    return view('sls.knowledge.batch', [
        'documents' => $documents,
        'sourceIds' => $sourceIds->implode(','),
    ]);
})->name('sls.knowledge.batch');

Route::post('/sls/knowledge/batch/approve-all', function (Request $request) {
    $sourceIds = collect(explode(',', (string) $request->input('sources')))
        ->map(fn (string $id) => (int) trim($id))
        ->filter()
        ->unique()
        ->values();

    abort_if($sourceIds->isEmpty(), 404);

    KnowledgeChunk::query()
        ->whereIn('source_document_id', $sourceIds)
        ->update(['approval_status' => 'approved']);

    SourceDocument::query()
        ->whereIn('id', $sourceIds)
        ->update(['approval_status' => 'approved']);

    return redirect()
        ->route('sls.knowledge.batch', ['sources' => $sourceIds->implode(',')])
        ->with('status', 'All chunks in this upload batch are approved for chat and proposal use.');
})->name('sls.knowledge.batch.approve_all');

Route::get('/sls/knowledge/{sourceDocument}/coverage', function (SourceDocument $sourceDocument) {
    $document = $sourceDocument->load('products', 'chunks.product');
    $combinedText = $document->chunks->pluck('chunk_text')->implode(' ');
    $normalizedText = preg_replace('/\s+/', ' ', $combinedText ?? '');
    $wordCount = str_word_count($normalizedText ?? '');
    $characterCount = strlen($combinedText);
    $chunkCount = $document->chunks->count();
    $artifactPatterns = [
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿' => substr_count($combinedText, 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿'),
        '?' => substr_count($combinedText, '?'),
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½' => substr_count($combinedText, 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½'),
        'eficient/eficiency' => preg_match_all('/\beficien(t|cy)\b/i', $combinedText),
        'atendance' => preg_match_all('/\batendance\b/i', $combinedText),
        'ofice/ofers' => preg_match_all('/\b(ofice|ofers)\b/i', $combinedText),
        'leters' => preg_match_all('/\bleters\b/i', $combinedText),
    ];

    $chunkStats = $document->chunks
        ->map(function (KnowledgeChunk $chunk) {
            $text = $chunk->chunk_text ?? '';
            $artifactCount = substr_count($text, 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿')
                + substr_count($text, '?')
                + substr_count($text, 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½')
                + preg_match_all('/\b(eficient|eficiency|atendance|ofice|ofers|leters|diferent|efective)\b/i', $text);

            return [
                'chunk' => $chunk,
                'words' => str_word_count($text),
                'characters' => strlen($text),
                'artifact_count' => $artifactCount,
                'preview' => str($text)->replace(["\u{00A0}", "\u{00AD}", "\u{00FF}"], ' ')->squish()->limit(350),
            ];
        })
        ->sortByDesc('artifact_count')
        ->values();

    $approvedCount = $document->chunks->where('approval_status', 'approved')->count();
    $averageWords = $chunkCount > 0 ? round($wordCount / $chunkCount) : 0;

    return view('sls.knowledge.coverage', [
        'document' => $document,
        'chunkCount' => $chunkCount,
        'approvedCount' => $approvedCount,
        'wordCount' => $wordCount,
        'characterCount' => $characterCount,
        'averageWords' => $averageWords,
        'artifactPatterns' => $artifactPatterns,
        'chunkStats' => $chunkStats,
    ]);
})->name('sls.knowledge.coverage');

Route::get('/sls/knowledge/{sourceDocument}', function (SourceDocument $sourceDocument) {
    return view('sls.knowledge.show', [
        'document' => $sourceDocument->load('products', 'chunks.product', 'intakeContacts'),
        'contactRelationshipOptions' => [
            'prospect' => 'Prospect',
            'account' => 'Account',
            'competitor' => 'Competitor',
            'partner' => 'Potential partner',
            'investor' => 'Potential investor',
            'acquisition_suitor' => 'Potential acquisition suitor',
            'supplier' => 'Supplier',
            'other' => 'Other',
        ],
    ]);
})->name('sls.knowledge.show');

Route::post('/sls/knowledge/{sourceDocument}/extract-contacts', function (
    Request $request,
    SourceDocument $sourceDocument,
    DocumentContactExtractionService $contactExtraction,
) {
    $relationshipOptions = ['prospect', 'account', 'competitor', 'partner', 'investor', 'acquisition_suitor', 'supplier', 'other'];
    $data = $request->validate([
        'contact_relationship_type' => ['required', 'in:' . implode(',', $relationshipOptions)],
    ]);

    $sourceDocument->update([
        'contact_relationship_type' => $data['contact_relationship_type'],
        'intake_action' => $sourceDocument->intake_action === 'searchable_library'
            ? 'searchable_and_extract'
            : ($sourceDocument->intake_action ?: 'extract_contacts'),
    ]);

    $result = $contactExtraction->extract($sourceDocument->fresh(['chunks']));

    return back()->with(
        'status',
        'Contact extraction completed: ' . $result['created'] . ' new contact(s), ' . $result['updated'] . ' updated.'
    );
})->name('sls.knowledge.extract_contacts');

Route::post('/sls/knowledge/{sourceDocument}/enrich-competitors', function (SourceDocument $sourceDocument) {
    $crawler = MarketCrawler::firstOrCreate(
        ['crawler_key' => 'competitor_bidder_enrichment'],
        [
            'name' => 'Competitor and bidder enrichment crawler',
            'crawler_type' => 'leadership_contact_crawler',
            'description' => 'Enriches competitor and bidder accounts created from tender documents by checking websites, procurement/supplier pages, leadership, HR, IT, news, and public contacts.',
            'is_enabled' => true,
        ],
    );

    $organizationIds = MarketOrganizationContact::query()
        ->where('contact_type', 'competitor_contact')
        ->where('notes', 'like', '%source document #' . $sourceDocument->id . '%')
        ->pluck('market_organization_id')
        ->filter()
        ->unique()
        ->values()
        ->all();

    if ($organizationIds === []) {
        return back()->with('status', 'No competitor/bidder CRM organizations have been created from this document yet. Extract contacts first.');
    }

    MarketOrganization::query()
        ->whereIn('id', $organizationIds)
        ->update(['market_crawler_id' => $crawler->id]);

    $batchRun = MarketCrawlerRun::query()->create([
        'market_crawler_id' => $crawler->id,
        'run_type' => 'direct_crawl_batch',
        'status' => 'started',
        'query_text' => 'Background competitor/bidder enrichment crawl for source document #' . $sourceDocument->id
            . ': ' . count($organizationIds) . ' organizations, 8 pages per organization.',
        'started_at' => now(),
        'finished_at' => null,
        'result_payload' => [
            'source_document_id' => $sourceDocument->id,
            'organization_ids' => $organizationIds,
            'organization_limit' => count($organizationIds),
            'page_limit' => 8,
        ],
    ]);

    $php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
    $artisan = base_path('artisan');
    $log = storage_path('logs/competitor-enrichment-crawl.log');
    $errorLog = storage_path('logs/competitor-enrichment-crawl-error.log');
    $arguments = [
        $artisan,
        'sls:crawler-crawl-pilot',
        (string) $crawler->id,
        '--organizations=' . count($organizationIds),
        '--pages=8',
        '--batch-id=' . $batchRun->id,
        '--organization-ids=' . implode(',', $organizationIds),
    ];

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd.exe /C start "" /B '
            . escapeshellarg($php) . ' '
            . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log)
            . ' 2> ' . escapeshellarg($errorLog);
        pclose(popen($command, 'r'));
    } else {
        $unixCommand = escapeshellarg($php) . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
            . ' > ' . escapeshellarg($log) . ' 2> ' . escapeshellarg($errorLog) . ' &';
        pclose(popen($unixCommand, 'r'));
    }

    return redirect()
        ->route('sls.organizations.crawlers.show', $crawler)
        ->with('status', 'Competitor/bidder enrichment crawl started for ' . count($organizationIds) . ' organization(s) from source document #' . $sourceDocument->id . '. Refresh this page manually to see progress.');
})->name('sls.knowledge.enrich_competitors');

Route::post('/sls/knowledge/{sourceDocument}/approve-all', function (SourceDocument $sourceDocument) {
    $sourceDocument->chunks()->update(['approval_status' => 'approved']);
    $sourceDocument->update(['approval_status' => 'approved']);

    return back()->with('status', 'All chunks approved for chat and proposal use.');
})->name('sls.knowledge.approve_all');

Route::post('/sls/knowledge/{sourceDocument}/reject-all', function (SourceDocument $sourceDocument) {
    $sourceDocument->chunks()->update(['approval_status' => 'rejected']);
    $sourceDocument->update(['approval_status' => 'rejected']);

    return back()->with('status', 'All chunks rejected.');
})->name('sls.knowledge.reject_all');

Route::post('/sls/knowledge/chunks/{knowledgeChunk}/approve', function (KnowledgeChunk $knowledgeChunk) {
    $knowledgeChunk->update(['approval_status' => 'approved']);
    $source = $knowledgeChunk->sourceDocument;

    if ($source && ! $source->chunks()->where('approval_status', '!=', 'approved')->exists()) {
        $source->update(['approval_status' => 'approved']);
    }

    return back()->with('status', 'Chunk approved for chat and proposal use.');
})->name('sls.knowledge.chunks.approve');

Route::post('/sls/knowledge/chunks/{knowledgeChunk}/reject', function (KnowledgeChunk $knowledgeChunk) {
    $knowledgeChunk->update(['approval_status' => 'rejected']);
    $knowledgeChunk->sourceDocument?->update(['approval_status' => 'unreviewed']);

    return back()->with('status', 'Chunk rejected.');
})->name('sls.knowledge.chunks.reject');

Route::get('/sls/chat', function () use ($orderedProducts) {
    return view('sls.chat', [
        'products' => $orderedProducts(),
        'question' => '',
        'answer' => null,
        'matches' => collect(),
        'visualMatches' => collect(),
        'aiProvider' => config('services.ai.provider', 'gemini'),
    ]);
})->name('sls.chat');

Route::post('/sls/chat', function (Request $request, KnowledgeChatService $chat, DemoMediaKnowledgeService $demoMediaKnowledge) use ($orderedProducts) {
    $data = $request->validate([
        'question' => ['required', 'string', 'max:1000'],
        'product_id' => ['nullable', 'integer', 'exists:products,id'],
    ]);

    $result = $chat->answer($data['question'], ! empty($data['product_id']) ? (int) $data['product_id'] : null);
    $matches = $result['matches'];
    $visualMatches = $demoMediaKnowledge->retrieve($data['question'], ! empty($data['product_id']) ? (int) $data['product_id'] : null);

    ChatAnswerLog::create([
        'product_id' => ! empty($data['product_id']) ? (int) $data['product_id'] : null,
        'ai_provider' => $result['ai_provider'] ?? config('services.ai.provider', 'gemini'),
        'answer_language' => $result['answer_language'] ?? null,
        'question' => $data['question'],
        'answer' => $result['answer'],
        'retrieval_terms' => $result['retrieval_terms'] ?? [],
        'source_matches' => $matches
            ->map(fn (array $match) => [
                'chunk_id' => $match['chunk']->id,
                'source_document_id' => $match['chunk']->source_document_id,
                'chunk_title' => $match['chunk']->chunk_title,
                'citation_label' => $match['chunk']->citation_label,
                'score' => $match['score'],
                'excerpt' => $match['excerpt'],
            ])
            ->values()
            ->all(),
        'visual_matches' => $visualMatches
            ->map(fn (array $match) => [
                'demo_feature_moment_id' => $match['moment']->id,
                'demo_session_id' => $match['moment']->demo_session_id,
                'feature_name' => $match['moment']->feature_name,
                'module_name' => $match['moment']->module_name,
                'score' => $match['score'],
                'excerpt' => $match['excerpt'],
                'frame_ids' => $match['frames']->pluck('id')->values()->all(),
            ])
            ->values()
            ->all(),
    ]);

    return view('sls.chat', [
        'products' => $orderedProducts(),
        'question' => $data['question'],
        'selectedProductId' => $data['product_id'] ?? null,
        'answer' => $result['answer'],
        'matches' => $matches,
        'visualMatches' => $visualMatches,
        'aiProvider' => $result['ai_provider'] ?? config('services.ai.provider', 'gemini'),
    ]);
})->name('sls.chat.ask');

Route::get('/sls/chat/logs', function () {
    return view('sls.chat-logs', [
        'logs' => ChatAnswerLog::with('product')->latest()->limit(100)->get(),
    ]);
})->name('sls.chat.logs');










