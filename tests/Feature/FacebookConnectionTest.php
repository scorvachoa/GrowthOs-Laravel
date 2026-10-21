<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Facebook\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Admin');
    }

    private function createConnection(array $overrides = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'organization_id' => $this->org->id,
            'provider' => 'facebook',
            'page_id' => '123456789',
            'page_name' => 'Mi Página',
            'page_picture' => 'https://graph.facebook.com/123456789/picture',
            'access_token' => 'EAAG-test-token',
            'meta' => ['followers' => 42, 'fan_count' => 40],
        ], $overrides));
    }

    private function fakeGraphSuccess(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'name' => 'Mi Página',
                'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
                'followers_count' => 1500,
                'fan_count' => 1400,
            ]),
        ]);
    }

    public function test_connect_requires_edit_empresa_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->post('/company/facebook/connect', ['access_token' => 'EAAG-test-token'])
            ->assertStatus(403);
    }

    public function test_connect_stores_page_connection_with_encrypted_token(): void
    {
        $this->fakeGraphSuccess();

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'EAAG-test-token'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $this->org->id)
            ->where('provider', 'facebook')
            ->firstOrFail();

        $this->assertSame('123456789', $connection->page_id);
        $this->assertSame('Mi Página', $connection->page_name);
        $this->assertSame('EAAG-test-token', $connection->access_token);
        $this->assertSame(1500, $connection->meta['followers']);

        $rawToken = DB::table('social_connections')->where('id', $connection->id)->value('access_token');
        $this->assertStringNotContainsString('EAAG-test-token', $rawToken);
    }

    public function test_connect_rejects_invalid_token(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.', 'type' => 'OAuthException'],
            ], 400),
        ]);

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'bad-token'])
            ->assertStatus(302)
            ->assertSessionHasErrors('facebook_token');

        $this->assertDatabaseCount('social_connections', 0);
    }

    public function test_connect_exchanges_user_token_and_stores_long_lived_tokens(): void
    {
        config()->set('services.facebook.client_id', '123456');
        config()->set('services.facebook.client_secret', 'app-secret');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response(['access_token' => 'LLT-60d', 'expires_in' => 5184000]);
            }

            if (str_contains($request->url(), 'me/accounts')) {
                return Http::response(['data' => [[
                    'id' => '123456789',
                    'name' => 'Mi Página',
                    'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
                    'followers_count' => 1500,
                    'fan_count' => 1400,
                    'access_token' => 'PAGE-NON-EXPIRING',
                ]]]);
            }

            return Http::response([
                'id' => '123456789',
                'name' => 'Mi Página',
                'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
            ]);
        });

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'SHORT-USER-TOKEN'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $this->org->id)
            ->where('provider', 'facebook')
            ->firstOrFail();

        $this->assertSame('PAGE-NON-EXPIRING', $connection->access_token);
        $this->assertSame('LLT-60d', $connection->user_token);
        $this->assertTrue($connection->expires_at->greaterThan(now()->addDays(59)));
        $this->assertTrue($connection->expires_at->lessThan(now()->addDays(61)));

        $rawUserToken = DB::table('social_connections')->where('id', $connection->id)->value('user_token');
        $this->assertStringNotContainsString('LLT-60d', $rawUserToken);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/access_token')
            && str_contains($r->url(), 'fb_exchange_token=SHORT-USER-TOKEN'));

        $this->assertStringContainsString('larga duración', session('success'));
    }

    public function test_connect_without_app_credentials_stores_token_as_is(): void
    {
        config()->set('services.facebook.client_id', null);
        config()->set('services.facebook.client_secret', null);

        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->url();

            return Http::response([
                'id' => '123456789',
                'name' => 'Mi Página',
                'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
                'followers_count' => 1500,
                'fan_count' => 1400,
            ]);
        });

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'EAAG-test-token'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $this->org->id)
            ->where('provider', 'facebook')
            ->firstOrFail();

        $this->assertSame('EAAG-test-token', $connection->access_token);
        $this->assertNull($connection->user_token);
        $this->assertNull($connection->expires_at);

        $this->assertEmpty(array_filter($sent, fn ($url) => str_contains($url, 'oauth/access_token')));
    }

    public function test_connect_falls_back_when_exchange_is_rejected(): void
    {
        config()->set('services.facebook.client_id', '123456');
        config()->set('services.facebook.client_secret', 'app-secret');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'oauth/access_token') || str_contains($url, 'me/accounts')) {
                return Http::response([
                    'error' => ['message' => 'Invalid OAuth access token.', 'type' => 'OAuthException'],
                ], 400);
            }

            return Http::response([
                'id' => '123456789',
                'name' => 'Mi Página',
                'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
                'followers_count' => 1500,
                'fan_count' => 1400,
            ]);
        });

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'EAAG-page-token'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $this->org->id)
            ->where('provider', 'facebook')
            ->firstOrFail();

        $this->assertSame('EAAG-page-token', $connection->access_token);
        $this->assertNull($connection->user_token);
        $this->assertNull($connection->expires_at);
        $this->assertStringContainsString('corta duración', session('success'));
    }

    public function test_connection_refreshes_long_lived_token_when_due(): void
    {
        config()->set('services.facebook.client_id', '123456');
        config()->set('services.facebook.client_secret', 'app-secret');

        $this->createConnection([
            'user_token' => 'OLD-LLT',
            'expires_at' => now()->addDays(2),
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response(['access_token' => 'NEW-LLT', 'expires_in' => 5184000]);
            }

            if (str_contains($request->url(), 'me/accounts')) {
                return Http::response(['data' => [[
                    'id' => '123456789',
                    'name' => 'Mi Página',
                    'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
                    'followers_count' => 42,
                    'fan_count' => 40,
                    'access_token' => 'NEW-PAGE-TOKEN',
                ]]]);
            }

            return Http::response([
                'id' => '123456789',
                'name' => 'Mi Página',
                'picture' => ['data' => ['url' => 'https://graph.facebook.com/123456789/picture']],
            ]);
        });

        $connection = app(AuthService::class)->connection($this->org->id);

        $this->assertSame('NEW-PAGE-TOKEN', $connection->access_token);
        $this->assertSame('NEW-LLT', $connection->user_token);
        $this->assertTrue($connection->expires_at->greaterThan(now()->addDays(59)));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/access_token')
            && str_contains($r->url(), 'fb_exchange_token=OLD-LLT'));
    }

    public function test_connection_skips_refresh_when_far_from_expiry(): void
    {
        config()->set('services.facebook.client_id', '123456');
        config()->set('services.facebook.client_secret', 'app-secret');

        $this->createConnection([
            'user_token' => 'LLT',
            'expires_at' => now()->addDays(30),
        ]);

        Http::fake();

        $connection = app(AuthService::class)->connection($this->org->id);

        $this->assertSame('EAAG-test-token', $connection->access_token);
        Http::assertNothingSent();
    }

    public function test_connect_prefers_already_connected_page_on_reconnect(): void
    {
        config()->set('services.facebook.client_id', '123456');
        config()->set('services.facebook.client_secret', 'app-secret');

        $this->createConnection(['page_id' => '350791751457036', 'page_name' => 'Machupicchu Terra']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response(['access_token' => 'LLT-60d', 'expires_in' => 5184000]);
            }

            if (str_contains($request->url(), 'me/accounts')) {
                return Http::response(['data' => [
                    ['id' => '111', 'name' => 'Otra Página', 'access_token' => 'OTHER-PT'],
                    ['id' => '350791751457036', 'name' => 'Machupicchu Terra', 'access_token' => 'KEPT-PT'],
                ]]);
            }

            return Http::response(['id' => '350791751457036', 'name' => 'Machupicchu Terra']);
        });

        $this->actingAs($this->admin)
            ->post('/company/facebook/connect', ['access_token' => 'SHORT-USER-TOKEN'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $this->org->id)
            ->where('provider', 'facebook')
            ->firstOrFail();

        $this->assertSame('350791751457036', $connection->page_id);
        $this->assertSame('KEPT-PT', $connection->access_token);
    }

    public function test_disconnect_removes_connection(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->post('/company/facebook/disconnect')
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseCount('social_connections', 0);

        $this->actingAs($this->admin)
            ->get('/company')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Company/Index')
                ->where('facebook', null));
    }

    public function test_admin_from_another_org_cannot_disconnect_this_org(): void
    {
        $connection = $this->createConnection();

        $otherOrg = Organization::factory()->create();
        $otherAdmin = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherAdmin->assignRole('Admin');

        $this->actingAs($otherAdmin)
            ->post('/company/facebook/disconnect', ['organization_id' => $this->org->id])
            ->assertStatus(403);

        $this->assertDatabaseHas('social_connections', ['id' => $connection->id]);
    }

    public function test_company_index_includes_fresh_facebook_connection(): void
    {
        $this->fakeGraphSuccess();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/company')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Company/Index')
                ->where('facebook.connected', true)
                ->where('facebook.page_name', 'Mi Página')
                ->where('facebook.stale', false)
                ->where('facebook.followers', 1500));
    }

    public function test_company_index_flags_stale_when_graph_api_fails(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Service temporarily unavailable', 'type' => 'APIerror'],
            ], 500),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/company')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Company/Index')
                ->where('facebook.connected', true)
                ->where('facebook.stale', true)
                ->where('facebook.followers', 42));
    }
}
