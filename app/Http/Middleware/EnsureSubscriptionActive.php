<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $orgId = $user->activeOrganizationId();

        if (! $orgId) {
            return $next($request);
        }

        $org = Organization::find($orgId);

        if ($org && ! $org->isAccessBlocked()) {
            return $next($request);
        }

        if ($request->routeIs('subscription.blocked', 'logout', 'profile.*')) {
            return $next($request);
        }

        return redirect()->route('subscription.blocked');
    }
}
