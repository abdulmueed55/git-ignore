<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin,principal'). Admins always pass.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Your account is not active.');
        }

        if ($user->role !== 'admin' && ! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
