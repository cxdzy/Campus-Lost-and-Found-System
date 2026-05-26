<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     * Accepts a comma-separated list of allowed roles, e.g. "Admin,Security".
     */
    public function handle(Request $request, Closure $next, $roles = null)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $roles) {
            return $next($request);
        }

        $allowed = array_map('trim', explode(',', $roles));

        if (! in_array($user->role, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
