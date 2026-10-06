<?php

use App\Support\EurLexDocumentClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'legal_document_code')) {
                $table->string('legal_document_code', 80)->nullable()->after('source_fingerprint')->index();
            }

            if (! Schema::hasColumn('country_updates', 'legal_instrument_type')) {
                $table->string('legal_instrument_type', 80)->nullable()->after('legal_document_code')->index();
            }

            if (! Schema::hasColumn('country_updates', 'legislation_stage')) {
                $table->string('legislation_stage', 80)->nullable()->after('legal_instrument_type')->index();
            }
        });

        DB::table('country_updates')
            ->where(function ($query) {
                $query
                    ->where('source_name', 'like', '%EUR-Lex%')
                    ->orWhere('source_url', 'like', '%eur-lex.europa.eu%')
                    ->orWhere('summary', 'like', '%CELEX:%');
            })
            ->orderBy('id')
            ->chunkById(250, function ($updates): void {
                foreach ($updates as $update) {
                    $celex = $this->extractCelex(implode(' ', [
                        $update->legal_document_code ?? '',
                        $update->summary ?? '',
                        $update->source_url ?? '',
                        $update->title ?? '',
                        $update->title_english ?? '',
                        $update->title_original ?? '',
                    ]));

                    $classification = EurLexDocumentClassifier::classify(
                        $celex,
                        implode(' ', [
                            $update->title_english ?? '',
                            $update->title_original ?? '',
                            $update->title ?? '',
                            $update->summary ?? '',
                        ]),
                        (string) ($update->source_name ?? ''),
                        (string) ($update->source_url ?? ''),
                    );

                    DB::table('country_updates')
                        ->where('id', $update->id)
                        ->update($classification);
                }
            });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            foreach (['legislation_stage', 'legal_instrument_type', 'legal_document_code'] as $column) {
                if (Schema::hasColumn('country_updates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function extractCelex(string $value): ?string
    {
        return preg_match('/\b([0-9][0-9]{4}[A-Z]{1,3}[0-9A-Z]{3,}(?:R(?:\([0-9A-Z]+\))?|\([0-9A-Z]+\))?)\b/i', $value, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }
};
