<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GeneratedVideo extends Model
{
    use LogsActivity;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    use BelongsToOrganization, OwnedByUser;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['organization_id']);
    }

    protected $fillable = [
        'status',
        'idea',
        'script',
        'copy_title',
        'copy_description',
        'copy_cta',
        'copy_hashtags',
        'copy_tags',
        'video_phrases',
        'used_in_planner',
        'organization_id',
        'user_id',
    ];

    protected $casts = [
        'used_in_planner' => 'boolean',
    ];
}
