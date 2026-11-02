<?php

namespace App\Modules\Department\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? ($this->user()?->can('departments.create') ?? false)
            : ($this->user()?->can('departments.update') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->ignore($department?->id),
            ],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
