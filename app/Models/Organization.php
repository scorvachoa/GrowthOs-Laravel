<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'name',
        'logo_path',
        'primary_color',
        'status',
        'plan',
        'trial_ends_at',
        'admin_invite_code',
        'invite_code',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Activa',
            self::STATUS_TRIAL => 'Trial',
            self::STATUS_SUSPENDED => 'Suspendida',
            self::STATUS_EXPIRED => 'Expirada',
        ];
    }

    public function statusLabel(): string
    {
        return $this->statusLabels()[$this->status] ?? $this->status;
    }

    /**
     * Trial vencido se trata como expirado en-request (sin cron).
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_TRIAL && $this->trial_ends_at?->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    public function isAccessBlocked(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_SUSPENDED, self::STATUS_EXPIRED], true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }
}
