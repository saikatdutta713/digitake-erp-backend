<?php

namespace App\Http\Resources;

use App\Models\EmployeeBankDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeBankDetail
 */
class EmployeeBankDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'routing_code' => $this->routing_code,
            'branch_name' => $this->branch_name,
            'account_type' => $this->account_type,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
