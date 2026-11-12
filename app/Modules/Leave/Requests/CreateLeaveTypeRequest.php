<?php

namespace App\Modules\Leave\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isSuperAdmin() || $user->can('leaves.manage'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9_-]+$/', 'unique:leave_types,code'],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'allow_half_day' => ['nullable', 'boolean'],
            'carry_forward_days' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'applicable_gender' => ['nullable', 'string', 'in:all,male,female'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
