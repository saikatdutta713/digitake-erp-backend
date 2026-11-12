<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_system_role',
        'role_type',
        'department_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_system_role' => 'boolean',
        ]);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
