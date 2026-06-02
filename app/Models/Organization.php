<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'primary_color',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
