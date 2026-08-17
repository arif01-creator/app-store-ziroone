<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends ApiController
{
    /**
     * Register or check in a device.
     *
     * The Flutter client calls this on every launch, not just first install —
     * it is how `last_seen_at`, the installed version, and a rotated FCM token
     * all stay current.
     */
    public function register(RegisterDeviceRequest $request, string $apiKey): JsonResponse
    {
        $app = $this->resolveApp($apiKey);
        $data = $request->validated();
        $now = now();

        $device = Device::firstOrNew([
            'app_id' => $app->id,
            'install_uuid' => $data['install_uuid'],
        ]);

        $isNew = ! $device->exists;

        $device->fill([
            'fcm_token' => $data['fcm_token'] ?? null,
            'device_model' => $data['device_model'] ?? null,
            'android_version' => $data['android_version'] ?? null,
            'current_version_code' => $data['version_code'],
            'current_version_name' => $data['version_name'] ?? null,
            'last_seen_at' => $now,
        ]);

        $device->first_seen_at ??= $now;
        // A device that checks in has un-retired itself.
        $device->is_active = true;

        $device->save();

        $latest = $app->latestActiveVersion()->first();

        return response()->json([
            'registered' => true,
            'is_new_device' => $isNew,
            'update_available' => $latest !== null
                && $latest->version_code > (int) $data['version_code'],
            'latest' => $latest === null ? null : [
                'version_name' => $latest->version_name,
                'version_code' => $latest->version_code,
                'is_force_update' => $latest->is_force_update,
            ],
        ], $isNew ? 201 : 200);
    }
}
