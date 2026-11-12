<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_id',
        'date',
        'clock_in_at',
        'clock_out_at',
        'total_duration_minutes',
        'active_minutes',
        'idle_minutes',
        'break_minutes',
        'productivity_score',
        'modality',
        'status',
        'device_uuid',
        'ip_address',
        'remarks',
        'is_manual_override',
        'override_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'total_duration_minutes' => 'integer',
            'active_minutes' => 'integer',
            'idle_minutes' => 'integer',
            'break_minutes' => 'integer',
            'productivity_score' => 'float',
            'is_manual_override' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AttendanceActivity::class);
    }

    /**
     * Recalculate duration, active/idle/break metrics, and productivity score.
     */
    public function recalculateMetrics(): void
    {
        $activities = $this->activities;
        if ($activities->isEmpty()) {
            return;
        }

        $totalActiveSec = $activities->sum('active_seconds');
        $totalIdleSec = $activities->sum('idle_seconds');
        $totalBreakSec = $activities->sum('break_seconds');

        $this->active_minutes = (int) round($totalActiveSec / 60);
        $this->idle_minutes = (int) round($totalIdleSec / 60);
        $this->break_minutes = (int) round($totalBreakSec / 60);

        $workSeconds = $totalActiveSec + $totalIdleSec;
        $totalSec = $workSeconds + $totalBreakSec;
        $this->total_duration_minutes = (int) round($totalSec / 60);

        if ($workSeconds > 0) {
            $this->productivity_score = round(($totalActiveSec / $workSeconds) * 100, 2);
        } else {
            $this->productivity_score = 100.00;
        }

        $this->save();
    }
}
