<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'official_email',
        'personal_email',
        'phone_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'avatar_url',
        'date_of_birth',
        'gender',
        'date_of_joining',
        'date_of_exit',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'employment_type',
        'employment_status',
        'current_address',
        'permanent_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'date_of_exit' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function bankDetails(): HasOne
    {
        return $this->hasOne(EmployeeBankDetail::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function managedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
