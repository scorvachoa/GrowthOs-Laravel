<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization()
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (Auth::check()) {
                $orgId = Auth::user()->activeOrganizationId();
                if ($orgId) {
                    $builder->where('organization_id', $orgId);
                }
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && ! $model->organization_id) {
                $model->organization_id = Auth::user()->activeOrganizationId();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
