<?php

namespace App\Modules\DeviceManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'max:100'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:personal,shared'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'app_version' => ['required', 'string', 'max:50'],
        ];
    }
}
