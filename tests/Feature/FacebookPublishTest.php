<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPublishTest extends TestCase
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

    public function test_facebook_publish_succeeds_and_clears_feed_cache(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => '1258598414001431_999']),
        ]);
        $this->createConnection();

        Cache::put("facebook_page_feed:{$this->org->id}", ['posts' => [], 'error' => true], 100);

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/publish', [
                'message' => 'Nuevo video disponible',
                'link' => 'https://youtube.com/watch?v=abc',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/feed')
            && $request['message'] === 'Nuevo video disponible'
            && $request['link'] === 'https://youtube.com/watch?v=abc');

        $this->assertNull(Cache::get("facebook_page_feed:{$this->org->id}"));
    }

    public function test_facebook_publish_with_photo_uses_photos_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => '1258598414001431_888']),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/publish', [
                'message' => 'Mira esta foto',
                'photo' => 'https://cdn.example.com/nueva.jpg',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/photos')
            && $request['url'] === 'https://cdn.example.com/nueva.jpg'
            && $request['message'] === 'Mira esta foto');
    }

    public function test_facebook_publish_graph_error_sets_flash(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#200) Requires pages_manage_posts permission', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/publish', [
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook')
            ->assertSessionHas('error');
    }

    public function test_facebook_publish_requires_message(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/publish', ['organization_id' => $this->org->id])
            ->assertSessionHasErrors('message');
    }

    public function test_facebook_publish_requires_connection(): void
    {
        $this->actingAs($this->admin)
            ->from('/facebook')
            ->post('/facebook/publish', [
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();
    }

    public function test_facebook_publish_requires_empresa_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->post('/facebook/publish', [
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();
    }
}
