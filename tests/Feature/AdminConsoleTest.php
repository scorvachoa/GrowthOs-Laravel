<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\UsageEvent;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create(['organization_id' => null]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_org_admin_cannot_access_admin_console(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->get('/admin/organizations')
            ->assertForbidden();

        $this->actingAs($user)
            ->patch('/admin/organizations/'.$org->id.'/suspend')
            ->assertForbidden();
    }

    public function test_super_admin_sees_organizations_with_usage(): void
    {
        $org = Organization::factory()->create(['name' => 'ACME', 'status' => 'trial']);
        UsageEvent::withoutGlobalScope('organization')->create([
            'organization_id' => $org->id,
            'event' => 'ai.generate',
            'quantity' => 4,
        ]);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organizations/Index')
                ->has('organizations', 2)
                ->where('organizations.0.name', 'ACME')
                ->where('organizations.0.status', 'trial')
                ->where('organizations.0.usage_this_month', ['ai.generate' => 4]));
    }

    public function test_super_admin_can_suspend_and_org_users_are_blocked(): void
    {
        $org = Organization::factory()->create();
        $member = User::factory()->create(['organization_id' => $org->id]);
        $member->assignRole('Admin');

        $this->actingAs($member)->get('/dashboard')->assertOk();

        $this->actingAs($this->makeSuperAdmin())
            ->patch('/admin/organizations/'.$org->id.'/suspend')
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'status' => Organization::STATUS_SUSPENDED,
        ]);

        $this->actingAs($member)
            ->get('/dashboard')
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_super_admin_can_activate_and_set_plan(): void
    {
        $org = Organization::factory()->create(['status' => Organization::STATUS_SUSPENDED]);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)
            ->patch('/admin/organizations/'.$org->id.'/activate')
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $this->actingAs($superAdmin)
            ->patch('/admin/organizations/'.$org->id.'/plan', [
                'plan' => 'pro',
                'trial_ends_at' => now()->addDays(14)->toDateString(),
            ])
            ->assertRedirect();

        $org->refresh();
        $this->assertSame('pro', $org->plan);
        $this->assertTrue($org->trial_ends_at->isFuture());
    }

    public function test_admin_actions_are_audited_with_organization_id(): void
    {
        $org = Organization::factory()->create();

        $this->actingAs($this->makeSuperAdmin())
            ->patch('/admin/organizations/'.$org->id.'/suspend');

        $this->assertDatabaseHas('activity_log', [
            'organization_id' => $org->id,
            'description' => 'admin.organization.suspended',
        ]);
    }

    public function test_model_activity_is_stamped_with_organization_id(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('Admin');

        $task = VideoTask::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);
        $task->update(['title' => 'Titulo auditado']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => VideoTask::class,
            'subject_id' => $task->id,
            'organization_id' => $org->id,
        ]);
    }
}
