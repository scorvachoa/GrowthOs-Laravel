<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\SharedWithUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VideoTask extends Model
{
    use BelongsToOrganization, HasFactory, LogsActivity, SharedWithUser, SoftDeletes;

    protected $fillable = [
        'task_date',
        'time_range',
        'title',
        'script',
        'copy',
        'notes',
        'youtube_url',
        'status',
        'created_by',
        'channel_id',
        'locale',
        'translation_group_id',
        'translated_from_task_id',
        'key_phrases',
        'is_pending',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'key_phrases' => 'array',
            'is_pending' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'script', 'copy', 'notes', 'key_phrases', 'status', 'time_range', 'task_date', 'channel_id', 'locale', 'youtube_url'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Tareas vencidas: fecha pasada, sin publicar/cancelar y cuya sesión
     * más reciente (por fecha, luego por id) NO está completada.
     * Sin sesiones también cuenta como vencida.
     */
    public function scopeOverdue($query)
    {
        $query->whereNotIn('status', ['published', 'cancelled'])
            ->where('task_date', '<', Carbon::today())
            ->where(function ($q) {
                $q->whereDoesntHave('sessions', fn ($sessions) => $sessions->withoutGlobalScope('organization'))
                    ->orWhereExists(function ($keep) {
                        $keep->from('work_sessions as ws_keep')
                            ->whereColumn('ws_keep.video_task_id', 'video_tasks.id')
                            ->where('ws_keep.status', '!=', 'completed')
                            ->whereNotExists(function ($newer) {
                                $newer->from('work_sessions as ws_newer')
                                    ->whereColumn('ws_newer.video_task_id', 'ws_keep.video_task_id')
                                    ->where(function ($w) {
                                        $w->whereColumn('ws_newer.date', '>', 'ws_keep.date')
                                            ->orWhere(function ($tie) {
                                                $tie->whereColumn('ws_newer.date', 'ws_keep.date')
                                                    ->whereColumn('ws_newer.id', '>', 'ws_keep.id');
                                            });
                                    });
                            });
                    });
            });
    }

    public function sessions()
    {
        return $this->hasMany(WorkSession::class);
    }
}
