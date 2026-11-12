<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_code',
        'name',
        'description',
        'client_name',
        'client_email',
        'client_phone',
        'client_company',
        'client_gstin',
        'client_address',
        'department_id',
        'project_manager_id',
        'created_by_user_id',
        'status',
        'priority',
        'billing_type',
        'currency',
        'estimated_budget',
        'actual_cost',
        'progress_percentage',
        'start_date',
        'deadline',
        'completed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_budget' => 'float',
            'actual_cost' => 'float',
            'progress_percentage' => 'integer',
            'start_date' => 'date:Y-m-d',
            'deadline' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Generate sequential project code.
     */
    public static function generateCode(): string
    {
        $year = date('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('PRJ-%s-%03d', $year, $count);
    }
}
