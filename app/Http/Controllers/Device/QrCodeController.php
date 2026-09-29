<?php

namespace App\Http\Controllers\Device;

use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrCodeController extends DeviceController
{
    public function __invoke(Request $request, QrCodeService $qrCodes): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:16', 'max:512', 'regex:/^[A-Za-z0-9:._~-]+$/'],
        ], [
            'code.required' => 'Karekod gerekli.',
            'code.min' => 'Karekod çok kısa.',
            'code.regex' => 'Karekod yalnızca harf, rakam ve : . _ ~ - içerebilir.',
        ]);

        $board = $this->board($request);
        $board->markSeen();
        $qr = $qrCodes->report($board, $data['code']);

        return response()->json([
            'ok' => true,
            'expires_at' => $qr->expires_at->toIso8601String(),
            'ttl_seconds' => (int) config('etakit.qr_ttl_seconds'),
        ]);
    }
}
