<?php

namespace App\Modules\Leave\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:90'],
            'half_day_type' => ['nullable', 'string', 'in:none,first_half,second_half'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'acting_delegate_id' => ['nullable', 'integer', 'exists:users,id'],
            'auto_approve' => ['nullable', 'boolean'],
        ];
    }
}
