<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->where('organization_id', $request->user()->activeOrganizationId())
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Super Admin'))
            ->when(
                $request->search,
                fn ($query) => $query->where(
                    'name',
                    'like',
                    "%{$request->search}%"
                )
            )
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'organization_id' => $user->organization_id,
                'created_at' => $user->created_at?->diffForHumans(),
            ]);

        $superAdmins = User::query()
            ->with('roles')
            ->whereHas('roles', fn ($r) => $r->where('name', 'Super Admin'))
            ->when(
                $request->search,
                fn ($query) => $query->where(
                    'name',
                    'like',
                    "%{$request->search}%"
                )
            )
            ->latest()
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'organization_id' => $user->organization_id,
                'created_at' => $user->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'super_admins' => $superAdmins,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        $user = auth()->user();
        $roles = $user->hasRole('Super Admin')
            ? Role::whereNull('organization_id')->orWhere('organization_id', $user->activeOrganizationId())->pluck('name')
            : Role::where('organization_id', $user->activeOrganizationId())->where('name', '!=', 'Super Admin')->pluck('name');

        return Inertia::render('Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $this->userService->create(
            $request->validated()
        );

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $authUser = request()->user();
        $roles = $authUser->hasRole('Super Admin')
            ? Role::whereNull('organization_id')->orWhere('organization_id', $authUser->activeOrganizationId())->pluck('name')
            : Role::where('organization_id', $authUser->activeOrganizationId())->where('name', '!=', 'Super Admin')->pluck('name');

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->userService->update(
            $user,
            $request->validated()
        );

        return redirect()->route('users.index')->with('warning', 'Usuario actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('error', 'Usuario eliminado correctamente.');
    }
}
