<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlockController extends Controller
{
    use InteractsWithOrganization;

    public function __invoke(Request $request, QrCodeService $qrCodes): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:16', 'max:512'],
        ], [
            'code.required' => 'Karekod gerekli.',
            'code.min' => 'Karekod çok kısa.',
        ]);

        $board = $qrCodes->redeem($data['code'], $this->organizationId($request));

        return response()->json([
            'message' => 'Açma izni yazıldı.',
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'device_code' => $board->device_code,
            ],
        ]);
    }
}
