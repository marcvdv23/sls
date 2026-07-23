<?php

namespace App\Services;

use App\Models\AccessAuditLog;
use App\Models\Country;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AccessControlService
{
    public const ACTIONS = [
        'view' => 'can_view',
        'search' => 'can_search',
        'insert' => 'can_insert',
        'update' => 'can_update',
        'delete' => 'can_delete',
        'approve' => 'can_approve',
        'print' => 'can_print',
        'export' => 'can_export',
        'import' => 'can_import',
        'run_process' => 'can_run_process',
        'assign' => 'can_assign',
        'configure' => 'can_configure',
    ];

    public function can(?User $user, string $formKey, string $action = 'view'): bool
    {
        if (! $user) {
            return true;
        }

        if (! $user->is_active) {
            return false;
        }

        $group = $user->group()->with('permissions')->first();

        if (! $group) {
            return false;
        }

        if ($group->is_admin) {
            return true;
        }

        $column = self::ACTIONS[$action] ?? null;

        if (! $column) {
            return false;
        }

        $permission = $group->permissions->firstWhere('form_key', $formKey);

        return (bool) ($permission?->{$column});
    }

    public function canAccessCountry(?User $user, Country|string|null $country): bool
    {
        if (! $user || ! $country) {
            return true;
        }

        $group = $user->group()->with('countryAccess.country')->first();

        if (! $group) {
            return false;
        }

        if ($group->is_admin) {
            return true;
        }

        $countryModel = $country instanceof Country
            ? $country
            : Country::query()->where('iso_code', $country)->orWhere('name', $country)->first();

        if (! $countryModel) {
            return false;
        }

        $rules = $group->countryAccess;

        if ($rules->isEmpty()) {
            return true;
        }

        return $rules->contains(function ($rule) use ($countryModel) {
            return $rule->can_access && (
                (filled($rule->country_id) && (int) $rule->country_id === (int) $countryModel->id)
                || (filled($rule->region) && Str::lower($rule->region) === Str::lower((string) $countryModel->region))
            );
        });
    }

    public function canAccessProduct(?User $user, Product|string|null $product): bool
    {
        if (! $user || ! $product) {
            return true;
        }

        $group = $user->group()->with('productAccess.product')->first();

        if (! $group) {
            return false;
        }

        if ($group->is_admin) {
            return true;
        }

        $productName = $product instanceof Product ? $product->name : (string) $product;
        $productId = $product instanceof Product ? $product->id : null;
        $rules = $group->productAccess;

        if ($rules->isEmpty()) {
            return true;
        }

        return $rules->contains(function ($rule) use ($productName, $productId) {
            return $rule->can_access && (
                (filled($productId) && filled($rule->product_id) && (int) $rule->product_id === (int) $productId)
                || (filled($rule->product_key) && Str::contains(Str::lower($productName), Str::lower($rule->product_key)))
                || ($rule->product && Str::lower($rule->product->name) === Str::lower($productName))
            );
        });
    }

    public function scopeCountries(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->group?->is_admin) {
            return $query;
        }

        $group = $user->group()->with('countryAccess')->first();

        if (! $group || $group->countryAccess->isEmpty()) {
            return $query;
        }

        $countryIds = $group->countryAccess->where('can_access', true)->pluck('country_id')->filter()->values();
        $regions = $group->countryAccess->where('can_access', true)->pluck('region')->filter()->values();

        return $query->where(function (Builder $inner) use ($countryIds, $regions) {
            if ($countryIds->isNotEmpty()) {
                $inner->orWhereIn('id', $countryIds);
            }

            if ($regions->isNotEmpty()) {
                $inner->orWhereIn('region', $regions);
            }
        });
    }

    public function log(string $action, ?string $formKey = null, mixed $model = null, array $metadata = [], ?Request $request = null): void
    {
        $request ??= request();

        AccessAuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'form_key' => $formKey,
            'model_type' => is_object($model) ? $model::class : null,
            'model_id' => is_object($model) && isset($model->id) ? $model->id : null,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
