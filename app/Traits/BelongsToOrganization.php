<?php

namespace App\Traits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization()
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (! Auth::check()) {
                // Consola, cola y seeds: no hay usuario con el que acotar la consulta.
                return;
            }

            $user = Auth::user();
            $orgId = $user->activeOrganizationId();

            if ($orgId) {
                $builder->where('organization_id', $orgId);

                return;
            }

            if ($user->hasRole('Super Admin')) {
                // Super Admin sin empresa activa: acceso global intencional.
                return;
            }

            // Fail-closed: un usuario sin empresa activa no ve datos de ninguna empresa.
            $builder->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            if (Auth::check() && ! $model->organization_id) {
                $model->organization_id = Auth::user()->activeOrganizationId();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Estampa organization_id en el activity log cuando el modelo usa LogsActivity.
     */
    public function tapActivity(Activity $activity, string $event)
    {
        if (! $activity->organization_id) {
            $activity->organization_id = $this->organization_id ?? Auth::user()?->activeOrganizationId();
        }
    }
}
