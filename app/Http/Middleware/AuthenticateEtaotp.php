<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEtaotp
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.otp.token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Yetkisiz.'], 401);
        }

        return $next($request);
    }
}
