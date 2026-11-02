<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.update')
            || $this->user()?->hasRole('Finance Manager')
            || $this->user()?->hasRole('HR Manager');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'routing_code' => ['required', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'in:savings,checking,current,salary'],
        ];
    }
}
