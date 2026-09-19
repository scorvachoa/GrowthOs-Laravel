<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationPolicyTest extends TestCase
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

    public function test_super_admin_can_view_vacations(): void
    {
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->can('view', $vacation));
    }

    public function test_super_admin_can_approve_vacation(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
        ]);

        $this->assertTrue($this->superAdmin->can('approve', $vacation));
    }

    public function test_user_cannot_approve_own_vacation(): void
    {
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
        $this->assertFalse($this->superAdmin->can('approve', $vacation));
    }

    public function test_user_cannot_reject_own_vacation(): void
    {
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
        $this->assertFalse($this->superAdmin->can('reject', $vacation));
    }

    public function test_admin_can_approve_other_user_vacation(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
        ]);

        $this->assertTrue($admin->can('approve', $vacation));
    }

    public function test_admin_cannot_approve_vacation_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $vacation = Vacation::factory()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $admin->id,
        ]);

        $this->assertFalse($admin->can('approve', $vacation));
    }

    public function test_admin_can_edit_vacation_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can('update', $vacation));
    }

    public function test_admin_can_delete_vacation_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $vacation = Vacation::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can('delete', $vacation));
    }
}
