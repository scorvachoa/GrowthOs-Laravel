<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QuotaTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $org->id]);
        $this->user->assignRole('Admin');

        Route::middleware(['auth', 'quota:ai.generate'])->post('/_quota-ok', function () {
            return response()->json(['ok' => true]);
        })->name('_quota.ok');

        Route::middleware(['auth', 'quota:ai.generate'])->post('/_quota-fail', function () {
            return response()->json(['error' => 'fail'], 422);
        })->name('_quota.fail');
    }

    public function test_records_usage_event_when_response_is_successful(): void
    {
        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();

        $this->assertDatabaseHas('usage_events', [
            'organization_id' => $this->user->organization_id,
            'user_id' => $this->user->id,
            'event' => 'ai.generate',
            'quantity' => 1,
        ]);
    }

    public function test_does_not_record_on_client_error(): void
    {
        $this->actingAs($this->user)->postJson('/_quota-fail')->assertStatus(422);

        $this->assertSame(0, UsageEvent::withoutGlobalScope('organization')->count());
    }

    public function test_safety_ceiling_always_blocks_even_in_observe_mode(): void
    {
        config(['quotas.mode' => 'observe', 'quotas.safety_ceiling' => 2]);

        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();
        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();

        $this->actingAs($this->user)
            ->from('/ai')
            ->post('/_quota-ok')
            ->assertRedirect('/ai')
            ->assertSessionHas('error');

        $this->assertSame(2, UsageEvent::withoutGlobalScope('organization')->count());
    }

    public function test_observe_mode_allows_when_plan_limit_exceeded(): void
    {
        config([
            'quotas.mode' => 'observe',
            'quotas.limits.ai.generate' => 1,
            'quotas.safety_ceiling' => 10000,
        ]);

        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();
        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();

        $this->assertSame(2, UsageEvent::withoutGlobalScope('organization')->count());
    }

    public function test_enforce_mode_blocks_when_plan_limit_exceeded(): void
    {
        config([
            'quotas.mode' => 'enforce',
            'quotas.limits.ai.generate' => 1,
            'quotas.safety_ceiling' => 10000,
        ]);

        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();

        $this->actingAs($this->user)
            ->from('/ai')
            ->post('/_quota-ok')
            ->assertRedirect('/ai')
            ->assertSessionHas('error');

        $this->assertSame(1, UsageEvent::withoutGlobalScope('organization')->count());
    }

    public function test_counts_only_current_month_window(): void
    {
        $orgId = $this->user->organization_id;

        UsageEvent::withoutGlobalScope('organization')->forceCreate([
            'organization_id' => $orgId,
            'user_id' => $this->user->id,
            'event' => 'ai.generate',
            'quantity' => 5,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);
        UsageEvent::withoutGlobalScope('organization')->create([
            'organization_id' => $orgId,
            'user_id' => $this->user->id,
            'event' => 'ai.generate',
            'quantity' => 3,
        ]);

        $quota = app(QuotaService::class);

        $this->assertSame(3, $quota->used($orgId, 'ai.generate'));
        $this->assertSame(['ai.generate' => 3], $quota->monthlyUsage($orgId));
    }

    public function test_monthly_usage_aggregates_by_event(): void
    {
        $orgId = $this->user->organization_id;
        $quota = app(QuotaService::class);

        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();
        $this->actingAs($this->user)->postJson('/_quota-ok')->assertOk();

        UsageEvent::withoutGlobalScope('organization')->create([
            'organization_id' => $orgId,
            'user_id' => $this->user->id,
            'event' => 'ai.audio',
            'quantity' => 7,
        ]);

        $this->assertSame(
            ['ai.audio' => 7, 'ai.generate' => 2],
            $quota->monthlyUsage($orgId)
        );
    }

    public function test_quota_service_reads_config(): void
    {
        config([
            'quotas.mode' => 'enforce',
            'quotas.safety_ceiling' => 50,
            'quotas.limits.ai.generate' => 10,
            'quotas.limits.ai.audio' => null,
        ]);

        $quota = app(QuotaService::class);

        $this->assertTrue($quota->isEnforcing());
        $this->assertSame(50, $quota->safetyCeiling());
        $this->assertSame(10, $quota->limitFor('ai.generate'));
        $this->assertNull($quota->limitFor('ai.audio'));
    }
}
