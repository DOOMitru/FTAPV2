<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only access.
 *
 * Depends on `auth` running first, and must be listed after it. On its own it
 * still refuses a guest -- $request->user() is null, so the null-safe read is
 * null and abort_unless fires -- but it refuses with 403, where a guest should
 * get 302 to the login screen. The difference is invisible in a passing test
 * and obvious to somebody locked out of a page they could reach by signing in.
 */
class EnsureUserIsAdmin
{
    /**
     * Allow the request through only for authenticated league administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
