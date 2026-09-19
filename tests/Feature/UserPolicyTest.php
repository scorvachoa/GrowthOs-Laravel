<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->org = Organization::factory()->create();
        $this->superAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_super_admin_can_view_any_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->superAdmin->can('viewAny', $user));
    }

    public function test_super_admin_can_create_user(): void
    {
        $this->assertTrue($this->superAdmin->can('create', User::class));
    }

    public function test_super_admin_can_update_any_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->superAdmin->can('update', $user));
    }

    public function test_super_admin_can_delete_any_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->superAdmin->can('delete', $user));
    }

    public function test_non_super_admin_cannot_update_super_admin(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');

        $this->assertFalse($admin->can('update', $this->superAdmin));
    }

    public function test_non_super_admin_cannot_delete_super_admin(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');

        $this->assertFalse($admin->can('delete', $this->superAdmin));
    }

    public function test_admin_can_update_user_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('update', $user));
    }

    public function test_admin_cannot_update_user_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $user = User::factory()->create(['organization_id' => $otherOrg->id]);

        $this->assertFalse($admin->can('update', $user));
    }

    public function test_employee_without_permission_cannot_create_user(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->assertFalse($employee->can('create', User::class));
    }

    public function test_super_admin_can_restore_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->superAdmin->can('restore', $user));
    }

    public function test_super_admin_can_force_delete_user(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($this->superAdmin->can('forceDelete', $user));
    }
}
