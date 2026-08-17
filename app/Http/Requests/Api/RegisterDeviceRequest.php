<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'install_uuid' => ['required', 'string', 'max:64'],
            // FCM registration tokens are long and have no fixed length.
            'fcm_token' => ['nullable', 'string', 'max:4096'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'android_version' => ['nullable', 'string', 'max:50'],
            'version_code' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'version_name' => ['nullable', 'string', 'max:50'],
        ];
    }
}
