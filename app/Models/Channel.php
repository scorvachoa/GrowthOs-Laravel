<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'name',
        'color',
        'youtube_channel_id',
        'channel_url',
        'organization_id',
    ];
}
