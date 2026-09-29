<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $user->organization?->name,
            'official_code' => $user->organization?->official_code,
        ]);
    }

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

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'E-posta veya parola hatalı.',
            ]);
        }

        if (! Auth::guard('web')->user()->organization?->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Kurum pasif. Etakit yönetimiyle görüşün.',
            ]);
        }

        Auth::guard('teacher')->logout();
        Auth::guard('super')->logout();
        Auth::shouldUse('web');
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $user->organization?->name,
            'official_code' => $user->organization?->official_code,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
