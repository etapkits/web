<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\OtpMessage;
use App\Models\Teacher;
use App\Models\TeacherOtp;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class SessionController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {

        $teacher = $this->approvedTeacher($request);
        $code = (string) random_int(100000, 999999);

        TeacherOtp::query()
            ->where('teacher_id', $teacher->id)
            ->whereNull('consumed_at')
            ->delete();

        TeacherOtp::query()->create([
            'teacher_id' => $teacher->id,
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            OtpMessage::queue($teacher->phone, $code.' Etakit giris kodunuzdur.', OtpMessage::TYPE_LOGIN);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'phone' => 'Kod sıraya alınamadı. Biraz sonra yeniden deneyin.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Kod gönderildi.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'phone.required' => 'Telefon gerekli.',
            'code.required' => 'Kod gerekli.',
            'code.digits' => 'Kod 6 haneli olmalı.',
        ]);

        $teacher = $this->approvedTeacher($request);
        $otp = TeacherOtp::query()
            ->where('teacher_id', $teacher->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => 'Kod hatalı veya süresi doldu.',
            ]);
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, hash('sha256', $data['code']))) {
            throw ValidationException::withMessages([
                'code' => 'Kod hatalı veya süresi doldu.',
            ]);
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        Auth::guard('web')->logout();
        Auth::guard('super')->logout();
        Auth::guard('teacher')->login($teacher, $request->boolean('remember'));
        Auth::shouldUse('teacher');
        $request->session()->regenerate();

        return response()->json([
            'name' => $teacher->name,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        // Hatırlama çerezi kalır; "Beni hatırla" seçildiyse sonraki girişte kod sorulmaz.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function forget(Request $request): JsonResponse
    {
        Auth::guard('teacher')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function approvedTeacher(Request $request): Teacher
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'phone.required' => 'Telefon gerekli.',
        ]);

        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'Telefon numarası geçerli değil.',
            ]);
        }

        $teacher = Teacher::query()->where('phone', $phone)->first();

        if (! $teacher) {
            throw ValidationException::withMessages([
                'phone' => 'Bu telefon kayıtlı değil.',
            ]);
        }

        if (! $teacher->organization?->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'Kurum pasif. Okul idaresiyle görüşün.',
            ]);
        }

        if (! $teacher->isApproved()) {
            throw ValidationException::withMessages([
                'phone' => 'İdare onayı bekleniyor.',
            ]);
        }

        return $teacher;
    }
}
