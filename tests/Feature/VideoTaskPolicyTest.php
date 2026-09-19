<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoTaskPolicyTest extends TestCase
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

    public function test_super_admin_can_view_any_task(): void
    {
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->can('view', $task));
    }

    public function test_super_admin_can_create_task(): void
    {
        $this->assertTrue($this->superAdmin->can('create', VideoTask::class));
    }

    public function test_super_admin_can_update_task(): void
    {
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->can('update', $task));
    }

    public function test_super_admin_can_delete_task(): void
    {
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->can('delete', $task));
    }

    public function test_admin_can_view_task_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($admin->can('view', $task));
    }

    public function test_admin_cannot_view_task_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $admin->id,
        ]);

        $this->assertFalse($admin->can('view', $task));
    }

    public function test_admin_can_update_task_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($admin->can('update', $task));
    }

    public function test_admin_cannot_update_task_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $admin->id,
        ]);

        $this->assertFalse($admin->can('update', $task));
    }

    public function test_employee_without_permission_cannot_create_task(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->assertFalse($employee->can('create', VideoTask::class));
    }

    public function test_admin_can_delete_task_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($admin->can('delete', $task));
    }

    public function test_admin_cannot_delete_task_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $task = VideoTask::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $admin->id,
        ]);

        $this->assertFalse($admin->can('delete', $task));
    }
}
