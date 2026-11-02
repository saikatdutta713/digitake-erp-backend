<?php

namespace App\Modules\Employee\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employee?->id])],
            'employment_type' => ['sometimes', 'string', 'in:full_time,part_time,contract,intern'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
        ];
    }
}
