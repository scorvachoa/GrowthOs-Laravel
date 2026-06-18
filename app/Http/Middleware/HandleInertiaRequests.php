<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $companies = null;
        $activeCompany = null;

        if ($user) {
            if ($user->hasRole('Super Admin')) {
                $companies = Organization::orderBy('name')->get(['id', 'name', 'primary_color', 'logo_path'])->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'primary_color' => $o->primary_color,
                    'logo_url' => $o->logo_path ? \Illuminate\Support\Facades\Storage::url($o->logo_path) : null,
                ]);

                $activeId = session('active_company_id');
                $activeCompany = $activeId ? $companies->firstWhere('id', $activeId) : null;
            } else {
                $org = $user->organization;
                if ($org) {
                    $activeCompany = [
                        'id' => $org->id,
                        'name' => $org->name,
                        'primary_color' => $org->primary_color,
                        'logo_url' => $org->logo_path ? \Illuminate\Support\Facades\Storage::url($org->logo_path) : null,
                    ];
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'settings' => $user->merged_settings,
                    'active_company' => $activeCompany,
                    'companies' => $companies,
                ] : null,
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
