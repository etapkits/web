<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'E-posta gerekli.',
            'email.email' => 'E-posta geçerli değil.',
            'password.required' => 'Parola gerekli.',
        ]);

        if (! Auth::guard('super')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'E-posta veya parola hatalı.',
            ]);
        }

        Auth::guard('web')->logout();
        Auth::guard('teacher')->logout();
        Auth::shouldUse('super');
        $request->session()->regenerate();

        $user = Auth::guard('super')->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('super')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
