<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleHasPageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName || ! str_starts_with($routeName, 'filament.')) {
            return $next($request);
        }

        if (str_contains($routeName, '.auth.') || str_contains($routeName, '.password-reset.')) {
            return $next($request);
        }

        if (RoleAccess::canAccessRoute($request->user(), $routeName)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
