<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'color',
        'youtube_channel_id',
        'channel_url',
        'organization_id',
    ];
}
