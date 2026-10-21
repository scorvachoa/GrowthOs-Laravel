<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SocialConnection extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'provider',
        'page_id',
        'page_name',
        'page_picture',
        'access_token',
        'user_token',
        'expires_at',
        'meta',
    ];

    protected $hidden = [
        'access_token',
        'user_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'user_token' => 'encrypted',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];
}
