<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EndInactiveOrganizationSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['web', 'teacher'] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user && $user->organization && ! $user->organization->is_active) {
                Auth::guard($guard)->logout();
            }
        }

        return $next($request);
    }
}
