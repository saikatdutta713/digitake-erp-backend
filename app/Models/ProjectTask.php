<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'milestone_id',
        'assigned_to_user_id',
        'title',
        'description',
        'priority',
        'status',
        'estimated_hours',
        'actual_hours',
        'due_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_hours' => 'float',
            'actual_hours' => 'float',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
