<?php

namespace App\Services;

use App\Models\CountryUpdate;
use Illuminate\Database\Eloquent\Builder;

class TenderDocumentProcessor
{
    public function __construct(
        private SourceContactExtractionService $contactExtractor,
        private TenderAwardLookupService $awardLookup,
    ) {
    }

    /**
     * @return array{processed:int, documents_archived:int, contacts_created:int, contacts_updated:int, awards_found:int}
     */
    public function process(int $limit = 20): array
    {
        $processed = 0;
        $documentsArchived = 0;
        $contactsCreated = 0;
        $contactsUpdated = 0;
        $awardsFound = 0;

        $this->candidateQuery()
            ->limit(max(1, $limit))
            ->get()
            ->each(function (CountryUpdate $update) use (&$processed, &$documentsArchived, &$contactsCreated, &$contactsUpdated, &$awardsFound) {
                $contactResult = $this->contactExtractor->extractForUpdate($update);
                $documentsArchived += $contactResult['documents_archived'];
                $contactsCreated += $contactResult['contacts_created'];
                $contactsUpdated += $contactResult['contacts_updated'];

                if (! $update->award_checked_at) {
                    $awardResult = $this->awardLookup->check($update);

                    if ($awardResult['status'] === 'awarded') {
                        $awardsFound++;
                    }
                }

                $processed++;
            });

        return [
            'processed' => $processed,
            'documents_archived' => $documentsArchived,
            'contacts_created' => $contactsCreated,
            'contacts_updated' => $contactsUpdated,
            'awards_found' => $awardsFound,
        ];
    }

    private function candidateQuery(): Builder
    {
        return CountryUpdate::query()
            ->with('country')
            ->whereNotNull('source_url')
            ->where(function (Builder $builder) {
                $builder->where('source_url', 'like', '%procurement-detail%')
                    ->orWhere('source_url', 'like', '%idbdocs%')
                    ->orWhere('source_url', 'like', '%procurement%')
                    ->orWhere('source_name', 'like', '%Procurement%')
                    ->orWhere('summary', 'like', '%[Official tender source]%')
                    ->orWhere('summary', 'like', '%[HRMS Tender Intelligence]%')
                    ->orWhere('summary', 'like', '%[ERMS Tender Intelligence]%')
                    ->orWhere('summary', 'like', '%[EBPC Tender Intelligence]%');
            })
            ->where(function (Builder $builder) {
                $builder->where('source_url', 'like', '%procurement-detail%')
                    ->orWhere('source_name', 'like', '%Procurement%')
                    ->orWhere('summary', 'like', '%[Official tender source]%')
                    ->orWhere('summary', 'like', '%Request for Proposals%')
                    ->orWhere('summary', 'like', '%Request for Bids%')
                    ->orWhere('title', 'like', '%tender%')
                    ->orWhere('title', 'like', '%bid%')
                    ->orWhere('title', 'like', '%procurement%')
                    ->orWhere('title', 'like', '%request for%')
                    ->orWhere('title', 'like', '%rfp%')
                    ->orWhere('title', 'like', '%payroll%')
                    ->orWhere('title', 'like', '%HRMIS%')
                    ->orWhere('title', 'like', '%HRMS%')
                    ->orWhere('title', 'like', '%HRIS%')
                    ->orWhere('title', 'like', '%HCM%')
                    ->orWhere('title', 'like', '%pension management information system%')
                    ->orWhere('title', 'like', '%social security pension%');
            })
            ->where(function (Builder $builder) {
                $builder->whereDoesntHave('intelligenceDocuments')
                    ->orWhereNull('award_checked_at');
            })
            ->latest('retrieved_at');
    }
}
