<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'standard_start_time',
        'standard_end_time',
        'late_grace_minutes',
        'half_day_threshold_minutes',
        'full_day_threshold_minutes',
        'idle_timeout_seconds',
        'allowed_modalities',
        'office_ips',
        'auto_clock_out_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'late_grace_minutes' => 'integer',
            'half_day_threshold_minutes' => 'integer',
            'full_day_threshold_minutes' => 'integer',
            'idle_timeout_seconds' => 'integer',
            'allowed_modalities' => 'array',
            'office_ips' => 'array',
        ];
    }

    /**
     * Get or create the global singleton attendance policy.
     */
    public static function getActivePolicy(): self
    {
        return static::firstOrCreate([], [
            'standard_start_time' => '09:30',
            'standard_end_time' => '18:30',
            'late_grace_minutes' => 15,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 480,
            'idle_timeout_seconds' => 180,
            'allowed_modalities' => ['office', 'wfh', 'hybrid'],
            'office_ips' => [],
            'auto_clock_out_at' => '22:00',
        ]);
    }
}
