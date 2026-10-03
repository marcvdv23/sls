<?php

namespace App\Support;

use App\Models\ReviewFocus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReviewFocuses
{
    protected static ?bool $tableReady = null;

    public static function configDefaults(): array
    {
        return config('country_intelligence.focuses', []);
    }

    public static function seedDefaults(): void
    {
        if (! static::tableReady()) {
            return;
        }

        $workspaceKey = (string) (WorkspaceContext::current()?->workspace_key ?? 'social_security');

        if ($workspaceKey !== 'social_security') {
            return;
        }

        $sortOrder = 10;

        foreach (static::configDefaults() as $key => $definition) {
            $knownKeys = ['label', 'description', 'terms', 'strong_signals'];
            $metadata = collect($definition)
                ->except($knownKeys)
                ->all();

            $focus = ReviewFocus::query()->firstOrCreate(
                ['focus_key' => $key],
                [
                    'label' => $definition['label'] ?? (string) str($key)->replace('_', ' ')->title(),
                    'description' => $definition['description'] ?? null,
                    'terms' => array_values($definition['terms'] ?? []),
                    'strong_signals' => array_values($definition['strong_signals'] ?? []),
                    'metadata' => $metadata,
                    'sort_order' => $sortOrder,
                    'is_enabled' => true,
                    'is_default' => $key === 'social_security',
                ]
            );

            $updates = [
                'metadata' => array_replace($metadata, $focus->metadata ?? []),
            ];

            if (blank($focus->label)) {
                $updates['label'] = $definition['label'] ?? (string) str($key)->replace('_', ' ')->title();
            }

            if (blank($focus->description) && filled($definition['description'] ?? null)) {
                $updates['description'] = $definition['description'];
            }

            if (empty($focus->terms) && ! empty($definition['terms'])) {
                $updates['terms'] = array_values($definition['terms']);
            }

            if (empty($focus->strong_signals) && ! empty($definition['strong_signals'])) {
                $updates['strong_signals'] = array_values($definition['strong_signals']);
            }

            if (empty($focus->sort_order)) {
                $updates['sort_order'] = $sortOrder;
            }

            $focus->forceFill($updates)->save();
            $sortOrder += 10;
        }
    }

    public static function all(bool $includeDisabled = false): array
    {
        if (! static::tableReady()) {
            return static::configDefaults();
        }

        static::seedDefaults();

        $query = ReviewFocus::query();

        if (! $includeDisabled) {
            $query->where('is_enabled', true);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->mapWithKeys(fn (ReviewFocus $focus) => [$focus->focus_key => static::toConfig($focus)])
            ->all();
    }

    public static function get(string $key, bool $includeDisabled = false): ?array
    {
        return static::all($includeDisabled)[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::all());
    }

    public static function groupedForSettings(): Collection
    {
        if (! static::tableReady()) {
            return collect();
        }

        static::seedDefaults();

        return ReviewFocus::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public static function defaultKey(): string
    {
        if (! static::tableReady()) {
            return array_key_first(static::configDefaults()) ?: 'social_security';
        }

        static::seedDefaults();

        return ReviewFocus::query()
            ->where('is_enabled', true)
            ->where('is_default', true)
            ->value('focus_key')
            ?: (ReviewFocus::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->value('focus_key') ?: 'social_security');
    }

    public static function tableReady(): bool
    {
        if (static::$tableReady !== null) {
            return static::$tableReady;
        }

        try {
            static::$tableReady = Schema::hasTable('review_focuses');
        } catch (\Throwable) {
            static::$tableReady = false;
        }

        return static::$tableReady;
    }

    public static function flush(): void
    {
        static::$tableReady = null;
    }

    protected static function toConfig(ReviewFocus $focus): array
    {
        $metadata = is_array($focus->metadata) ? $focus->metadata : [];

        return array_replace($metadata, [
            'label' => $focus->label,
            'description' => $focus->description,
            'terms' => array_values($focus->terms ?? []),
            'strong_signals' => array_values($focus->strong_signals ?? []),
        ]);
    }
}
