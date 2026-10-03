<?php

namespace App\Support;

use App\Models\SlsSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SlsSettings
{
    protected static ?Collection $settings = null;
    protected static ?bool $tableReady = null;

    public static function definitions(): array
    {
        return [
            'platform.name' => [
                'group' => 'Platform',
                'label' => 'Platform name',
                'description' => 'Short product name shown in page titles and app chrome.',
                'value_type' => 'string',
                'default' => config('sls.platform.name', 'SLS'),
            ],
            'platform.full_name' => [
                'group' => 'Platform',
                'label' => 'Platform full name',
                'description' => 'Plain-language product meaning. SLS is the generic sales platform.',
                'value_type' => 'string',
                'default' => config('sls.platform.full_name', 'Sales'),
            ],
            'platform.legacy_name' => [
                'group' => 'Platform',
                'label' => 'Legacy name',
                'description' => 'Old or abbreviated app label used where existing users still expect it.',
                'value_type' => 'string',
                'default' => config('sls.platform.legacy_name', '1G-SLS'),
            ],
            'entity.key' => [
                'group' => 'Entity',
                'label' => 'Entity key',
                'description' => 'Stable lowercase key for this organization or deployment, such as 2interact or rckgrp.',
                'value_type' => 'string',
                'default' => config('sls.entity.key', '2interact'),
            ],
            'entity.name' => [
                'group' => 'Entity',
                'label' => 'Entity name',
                'description' => 'Legal or internal organization name for this SLS deployment.',
                'value_type' => 'string',
                'default' => config('sls.entity.name', '2interact'),
            ],
            'entity.display_name' => [
                'group' => 'Entity',
                'label' => 'Entity display name',
                'description' => 'User-facing organization name shown in navigation and login copy.',
                'value_type' => 'string',
                'default' => config('sls.entity.display_name', config('sls.entity.name', '2interact')),
            ],
            'workspace.key' => [
                'group' => 'Workspace',
                'label' => 'Workspace key',
                'description' => 'Stable lowercase key for the current sales/intelligence workspace.',
                'value_type' => 'string',
                'default' => config('sls.workspace.key', 'social_security'),
            ],
            'workspace.name' => [
                'group' => 'Workspace',
                'label' => 'Workspace name',
                'description' => 'Name shown for the active sales/intelligence workspace.',
                'value_type' => 'string',
                'default' => config('sls.workspace.name', 'Social Security Sales'),
            ],
            'workspace.description' => [
                'group' => 'Workspace',
                'label' => 'Workspace description',
                'description' => 'Short internal explanation of this workspace focus.',
                'value_type' => 'text',
                'default' => config('sls.workspace.description', 'Social security, pensions, public sector HR/payroll, and related sales intelligence.'),
            ],
            'workspace.domain_label' => [
                'group' => 'Workspace',
                'label' => 'Domain label',
                'description' => 'Business domain label used in setup and intelligence screens.',
                'value_type' => 'string',
                'default' => config('sls.workspace.domain_label', 'Social Security and Public Sector Software'),
            ],
            'workspace.opportunity_label' => [
                'group' => 'Workspace',
                'label' => 'Opportunity label',
                'description' => 'Label for curated priority opportunities.',
                'value_type' => 'string',
                'default' => config('sls.workspace.opportunity_label', 'Curated social security opportunities'),
            ],
            'workspace.review_label' => [
                'group' => 'Workspace',
                'label' => 'Review queue label',
                'description' => 'Navigation label for the human review queue.',
                'value_type' => 'string',
                'default' => config('sls.workspace.review_label', 'Review Desk'),
            ],
            'products.default_code' => [
                'group' => 'Products',
                'label' => 'Default product code',
                'description' => 'Product code used as the default for mapping and intake.',
                'value_type' => 'string',
                'default' => config('sls.products.default_code', 'SSAS'),
            ],
            'products.default_name' => [
                'group' => 'Products',
                'label' => 'Default product name',
                'description' => 'Product name used when finding the default product.',
                'value_type' => 'string',
                'default' => config('sls.products.default_name', 'Interact SSAS'),
            ],
            'products.order' => [
                'group' => 'Products',
                'label' => 'Product order',
                'description' => 'Comma-separated product codes used for dashboard and selector ordering.',
                'value_type' => 'string',
                'default' => implode(',', config('sls.products.order', ['SSAS', 'HRMS', 'ERMS', 'EBPC'])),
            ],
        ];
    }

    public static function seedDefaults(): void
    {
        if (! static::tableReady()) {
            return;
        }

        foreach (static::definitions() as $key => $definition) {
            $setting = SlsSetting::query()->firstOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $definition['default'] ?? null,
                    'value_type' => $definition['value_type'] ?? 'string',
                    'setting_group' => $definition['group'] ?? 'General',
                    'label' => $definition['label'] ?? $key,
                    'description' => $definition['description'] ?? null,
                ]
            );

            $setting->forceFill([
                'value_type' => $definition['value_type'] ?? 'string',
                'setting_group' => $definition['group'] ?? 'General',
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? null,
            ])->save();
        }

        static::flush();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! static::tableReady()) {
            return $default;
        }

        $setting = static::settings()->get($key);

        return $setting?->setting_value ?? $default;
    }

    public static function grouped(): Collection
    {
        static::seedDefaults();

        if (! static::tableReady()) {
            return collect();
        }

        return static::settings()
            ->sortBy(fn (SlsSetting $setting) => array_search($setting->setting_key, array_keys(static::definitions()), true))
            ->groupBy('setting_group');
    }

    public static function flush(): void
    {
        static::$settings = null;
        static::$tableReady = null;
    }

    protected static function settings(): Collection
    {
        if (static::$settings !== null) {
            return static::$settings;
        }

        static::$settings = SlsSetting::query()
            ->get()
            ->keyBy('setting_key');

        return static::$settings;
    }

    protected static function tableReady(): bool
    {
        if (static::$tableReady !== null) {
            return static::$tableReady;
        }

        try {
            static::$tableReady = Schema::hasTable('sls_settings');
        } catch (\Throwable) {
            static::$tableReady = false;
        }

        return static::$tableReady;
    }
}
