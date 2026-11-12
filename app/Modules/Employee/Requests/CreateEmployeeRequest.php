<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.create') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'official_email' => ['required', 'email', 'max:255', 'unique:employees,official_email', 'unique:users,email'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'avatar_url' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'date_of_joining' => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'employment_type' => ['nullable', 'string', 'in:full_time,part_time,contract,intern'],
            'employment_status' => ['nullable', 'string', 'in:active,inactive,probation'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'create_user_account' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
