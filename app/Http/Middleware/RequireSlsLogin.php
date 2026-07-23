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

        return $next($request);
    }
}
