<?php

namespace App\Http\Middleware;

use App\Models\Board;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();

        if ($token === '') {
            return response()->json(['message' => 'Tahta kimliği gerekli.'], 401);
        }

        $board = Board::query()->where('token_hash', hash('sha256', $token))->first();

        if (! $board) {
            return response()->json(['message' => 'Tahta kimliği geçersiz.'], 401);
        }

        $request->attributes->set('board', $board);

        return $next($request);
    }
}
