<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SourceMaintenanceMetrics
{
    /**
     * @param Collection<int, object> $countries
     * @return array<string, int|float>
     */
    public function calculate(Collection $countries): array
    {
        $sourceSlotCount = 0;
        $realOrganizationCount = 0;
        $confirmedNonexistentOrganizationCount = 0;
        $urlCount = 0;
        $confirmedNonexistentUrlCount = 0;
        $completeSourceSlotCount = 0;

        foreach ($countries as $country) {
            $links = collect($country->social_security_administration_links ?? []);
            $sourceSlotCount += $links->count();

            foreach ($links as $link) {
                $hasOrganization = filled($link['name'] ?? null);
                $organizationConfirmedNonexistent = filled($link['organization_nonexistent_confirmed_at'] ?? null);
                $urls = collect([
                    $link['general_url'] ?? null,
                    $link['press_url'] ?? null,
                    $link['tenders_url'] ?? null,
                ])->filter(fn ($url) => filled($url));
                $confirmedNonexistentUrls = collect([
                    $link['general_url_nonexistent_confirmed_at'] ?? null,
                    $link['press_url_nonexistent_confirmed_at'] ?? null,
                    $link['tenders_url_nonexistent_confirmed_at'] ?? null,
                ])->filter(fn ($confirmedAt) => filled($confirmedAt))->count();

                if ($hasOrganization) {
                    $realOrganizationCount += 1;
                }

                if ($organizationConfirmedNonexistent) {
                    $confirmedNonexistentOrganizationCount += 1;
                }

                $urlCount += $urls->count();
                $confirmedNonexistentUrlCount += $confirmedNonexistentUrls;

                if (($hasOrganization || $organizationConfirmedNonexistent) && ($urls->count() + $confirmedNonexistentUrls) === 3) {
                    $completeSourceSlotCount += 1;
                }
            }
        }

        $handledOrganizationCount = $realOrganizationCount + $confirmedNonexistentOrganizationCount;
        $targetUrlCount = $sourceSlotCount * 3;
        $missingOrganizationCount = max(0, $sourceSlotCount - $handledOrganizationCount);
        $missingUrlCount = max(0, $targetUrlCount - $urlCount - $confirmedNonexistentUrlCount);
        $completionBasis = $sourceSlotCount + $targetUrlCount;
        $completionValue = $handledOrganizationCount + $urlCount + $confirmedNonexistentUrlCount;
        $organizationCompletionPercent = $sourceSlotCount > 0
            ? round(($handledOrganizationCount / $sourceSlotCount) * 100, 2)
            : 0.0;
        $urlCompletionPercent = $targetUrlCount > 0
            ? round((($urlCount + $confirmedNonexistentUrlCount) / $targetUrlCount) * 100, 2)
            : 0.0;

        return [
            'country_count' => $countries->count(),
            'source_slot_count' => $sourceSlotCount,
            'defined_organization_count' => $handledOrganizationCount,
            'real_organization_count' => $realOrganizationCount,
            'confirmed_nonexistent_organization_count' => $confirmedNonexistentOrganizationCount,
            'missing_organization_count' => $missingOrganizationCount,
            'url_count' => $urlCount,
            'confirmed_nonexistent_url_count' => $confirmedNonexistentUrlCount,
            'target_url_count' => $targetUrlCount,
            'missing_url_count' => $missingUrlCount,
            'complete_source_slot_count' => $completeSourceSlotCount,
            'organization_completion_percent' => $organizationCompletionPercent,
            'url_completion_percent' => $urlCompletionPercent,
            'completion_field_count' => $completionValue,
            'completion_target_field_count' => $completionBasis,
            'completion_percent' => $completionBasis > 0 ? round(($completionValue / $completionBasis) * 100, 2) : 0.0,
        ];
    }

    /**
     * @param array<string, int|float> $metrics
     */
    public function recordDailySnapshot(?int $workspaceId, array $metrics, ?CarbonInterface $date = null): void
    {
        if (! Schema::hasTable('source_maintenance_metric_snapshots')) {
            return;
        }

        $snapshotDate = ($date ?: now())->toDateString();
        $now = now();
        $values = [
            'country_count' => (int) ($metrics['country_count'] ?? 0),
            'source_slot_count' => (int) ($metrics['source_slot_count'] ?? 0),
            'defined_organization_count' => (int) ($metrics['defined_organization_count'] ?? 0),
            'missing_organization_count' => (int) ($metrics['missing_organization_count'] ?? 0),
            'url_count' => (int) ($metrics['url_count'] ?? 0),
            'target_url_count' => (int) ($metrics['target_url_count'] ?? 0),
            'missing_url_count' => (int) ($metrics['missing_url_count'] ?? 0),
            'complete_source_slot_count' => (int) ($metrics['complete_source_slot_count'] ?? 0),
            'completion_percent' => (float) ($metrics['completion_percent'] ?? 0),
            'metrics' => json_encode($metrics),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_organization_count')) {
            $values['confirmed_nonexistent_organization_count'] = (int) ($metrics['confirmed_nonexistent_organization_count'] ?? 0);
        }

        if (Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_url_count')) {
            $values['confirmed_nonexistent_url_count'] = (int) ($metrics['confirmed_nonexistent_url_count'] ?? 0);
        }

        DB::table('source_maintenance_metric_snapshots')->updateOrInsert(
            [
                'workspace_id' => $workspaceId,
                'snapshot_date' => $snapshotDate,
            ],
            $values,
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function history(?int $workspaceId, int $days = 60): Collection
    {
        if (! Schema::hasTable('source_maintenance_metric_snapshots')) {
            return collect();
        }

        return DB::table('source_maintenance_metric_snapshots')
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where('snapshot_date', '>=', now()->subDays(max(1, $days))->toDateString())
            ->orderBy('snapshot_date')
            ->get();
    }
}
