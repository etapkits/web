<?php

namespace App\Http\Controllers\Device;

use App\Models\LockSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends DeviceController
{
    public function __invoke(Request $request): JsonResponse
    {
        $setting = LockSetting::forOrganization((int) $this->board($request)->organization_id);

        if (! $setting) {
            return response()->json([
                'configured' => false,
            ])->header('Cache-Control', 'no-store');
        }

        return response()->json($setting->toDeviceArray())->header('Cache-Control', 'no-store');
    }
}
