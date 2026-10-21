<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        $roleNames = [];

        if ($user) {
            $roleNames = $this->cachedRoleNames($user);

            if (in_array('Super Admin', $roleNames, true)) {
                $activeId = session('active_company_id');

                if ($activeId) {
                    $org = Organization::find($activeId, ['id', 'name', 'primary_color', 'logo_path']);
                    $activeCompany = $org ? [
                        'id' => $org->id,
                        'name' => $org->name,
                        'primary_color' => $org->primary_color,
                        'logo_url' => $org->logo_path ? Storage::url($org->logo_path) : null,
                    ] : null;
                }

                $companies = $this->cachedCompanies();
            } else {
                $org = $user->organization;
                if ($org) {
                    $activeCompany = [
                        'id' => $org->id,
                        'name' => $org->name,
                        'primary_color' => $org->primary_color,
                        'logo_url' => $org->logo_path ? Storage::url($org->logo_path) : null,
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
                    'roles' => $roleNames,
                    'permissions' => $this->cachedPermissions($user),
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

    /**
     * Roles y permisos cambian poco: cachearlos 5 min por usuario evita
     * +2 queries en cada request (solo UI — la autorización real es por ruta).
     * Los tests se lo saltan porque asignan roles en mitad del test y el
     * store en memoria persistiría entre requests.
     */
    private function cachedRoleNames(User $user): array
    {
        if (app()->runningUnitTests()) {
            return $user->getRoleNames()->values()->all();
        }

        return Cache::remember(
            "inertia:user:{$user->id}:roles",
            now()->addMinutes(5),
            fn () => $user->getRoleNames()->values()->all()
        );
    }

    private function cachedPermissions(User $user): array
    {
        if (app()->runningUnitTests()) {
            return $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return Cache::remember(
            "inertia:user:{$user->id}:permissions",
            now()->addMinutes(5),
            fn () => $user->getAllPermissions()->pluck('name')->values()->all()
        );
    }

    /**
     * Lista de empresas del selector del Super Admin.
     */
    private function cachedCompanies(): Collection
    {
        if (app()->runningUnitTests()) {
            return $this->loadCompanies();
        }

        return Cache::remember(
            'inertia:companies',
            now()->addMinutes(5),
            fn () => $this->loadCompanies()
        );
    }

    private function loadCompanies(): Collection
    {
        return Organization::orderBy('name')->get(['id', 'name', 'primary_color', 'logo_path'])->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'primary_color' => $o->primary_color,
            'logo_url' => $o->logo_path ? Storage::url($o->logo_path) : null,
        ]);
    }
}
