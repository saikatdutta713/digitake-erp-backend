<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'employee_status_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'changed_by',
        'old_status',
        'new_status',
        'effective_date',
        'reason',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
