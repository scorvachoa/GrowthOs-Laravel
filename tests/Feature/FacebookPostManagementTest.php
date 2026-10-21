<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPostManagementTest extends TestCase
{
    use RefreshDatabase;

    private const POST_ID = '1258598414001431_999';

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

    private function createConnection(): SocialConnection
    {
        return SocialConnection::create([
            'organization_id' => $this->org->id,
            'provider' => 'facebook',
            'page_id' => '1258598414001431',
            'page_name' => 'InkaGo',
            'page_picture' => 'https://cdn.example.com/pic.jpg',
            'access_token' => 'EAAG-test-token',
            'meta' => ['followers' => 100, 'fan_count' => 90],
        ]);
    }

    private function fakePostSuccess(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true]),
        ]);
    }

    public function test_facebook_post_update_succeeds_and_clears_cache(): void
    {
        $this->fakePostSuccess();
        $this->createConnection();

        Cache::put("facebook_page_feed:{$this->org->id}", ['posts' => [], 'error' => true], 100);

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/update', [
                'post_id' => self::POST_ID,
                'message' => 'Texto editado',
                'link' => 'https://youtube.com/watch?v=xyz',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), self::POST_ID)
            && $request['message'] === 'Texto editado'
            && $request['link'] === 'https://youtube.com/watch?v=xyz');

        $this->assertNull(Cache::get("facebook_page_feed:{$this->org->id}"));
    }

    public function test_facebook_post_update_validates_input(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/update', [
                'post_id' => self::POST_ID,
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('message');

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/update', [
                'post_id' => '../../etc',
                'message' => 'Texto',
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('post_id');
    }

    public function test_facebook_post_delete_succeeds_and_clears_cache(): void
    {
        $this->fakePostSuccess();
        $this->createConnection();

        Cache::put("facebook_page_feed:{$this->org->id}", ['posts' => [], 'error' => false], 100);

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/delete', [
                'post_id' => self::POST_ID,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), self::POST_ID));

        $this->assertNull(Cache::get("facebook_page_feed:{$this->org->id}"));
    }

    public function test_facebook_post_management_graph_error_sets_flash(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#200) Post is not visible', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/delete', [
                'post_id' => self::POST_ID,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('error');
    }

    public function test_facebook_post_management_requires_connection(): void
    {
        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/posts/delete', [
                'post_id' => self::POST_ID,
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();
    }

    public function test_facebook_post_management_requires_empresa_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $payload = ['post_id' => self::POST_ID, 'organization_id' => $this->org->id];

        $this->actingAs($employee)->post('/facebook/posts/update', $payload + ['message' => 'x'])->assertForbidden();
        $this->actingAs($employee)->post('/facebook/posts/delete', $payload)->assertForbidden();
    }
}
