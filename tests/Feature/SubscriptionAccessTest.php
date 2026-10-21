<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    private function makeOrgUser(array $orgAttributes = []): array
    {
        $org = Organization::factory()->create($orgAttributes);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('Admin');

        return [$org, $user];
    }

    public function test_active_org_user_can_access_dashboard(): void
    {
        [, $user] = $this->makeOrgUser(['status' => Organization::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_suspended_org_user_is_redirected_to_blocked_page(): void
    {
        [, $user] = $this->makeOrgUser(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_expired_trial_user_is_blocked_without_cron(): void
    {
        [, $user] = $this->makeOrgUser([
            'status' => Organization::STATUS_TRIAL,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/planning')
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_running_trial_user_is_not_blocked(): void
    {
        [, $user] = $this->makeOrgUser([
            'status' => Organization::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_blocked_user_can_still_logout_and_open_profile(): void
    {
        [, $user] = $this->makeOrgUser(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');
    }

    public function test_blocked_user_sees_status_on_blocked_page(): void
    {
        [, $user] = $this->makeOrgUser([
            'status' => Organization::STATUS_TRIAL,
            'plan' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/subscription/blocked')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subscription/Blocked')
                ->where('statusLabel', 'Expirada')
                ->where('plan', 'trial')
                ->has('trialEndsAt'));
    }

    public function test_super_admin_is_exempt_from_subscription_check(): void
    {
        $org = Organization::factory()->create(['status' => Organization::STATUS_SUSPENDED]);
        $user = User::factory()->create(['organization_id' => null]);
        $user->assignRole('Super Admin');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->assertNotNull($org);
    }

    public function test_effective_status_and_is_access_blocked(): void
    {
        $trial = new Organization(['status' => Organization::STATUS_TRIAL]);
        $trial->trial_ends_at = now()->subDay();
        $this->assertSame(Organization::STATUS_EXPIRED, $trial->effectiveStatus());
        $this->assertTrue($trial->isAccessBlocked());

        $trial->trial_ends_at = now()->addDay();
        $this->assertSame(Organization::STATUS_TRIAL, $trial->effectiveStatus());
        $this->assertFalse($trial->isAccessBlocked());

        $suspended = new Organization(['status' => Organization::STATUS_SUSPENDED]);
        $this->assertTrue($suspended->isAccessBlocked());

        $active = new Organization(['status' => Organization::STATUS_ACTIVE]);
        $this->assertFalse($active->isAccessBlocked());
    }
}
