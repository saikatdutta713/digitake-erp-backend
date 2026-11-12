<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'days_per_year',
        'is_paid',
        'requires_approval',
        'allow_half_day',
        'carry_forward_days',
        'applicable_gender',
        'color',
        'description',
        'is_active',
        'created_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_per_year' => 'float',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_half_day' => 'boolean',
            'carry_forward_days' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'leave_type', 'code');
    }

    /**
     * Scope a query to only include active leave types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Default enterprise leave structure specifications.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultTypes(): array
    {
        return [
            [
                'name' => 'Casual Leave',
                'code' => 'casual',
                'days_per_year' => 12.0,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => true,
                'carry_forward_days' => 3.0,
                'applicable_gender' => 'all',
                'color' => '#10b981', // Emerald
                'description' => 'For short urgent personal matters and planned short breaks.',
                'is_active' => true,
            ],
            [
                'name' => 'Sick & Medical Leave',
                'code' => 'medical',
                'days_per_year' => 10.0,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => true,
                'carry_forward_days' => 5.0,
                'applicable_gender' => 'all',
                'color' => '#ef4444', // Rose
                'description' => 'For health issues, illness, dental, or surgical recovery.',
                'is_active' => true,
            ],
            [
                'name' => 'Earned / Annual Leave',
                'code' => 'annual',
                'days_per_year' => 18.0,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => true,
                'carry_forward_days' => 10.0,
                'applicable_gender' => 'all',
                'color' => '#3b82f6', // Sapphire
                'description' => 'Accrued annual vacation and personal rejuvenation leave.',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'maternity',
                'days_per_year' => 90.0,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => false,
                'carry_forward_days' => 0.0,
                'applicable_gender' => 'female',
                'color' => '#ec4899', // Pink
                'description' => 'Statutory maternity leave for expecting and new mothers.',
                'is_active' => true,
            ],
            [
                'name' => 'Paternity Leave',
                'code' => 'paternity',
                'days_per_year' => 10.0,
                'is_paid' => true,
                'requires_approval' => true,
                'allow_half_day' => false,
                'carry_forward_days' => 0.0,
                'applicable_gender' => 'male',
                'color' => '#6366f1', // Indigo
                'description' => 'Paternity leave for new fathers upon childbirth or adoption.',
                'is_active' => true,
            ],
            [
                'name' => 'Bereavement Leave',
                'code' => 'bereavement',
                'days_per_year' => 5.0,
                'is_paid' => true,
                'requires_approval' => false,
                'allow_half_day' => true,
                'carry_forward_days' => 0.0,
                'applicable_gender' => 'all',
                'color' => '#64748b', // Slate
                'description' => 'Compassionate leave granted upon loss of immediate family.',
                'is_active' => true,
            ],
            [
                'name' => 'Unpaid / Loss of Pay',
                'code' => 'unpaid',
                'days_per_year' => 30.0,
                'is_paid' => false,
                'requires_approval' => true,
                'allow_half_day' => true,
                'carry_forward_days' => 0.0,
                'applicable_gender' => 'all',
                'color' => '#f59e0b', // Amber
                'description' => 'Approved absence without compensation when quotas are exhausted.',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Ensure standard leave types are seeded into the database.
     */
    public static function ensureDefaultTypesExist(): void
    {
        if (static::count() > 0) {
            return;
        }

        foreach (static::defaultTypes() as $spec) {
            static::create($spec);
        }
    }
}
