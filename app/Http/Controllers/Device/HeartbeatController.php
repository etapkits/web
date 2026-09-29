<?php

namespace App\Http\Controllers\Device;

use App\Enums\BoardState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeartbeatController extends DeviceController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', Rule::enum(BoardState::class)],
        ], [
            'state.required' => 'Durum gerekli.',
        ]);

        $board = $this->board($request);
        $board->markSeen(BoardState::from($data['state']));

        $board->refresh();

        return response()->json([
            'ok' => true,
            'approval' => $board->approval->value,
            'device_code' => $board->device_code,
            'name' => $board->name,
            'server_time' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }
}
