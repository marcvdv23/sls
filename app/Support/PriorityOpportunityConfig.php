<?php

namespace App\Support;

use App\Models\PriorityOpportunityOption;
use App\Models\SlsSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PriorityOpportunityConfig
{
    protected static ?bool $tableReady = null;

    public static function statusDefaults(): array
    {
        return [
            'new' => ['label' => 'New', 'sort_order' => 10],
            'researching' => ['label' => 'Researching', 'sort_order' => 20, 'is_default' => true],
            'qualified' => ['label' => 'Qualified', 'sort_order' => 30],
            'outreach_planned' => ['label' => 'Outreach planned', 'sort_order' => 40],
            'contacted' => ['label' => 'Contacted', 'sort_order' => 50],
            'active_pursuit' => ['label' => 'Active pursuit', 'sort_order' => 60],
            'parked' => ['label' => 'Parked', 'sort_order' => 90],
            'closed' => ['label' => 'Closed', 'sort_order' => 100],
        ];
    }

    public static function priorityDefaults(): array
    {
        return [
            'low' => ['label' => 'Low', 'sort_order' => 40],
            'normal' => ['label' => 'Normal', 'sort_order' => 30],
            'high' => ['label' => 'High', 'sort_order' => 20, 'is_default' => true],
            'urgent' => ['label' => 'Urgent', 'sort_order' => 10],
        ];
    }

    public static function tierDefaults(): array
    {
        return [
            'immediate_focus' => [
                'label' => 'Immediate focus',
                'sort_order' => 10,
                'metadata' => ['default_status' => 'researching', 'default_priority' => 'urgent', 'task_due_days' => 3],
            ],
            'high' => [
                'label' => 'High',
                'sort_order' => 20,
                'metadata' => ['default_status' => 'new', 'default_priority' => 'high', 'task_due_days' => 7],
            ],
            'medium' => [
                'label' => 'Medium',
                'sort_order' => 30,
                'metadata' => ['default_status' => 'new', 'default_priority' => 'normal', 'task_due_days' => 14],
            ],
        ];
    }

    public static function settingDefinitions(): array
    {
        return [
            'priority_opportunities.default_status' => [
                'label' => 'Default import status',
                'description' => 'Status used when an imported row does not match a focus-tier rule.',
                'value_type' => 'select:status',
                'default' => 'new',
            ],
            'priority_opportunities.default_priority' => [
                'label' => 'Default import priority',
                'description' => 'Priority used when an imported row does not match a focus-tier rule.',
                'value_type' => 'select:priority',
                'default' => 'high',
            ],
            'priority_opportunities.default_due_days' => [
                'label' => 'Default task due days',
                'description' => 'Number of days after import for non-urgent created tasks.',
                'value_type' => 'integer',
                'default' => '7',
            ],
            'priority_opportunities.task_type' => [
                'label' => 'Created task type',
                'description' => 'Task type assigned to follow-up tasks created during import.',
                'value_type' => 'string',
                'default' => 'research',
            ],
            'priority_opportunities.import_lead_status' => [
                'label' => 'Imported account lead status',
                'description' => 'Lead status assigned to newly created CRM accounts from shortlist import.',
                'value_type' => 'string',
                'default' => 'researching',
            ],
            'priority_opportunities.organization_type' => [
                'label' => 'Imported account type',
                'description' => 'Organization type assigned to newly created CRM accounts from shortlist import.',
                'value_type' => 'string',
                'default' => 'government_agency',
            ],
            'priority_opportunities.organization_industry' => [
                'label' => 'Imported account industry',
                'description' => 'Industry assigned to newly created CRM accounts from shortlist import.',
                'value_type' => 'string',
                'default' => 'Social security',
            ],
            'priority_opportunities.organization_subcategory' => [
                'label' => 'Imported account subcategory',
                'description' => 'Subcategory assigned to newly created CRM accounts from shortlist import.',
                'value_type' => 'string',
                'default' => 'social_security_administration',
            ],
        ];
    }

    public static function seedDefaults(): void
    {
        if (! static::tableReady()) {
            return;
        }

        foreach ([
            'status' => static::statusDefaults(),
            'priority' => static::priorityDefaults(),
            'focus_tier' => static::tierDefaults(),
        ] as $group => $options) {
            foreach ($options as $key => $definition) {
                PriorityOpportunityOption::query()->firstOrCreate(
                    ['option_group' => $group, 'option_key' => $key],
                    [
                        'label' => $definition['label'],
                        'description' => $definition['description'] ?? null,
                        'metadata' => $definition['metadata'] ?? [],
                        'sort_order' => $definition['sort_order'] ?? 0,
                        'is_enabled' => true,
                        'is_default' => (bool) ($definition['is_default'] ?? false),
                    ]
                );
            }
        }

        if (Schema::hasTable('sls_settings')) {
            foreach (static::settingDefinitions() as $key => $definition) {
                SlsSetting::query()->firstOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $definition['default'] ?? null,
                        'value_type' => $definition['value_type'] ?? 'string',
                        'setting_group' => 'Priority Opportunities',
                        'label' => $definition['label'],
                        'description' => $definition['description'] ?? null,
                    ]
                );
            }
        }
    }

    public static function statuses(bool $includeDisabled = false): array
    {
        return static::optionLabels('status', static::statusDefaults(), $includeDisabled);
    }

    public static function priorities(bool $includeDisabled = false): array
    {
        return static::optionLabels('priority', static::priorityDefaults(), $includeDisabled);
    }

    public static function tiers(bool $includeDisabled = false): Collection
    {
        if (! static::tableReady()) {
            return collect(static::tierDefaults())
                ->map(fn (array $definition) => $definition['label'])
                ->values();
        }

        return static::options('focus_tier', $includeDisabled)
            ->map(fn (PriorityOpportunityOption $option) => $option->label)
            ->values();
    }

    public static function statusOrder(): array
    {
        return array_keys(static::statuses());
    }

    public static function priorityOrder(): array
    {
        return array_keys(static::priorities());
    }

    public static function importDefaultsForTier(?string $tier): array
    {
        $normalizedTier = static::normalizeKey((string) $tier);
        $option = static::options('focus_tier', true)
            ->first(fn (PriorityOpportunityOption $candidate) => $candidate->option_key === $normalizedTier || static::normalizeKey($candidate->label) === $normalizedTier);
        $metadata = $option?->metadata ?? [];

        return [
            'status' => $metadata['default_status'] ?? static::setting('priority_opportunities.default_status', 'new'),
            'priority' => $metadata['default_priority'] ?? static::setting('priority_opportunities.default_priority', 'high'),
            'due_days' => (int) ($metadata['task_due_days'] ?? static::setting('priority_opportunities.default_due_days', '7')),
        ];
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('sls_settings')) {
            return $default;
        }

        return SlsSetting::query()->where('setting_key', $key)->value('setting_value') ?? $default;
    }

    public static function settingsForView(): Collection
    {
        static::seedDefaults();

        return collect(static::settingDefinitions())
            ->map(function (array $definition, string $key) {
                $definition['key'] = $key;
                $definition['value'] = static::setting($key, $definition['default'] ?? null);

                return $definition;
            });
    }

    public static function groupedOptionsForView(): Collection
    {
        static::seedDefaults();

        if (! static::tableReady()) {
            return collect();
        }

        return PriorityOpportunityOption::query()
            ->orderBy('option_group')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('option_group');
    }

    public static function tableReady(): bool
    {
        if (static::$tableReady !== null) {
            return static::$tableReady;
        }

        try {
            static::$tableReady = Schema::hasTable('priority_opportunity_options');
        } catch (\Throwable) {
            static::$tableReady = false;
        }

        return static::$tableReady;
    }

    public static function flush(): void
    {
        static::$tableReady = null;
    }

    protected static function optionLabels(string $group, array $fallback, bool $includeDisabled = false): array
    {
        if (! static::tableReady()) {
            return collect($fallback)->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['label']])->all();
        }

        return static::options($group, $includeDisabled)
            ->mapWithKeys(fn (PriorityOpportunityOption $option) => [$option->option_key => $option->label])
            ->all();
    }

    protected static function options(string $group, bool $includeDisabled = false): Collection
    {
        if (! static::tableReady()) {
            return collect();
        }

        static::seedDefaults();

        return PriorityOpportunityOption::query()
            ->where('option_group', $group)
            ->when(! $includeDisabled, fn ($query) => $query->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    protected static function normalizeKey(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
