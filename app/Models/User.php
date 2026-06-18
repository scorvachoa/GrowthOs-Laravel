<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;

use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vacations()
    {
        return $this->hasMany(Vacation::class);
    }

    public function timeOffs()
    {
        return $this->hasMany(TimeOff::class);
    }

    public function activeOrganizationId(): ?int
    {
        if ($this->hasRole('Super Admin')) {
            return session('active_company_id', $this->organization_id);
        }
        return $this->organization_id;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public static function defaultSettings(): array
    {
        return [
            'use_blocks' => true,
            'block_hours' => 2,
            'show_youtube_chart' => true,
            'default_work_start' => '09:00',
            'default_work_end' => '18:00',
            'lunch_start' => '13:00',
            'lunch_end' => '14:00',
            'working_days' => [1, 2, 3, 4, 5],
            'max_tasks_per_block' => 1,
            'default_report_scope' => 'mensual',
            'dashboard_default_view' => 'month',
            'youtube_max_recent_videos' => 10,
            'app_locale' => 'es',
            'timezone' => 'America/Lima',
        ];
    }

    public function getMergedSettingsAttribute(): array
    {
        return array_merge(static::defaultSettings(), $this->settings ?? []);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'email',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
