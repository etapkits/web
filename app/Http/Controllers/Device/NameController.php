<?php

namespace App\Http\Controllers\Device;

use App\Services\DeviceRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NameController extends DeviceController
{
    public function __invoke(Request $request, DeviceRegistrar $registrar): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/()]*$/u'],
        ], [
            'name.required' => 'Tahta adı gerekli.',
            'name.max' => 'Tahta adı en fazla 80 karakter olabilir.',
            'name.regex' => 'Tahta adı geçersiz.',
        ]);

        $board = $this->board($request);
        $registrar->rename($board, $data['name']);

        return response()->json([
            'ok' => true,
            'board_id' => $board->id,
            'name' => $board->name,
            'device_code' => $board->device_code,
            'approval' => $board->approval->value,
        ]);
    }
}
