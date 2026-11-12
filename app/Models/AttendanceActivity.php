<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceActivity extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attendance_id',
        'user_id',
        'interval_start',
        'interval_end',
        'active_seconds',
        'idle_seconds',
        'break_seconds',
        'state',
        'break_type',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_start' => 'datetime',
            'interval_end' => 'datetime',
            'active_seconds' => 'integer',
            'idle_seconds' => 'integer',
            'break_seconds' => 'integer',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
