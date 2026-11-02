<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_uuid',
        'device_name',
        'device_type',
        'platform',
        'os_version',
        'app_version',
        'status',
        'registered_by',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function registeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function isShared(): bool
    {
        return $this->device_type === 'shared';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
