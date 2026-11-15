<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.archive') || $this->user()?->can('employees.update');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:active,inactive,resigned,terminated,probation,archived,on_leave,suspended'],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
