<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSlsLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('sls') && ! $request->is('sls/*')) {
            return $next($request);
        }

        if ($request->is('sls/login')) {
            return $next($request);
        }

        if (! auth()->check()) {
            return redirect()->route('sls.login');
        }

        if (! auth()->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('sls.login')->withErrors([
                'email' => 'This SLS account is inactive.',
            ]);
        }

        if ($this->isSourceMaintenanceOnlyUser(auth()->user())) {
            if ($request->is('sls')) {
                return redirect()->route('sls.sourceMaintenance.index');
            }

            if (! $request->is('sls/source-maintenance', 'sls/source-maintenance/*', 'sls/tracked-countries/admin-urls', 'sls/tracked-countries/admin-urls/*', 'sls/workspaces/current', 'sls/logout')) {
                abort(403);
            }
        }

        return $next($request);
    }

    private function isSourceMaintenanceOnlyUser($user): bool
    {
        $group = $user?->group()->with('permissions')->first();

        if (! $group || $group->is_admin) {
            return false;
        }

        $sourcePermission = $group->permissions->firstWhere('form_key', 'source_maintenance');

        if (! $sourcePermission || (! $sourcePermission->can_view && ! $sourcePermission->can_update)) {
            return false;
        }

        return ! $group->permissions
            ->reject(fn ($permission) => $permission->form_key === 'source_maintenance')
            ->contains(function ($permission): bool {
                foreach ([
                    'can_view',
                    'can_search',
                    'can_insert',
                    'can_update',
                    'can_delete',
                    'can_approve',
                    'can_print',
                    'can_export',
                    'can_import',
                    'can_run_process',
                    'can_assign',
                    'can_configure',
                ] as $column) {
                    if ((bool) $permission->{$column}) {
                        return true;
                    }
                }

                return false;
            });
    }
}
