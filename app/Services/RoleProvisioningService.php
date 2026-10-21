<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleProvisioningService
{
    public const ROLE_SUPER_ADMIN = 'Super Admin';

    public const ROLE_ADMIN = 'Admin';

    public const ROLE_EMPLOYEE = 'Employee';

    /**
     * Catálogo único de permisos del sistema (fuente de verdad del seeder).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return [
            'view dashboard',

            'view users',
            'create users',
            'edit users',
            'delete users',

            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            'view planning',
            'create planning',
            'edit planning',
            'delete planning',
            'export planning',

            'view ideas',
            'create ideas',
            'edit ideas',
            'delete ideas',
            'import ideas',
            'export ideas',

            'view reports',
            'download reports',
            'delete reports',

            'view youtube',

            'generate ai',
            'view ai history',
            'download ai',

            'view empresa',
            'create empresa',
            'edit empresa',
            'delete empresa',

            'view configuracion',
            'configure work hours',
            'configure youtube',
            'configure dashboard',

            'view vacations',
            'create vacations',
            'edit vacations',
            'delete vacations',

            'view time off',
            'create time off',
            'edit time off',
            'delete time off',
            'approve time off',
            'reject time off',

            'approve vacations',
            'reject vacations',
        ];
    }

    /**
     * Permisos del rol Admin (todo salvo la gestión de roles).
     *
     * @return list<string>
     */
    public function adminPermissions(): array
    {
        return array_values(array_filter($this->permissions(), fn (string $p) => ! str_ends_with($p, 'roles')));
    }

    /**
     * Crea el catálogo de permisos y el rol Super Admin (global, sin organización).
     * Idempotente: puede ejecutarse en cada seed.
     */
    public function syncPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => self::ROLE_SUPER_ADMIN]);
        $superAdmin->givePermissionTo($this->permissions());
    }

    /**
     * Garantiza que una organización tenga los roles por defecto (Admin con
     * permisos y Employee sin permisos). Usado al crear la empresa y al migrar.
     */
    public function ensureDefaultsFor(?int $organizationId): void
    {
        if ($organizationId === null) {
            return;
        }

        $this->ensurePermissionsExist();

        Role::firstOrCreate([
            'name' => self::ROLE_EMPLOYEE,
            'organization_id' => $organizationId,
        ]);

        $admin = Role::firstOrCreate([
            'name' => self::ROLE_ADMIN,
            'organization_id' => $organizationId,
        ]);

        if ($admin->permissions()->count() === 0) {
            $admin->givePermissionTo($this->adminPermissions());
        }
    }

    /**
     * Devuelve (creándolo si falta) un rol por defecto de la organización.
     */
    public function ensureRoleFor(int $organizationId, string $roleName): Role
    {
        $this->ensurePermissionsExist();

        $role = Role::firstOrCreate([
            'name' => $roleName,
            'organization_id' => $organizationId,
        ]);

        if ($roleName === self::ROLE_ADMIN && $role->permissions()->count() === 0) {
            $role->givePermissionTo($this->adminPermissions());
        }

        return $role;
    }

    /**
     * Resuelve un rol por defecto en el ámbito de una organización y, si no
     * existe (empresas creadas antes del aprovisionamiento), lo crea.
     */
    public function resolveFor(int $organizationId, string $roleName): Role
    {
        return Role::where('organization_id', $organizationId)
            ->where('name', $roleName)
            ->first()
            ?? $this->ensureRoleFor($organizationId, $roleName);
    }

    private function ensurePermissionsExist(): void
    {
        $existing = Permission::whereIn('name', $this->permissions())->pluck('name')->all();

        foreach (array_diff($this->permissions(), $existing) as $missing) {
            Permission::firstOrCreate(['name' => $missing]);
        }
    }
}
