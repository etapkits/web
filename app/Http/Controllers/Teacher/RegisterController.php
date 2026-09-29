<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Teacher;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('teacher.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'official_code' => ['required', 'regex:/^\d{4,12}$/'],
            'first_name' => ['required', 'string', 'max:40', 'regex:/^[\p{L}][\p{L} .\'-]*$/u'],
            'last_name' => ['required', 'string', 'max:40', 'regex:/^[\p{L}][\p{L} .\'-]*$/u'],
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'official_code.required' => 'Kurum kodu gerekli.',
            'official_code.regex' => 'Kurum kodu 4-12 haneli olmalı.',
            'first_name.required' => 'Ad gerekli.',
            'first_name.regex' => 'Ad geçersiz.',
            'last_name.required' => 'Soyad gerekli.',
            'last_name.regex' => 'Soyad geçersiz.',
            'phone.required' => 'Telefon gerekli.',
        ]);

        $organization = Organization::query()->where('official_code', $data['official_code'])->first();

        if (! $organization) {
            return back()->withErrors(['official_code' => 'Kurum kodu bulunamadı.'])->withInput();
        }

        if (! $organization->is_active) {
            return back()->withErrors(['official_code' => 'Kurum pasif. Kayıt alınmıyor.'])->withInput();
        }

        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            return back()->withErrors(['phone' => 'Telefon numarası geçerli değil.'])->withInput();
        }

        if (Teacher::query()->where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'Bu telefon kayıtlı.'])->withInput();
        }

        Teacher::query()->create([
            'organization_id' => $organization->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $phone,
            'approved_at' => null,
        ]);

        return redirect()
            ->route('home')
            ->with('status', 'Kayıt alındı. İdare onayından sonra telefonunuzla giriş yapabilirsiniz.');
    }
}
