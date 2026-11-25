<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanEdit
{
    /**
     * Viewers can look at and print everything, but any change is refused.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $request->user()?->canEdit()) {
            abort(403, __('Your role on this team is view-only.'));
        }

        return $next($request);
    }
}
