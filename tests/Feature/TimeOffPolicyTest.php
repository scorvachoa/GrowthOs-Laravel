<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\TimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeOffPolicyTest extends TestCase
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

    public function test_super_admin_can_view_time_off(): void
    {
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->can('view', $timeOff));
    }

    public function test_super_admin_can_approve_time_off(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
        ]);

        $this->assertTrue($this->superAdmin->can('approve', $timeOff));
    }

    public function test_user_cannot_approve_own_time_off(): void
    {
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
        $this->assertFalse($this->superAdmin->can('approve', $timeOff));
    }

    public function test_user_cannot_reject_own_time_off(): void
    {
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
        $this->assertFalse($this->superAdmin->can('reject', $timeOff));
    }

    public function test_admin_can_approve_other_user_time_off(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
        ]);

        $this->assertTrue($admin->can('approve', $timeOff));
    }

    public function test_admin_cannot_approve_time_off_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $admin->id,
        ]);

        $this->assertFalse($admin->can('approve', $timeOff));
    }

    public function test_admin_can_edit_time_off_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can('update', $timeOff));
    }

    public function test_admin_can_delete_time_off_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $timeOff = TimeOff::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can('delete', $timeOff));
    }
}
