<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'primary_color',
        'admin_invite_code',
        'invite_code',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }
}
