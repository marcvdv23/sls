<?php

namespace App\Services;

use App\Support\TitleLanguage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TitleTranslationService
{
    public function __construct(private GeminiAnswerService $gemini)
    {
    }

    public function toEnglish(string $title, string $sourceUrl = '', string $country = '', string $sourceName = ''): string
    {
        $fallback = $this->cleanText($title);
        $fallback = $this->preferReadableUrlTitle($fallback, $sourceUrl);

        return $this->translateToEnglish($fallback, $sourceUrl, $country, $sourceName, 500, true);
    }

    public function summaryToEnglish(string $summary, string $sourceUrl = '', string $country = '', string $sourceName = ''): string
    {
        $fallback = $this->cleanText($summary);

        return $this->translateToEnglish($fallback, $sourceUrl, $country, $sourceName, 1200, false);
    }

    private function translateToEnglish(string $text, string $sourceUrl, string $country, string $sourceName, int $limit, bool $isTitle): string
    {
        if ($text === '') {
            return '';
        }

        if ($isTitle) {
            $knownTranslation = $this->translateKnownTitle($text);

            if ($knownTranslation !== null) {
                return $knownTranslation;
            }
        }

        if (! TitleLanguage::looksNonEnglish($text)) {
            return Str::limit($text, $limit, '');
        }

        foreach ((array) config('services.translation.provider_order', []) as $provider) {
            $translated = match ((string) $provider) {
                'deepl' => $this->translateWithDeepL($text),
                'google' => $this->translateWithGoogle($text),
                'azure' => $this->translateWithAzure($text),
                'libretranslate' => $this->translateWithLibreTranslate($text),
                'mymemory' => $this->translateWithMyMemory($text),
                'gemini' => $this->translateWithGemini($text, $sourceUrl, $country, $sourceName),
                default => null,
            };

            if ($translated !== null && TitleLanguage::isUsableEnglishTitle($translated, $text)) {
                return Str::limit($translated, $limit, '');
            }
        }

        return '';
    }

    private function translateWithDeepL(string $text): ?string
    {
        $apiKey = trim((string) config('services.deepl.api_key'));

        if ($apiKey === '') {
            return null;
        }

        $endpoint = rtrim((string) config('services.deepl.endpoint', 'https://api-free.deepl.com'), '/');

        try {
            $response = Http::timeout((int) config('services.translation.timeout', 8))
                ->withOptions($this->httpOptions())
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $apiKey])
                ->asForm()
                ->post($endpoint . '/v2/translate', [
                    'text' => $text,
                    'target_lang' => 'EN',
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->cleanText((string) data_get($response->json(), 'translations.0.text'));
        } catch (Throwable) {
            return null;
        }
    }

    private function translateWithGoogle(string $text): ?string
    {
        $apiKey = trim((string) config('services.google_translate.api_key'));

        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.translation.timeout', 8))
                ->withOptions($this->httpOptions())
                ->post('https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($apiKey), [
                    'q' => $text,
                    'target' => 'en',
                    'format' => 'text',
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->cleanText((string) data_get($response->json(), 'data.translations.0.translatedText'));
        } catch (Throwable) {
            return null;
        }
    }

    private function translateWithAzure(string $text): ?string
    {
        $key = trim((string) config('services.azure_translator.key'));

        if ($key === '') {
            return null;
        }

        $endpoint = rtrim((string) config('services.azure_translator.endpoint'), '/');

        if ($endpoint === '') {
            return null;
        }

        $headers = [
            'Ocp-Apim-Subscription-Key' => $key,
            'Content-Type' => 'application/json',
        ];
        $region = trim((string) config('services.azure_translator.region'));

        if ($region !== '') {
            $headers['Ocp-Apim-Subscription-Region'] = $region;
        }

        try {
            $response = Http::timeout((int) config('services.translation.timeout', 8))
                ->withOptions($this->httpOptions())
                ->withHeaders($headers)
                ->post($endpoint . '/translate?api-version=3.0&to=en', [
                    ['text' => $text],
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->cleanText((string) data_get($response->json(), '0.translations.0.text'));
        } catch (Throwable) {
            return null;
        }
    }

    private function translateWithLibreTranslate(string $text): ?string
    {
        if (! (bool) config('services.libretranslate.enabled', true)) {
            return null;
        }

        $endpoint = rtrim((string) config('services.libretranslate.endpoint'), '/');

        if ($endpoint === '') {
            return null;
        }

        try {
            $payload = [
                'q' => $text,
                'source' => 'auto',
                'target' => 'en',
                'format' => 'text',
            ];

            $apiKey = trim((string) config('services.libretranslate.api_key'));

            if ($apiKey !== '') {
                $payload['api_key'] = $apiKey;
            }

            $response = Http::timeout((int) config('services.libretranslate.timeout', 8))
                ->withOptions($this->httpOptions())
                ->asForm()
                ->post($endpoint . '/translate', $payload);

            if (! $response->successful()) {
                return null;
            }

            return $this->cleanText((string) data_get($response->json(), 'translatedText'));
        } catch (Throwable) {
            return null;
        }
    }

    private function translateWithMyMemory(string $text): ?string
    {
        if (! (bool) config('services.mymemory.enabled', true)) {
            return null;
        }

        $sourceLanguage = $this->guessSourceLanguage($text);

        if ($sourceLanguage === null) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.translation.timeout', 8))
                ->withOptions($this->httpOptions())
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => Str::limit($text, 4900, ''),
                    'langpair' => $sourceLanguage . '|en',
                    'de' => config('services.mymemory.email'),
                    'key' => config('services.mymemory.key'),
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->cleanText((string) data_get($response->json(), 'responseData.translatedText'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function httpOptions(): array
    {
        if (! (bool) config('services.translation.verify_ssl', true)) {
            return ['verify' => false];
        }

        $caBundle = (string) config('services.translation.ca_bundle');

        if ($caBundle !== '' && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return [];
    }

    private function translateWithGemini(string $text, string $sourceUrl, string $country, string $sourceName): ?string
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $translation = $this->gemini->translateTitle($text, $sourceUrl, $country, $sourceName);

            if (TitleLanguage::isUsableEnglishTitle($translation, $text)) {
                return $translation;
            }
        }

        return null;
    }

    private function guessSourceLanguage(string $text): ?string
    {
        $lowerOriginal = Str::lower($text);
        $normalized = ' ' . Str::lower(Str::ascii($text)) . ' ';

        if (preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1) {
            return 'km';
        }

        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) === 1) {
            return 'zh-CN';
        }

        if (preg_match('/[\x{3040}-\x{30FF}]/u', $text) === 1) {
            return 'ja';
        }

        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $text) === 1) {
            return 'ko';
        }

        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $text) === 1) {
            return 'th';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1) {
            return 'ar';
        }

        if (preg_match('/[\x{0530}-\x{058F}]/u', $text) === 1) {
            return 'hy';
        }

        if (preg_match('/[\x{0400}-\x{04FF}]/u', $text) === 1) {
            return Str::contains($lowerOriginal, ['оны', 'сард', 'жирэмсэн', 'тэтгэмж', 'байна'])
                ? 'mn'
                : 'ru';
        }

        if (Str::contains($normalized, [
            ' ahora ', ' ancianos ', ' beneficio ', ' colaboradores ', ' corrupcion ', ' contraloria ',
            ' aranceles ', ' ante ', ' agenda laboral ', ' desde ', ' del ', ' denunciar ', ' el ', ' exportador ',
            ' francas ', ' haiti ', ' hogar ', ' ideologia ', ' jaque ', ' la ', ' licitacion ', ' modelo ',
            ' migrantes ', ' migratoria ', ' objetividad ', ' oit ', ' pactan ', ' para ', ' plazos ',
            ' proyecto ', ' regularizacion ', ' se unen ', ' zonas ',
        ])) {
            return 'es';
        }

        if (Str::contains($normalized, [' avec ', ' des ', ' du ', ' et ', ' le ', ' les ', ' projet ', ' securite sociale ', ' retraite ', ' pension '])) {
            return 'fr';
        }

        if (Str::contains($normalized, [' dos ', ' para ', ' previdencia ', ' seguranca social ', ' licitacao '])) {
            return 'pt';
        }

        if (Str::contains($normalized, [
            ' altersarmut ', ' anhorung ', ' berichtet ', ' bestimmen ', ' einsamkeit ', ' einwanderern ',
            ' konnte ', ' moldau ', ' podcast ', ' supreme court ', ' uber ', ' vor ', ' programm ',
            ' sozialen ', ' sicherungssysteme ', ' starkung ', ' zur ',
        ])) {
            return 'de';
        }

        if (Str::contains($normalized, [' buurtvergadering ', ' direct ', ' grijpen ', ' grond ', ' minister ', ' nieuwe '])) {
            return 'nl';
        }

        if (Str::contains($normalized, [' emeklilik ', ' sosyal ', ' turkiye ', ' uzbekistan '])) {
            return 'tr';
        }

        return null;
    }

    private function translateKnownTitle(string $title): ?string
    {
        $normalized = Str::of($title)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        $translations = [
            'inclusion numerique et entrepreneuriat le projet sagev couronne de succes a mbour et saint louis'
                => 'Digital inclusion and entrepreneurship: The Sagev project hailed as a success in Mbour and Saint-Louis',
            'ahora podras denunciar corrupcion desde tu celular contraloria lanza alerta segura'
                => 'You can now report corruption from your phone: Comptroller launches Alerta Segura',
            'tournee nationale de visite des inspections du travail et de la securite sociale phase 1'
                => 'National Tour of Labor and Social Security Inspections - Phase 1',
        ];

        return $translations[$normalized] ?? null;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->repairCommonEncodingDamage($text);
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return trim($text, " \t\n\r\0\x0B\"'`*#");
    }

    private function repairCommonEncodingDamage(string $text): string
    {
        $text = str_replace([
            'num' . chr(226) . chr(128) . chr(154) . 'rique',
            'couronn' . chr(226) . chr(128) . chr(154),
            'succ' . chr(197) . chr(160) . 's',
            'corrupci' . chr(194) . chr(162) . 'n',
            'Contralor' . chr(194) . chr(161) . 'a',
            'contralor' . chr(194) . chr(161) . 'a',
            'podr' . chr(194) . chr(160) . 's',
        ], [
            'numerique',
            'couronne',
            'succes',
            'corrupcion',
            'Contraloria',
            'contraloria',
            'podras',
        ], $text);

        return preg_replace('/\s+' . chr(226) . chr(128) . chr(166) . '\s+/', ' a ', $text) ?? $text;
    }

    private function preferReadableUrlTitle(string $title, string $sourceUrl): string
    {
        if (! $this->hasEncodingDamage($title)) {
            return $title;
        }

        $path = parse_url($sourceUrl, PHP_URL_PATH);

        if (! is_string($path) || trim($path, '/') === '') {
            return $title;
        }

        $slug = trim((string) basename(trim($path, '/')));
        $slug = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $slug) ?? $slug;
        $slug = preg_replace('/-\d+$/', '', $slug) ?? $slug;
        $slug = str_replace(['-', '_'], ' ', rawurldecode($slug));
        $slug = $this->cleanText($slug);

        return strlen($slug) > 20 ? $slug : $title;
    }

    private function hasEncodingDamage(string $title): bool
    {
        return str_contains($title, chr(226))
            || str_contains($title, chr(194))
            || str_contains($title, chr(197))
            || str_contains($title, "\xEF\xBF\xBD")
            || str_contains($title, "\u{00A0}");
    }
}
