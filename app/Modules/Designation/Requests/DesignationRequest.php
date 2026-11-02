<?php

namespace App\Modules\Designation\Requests;

use App\Models\Designation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? ($this->user()?->can('departments.create') || $this->user()?->can('departments.manage'))
            : ($this->user()?->can('departments.update') || $this->user()?->can('departments.manage'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Designation|null $designation */
        $designation = $this->route('designation');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('designations', 'code')->ignore($designation?->id),
            ],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
