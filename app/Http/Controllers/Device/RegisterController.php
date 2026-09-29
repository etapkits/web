<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\DeviceRegistrar;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __invoke(Request $request, DeviceRegistrar $registrar): JsonResponse
    {
        $data = $request->validate([
            'enrollment_key' => ['required', 'string'],
            'machine_id' => ['required', 'string', 'min:4', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'hostname' => ['nullable', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/()]*$/u'],
        ], [
            'enrollment_key.required' => 'Kayıt anahtarı gerekli.',
            'machine_id.required' => 'Makine kimliği gerekli.',
            'machine_id.regex' => 'Makine kimliği geçersiz.',
            'machine_id.min' => 'Makine kimliği geçersiz.',
            'name.max' => 'Tahta adı en fazla 80 karakter olabilir.',
            'name.regex' => 'Tahta adı geçersiz.',
        ]);

        $given = (string) $data['enrollment_key'];
        $organization = Organization::query()->where('enrollment_key', $given)->first();

        if (! $organization || ! hash_equals($organization->enrollment_key, $given)) {
            return response()->json(['message' => 'Kayıt anahtarı geçersiz.'], 401);
        }

        if (! $organization->is_active) {
            return response()->json(['message' => 'Kurum pasif.'], 403);
        }

        $hostname = trim((string) ($data['hostname'] ?? ''));
        $name = DeviceRegistrar::normalizeName($data['name'] ?? null);

        try {
            $result = $registrar->register(
                $data['machine_id'],
                $hostname === '' ? null : $hostname,
                $organization,
                $name,
            );
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'device_code')) {
                return response()->json(['message' => 'Tahta kodu üretilemedi.'], 409);
            }

            throw $exception;
        }

        $board = $result['board'];

        return response()->json([
            'board_id' => $board->id,
            'device_token' => $result['token'],
            'device_code' => $board->device_code,
            'approval' => $board->approval->value,
            'name' => $board->name,
        ], $result['created'] ? 201 : 200);
    }
}
