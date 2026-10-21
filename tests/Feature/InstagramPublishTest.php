<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramPublishTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private const PAGE_ID = '1258598414001431';

    private const IG_ID = '17841400000000123';

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
            'page_id' => self::PAGE_ID,
            'page_name' => 'InkaGo',
            'page_picture' => 'https://cdn.example.com/pic.jpg',
            'access_token' => 'EAAG-test-token',
            'meta' => ['followers' => 100, 'fan_count' => 90],
        ]);
    }

    private function fakeGraph(array $opts = []): void
    {
        $error = fn (string $message, int $status = 400) => Http::response([
            'error' => ['message' => $message, 'type' => 'OAuthException'],
        ], $status);

        Http::fake(function ($request) use ($opts, $error) {
            $url = $request->url();

            if ($request->method() === 'DELETE') {
                return isset($opts['deleteError'])
                    ? $error('(#100) Media not found')
                    : Http::response(['success' => true]);
            }

            if ($request->method() === 'POST') {
                if (str_contains($url, '/media_publish')) {
                    return Http::response(['id' => 'ig_media_9']);
                }

                if (str_contains($url, '/media')) {
                    return isset($opts['mediaPostError'])
                        ? $error('(#100) Invalid media')
                        : Http::response(['id' => 'container1']);
                }
            }

            if (str_contains($url, 'status_code')) {
                return Http::response(['id' => 'container1', 'status_code' => $opts['containerStatus'] ?? 'FINISHED']);
            }

            if (str_contains($url, 'instagram_business_account')) {
                return Http::response([
                    'instagram_business_account' => [
                        'id' => self::IG_ID,
                        'username' => 'inkago',
                        'name' => 'InkaGo',
                        'profile_picture_url' => 'https://cdn.example.com/ig.jpg',
                        'followers_count' => 500,
                        'follows_count' => 40,
                        'media_count' => 12,
                    ],
                ]);
            }

            if (str_contains($url, 'me/accounts')) {
                return Http::response(['data' => [[
                    'id' => self::PAGE_ID,
                    'name' => 'InkaGo',
                    'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                    'access_token' => 'EAAG-derived',
                    'followers_count' => 100,
                    'fan_count' => 90,
                ]]]);
            }

            return $error('Unexpected call: '.$url, 403);
        });
    }

    public function test_instagram_publish_image_creates_container_and_publishes(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_feed:{$this->org->id}", ['media' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Hola Instagram',
                'image_url' => 'https://cdn.example.com/foto.jpg',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('success', 'Publicado en Instagram');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/'.self::IG_ID.'/media')
            && ! str_contains($request->url(), 'media_publish')
            && ($request['image_url'] ?? null) === 'https://cdn.example.com/foto.jpg'
            && ($request['caption'] ?? null) === 'Hola Instagram');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/media_publish')
            && ($request['creation_id'] ?? null) === 'container1');

        $this->assertNull(Cache::get("instagram_feed:{$this->org->id}"));
    }

    public function test_instagram_publish_video_polls_container_until_finished(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Video nuevo',
                'video_url' => 'https://cdn.example.com/video.mp4',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('success', 'Publicado en Instagram');

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'status_code'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/'.self::IG_ID.'/media')
            && ! str_contains($request->url(), 'media_publish')
            && ($request['video_url'] ?? null) === 'https://cdn.example.com/video.mp4'
            && ! isset($request['media_type']));
    }

    public function test_instagram_publish_reel_sets_media_type(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Reel nuevo',
                'video_url' => 'https://cdn.example.com/reel.mp4',
                'reel' => 1,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/'.self::IG_ID.'/media')
            && ! str_contains($request->url(), 'media_publish')
            && ($request['media_type'] ?? null) === 'REELS');
    }

    public function test_instagram_publish_requires_media_url(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Sin imagen',
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('image_url');
    }

    public function test_instagram_publish_graph_error_sets_flash(): void
    {
        $this->fakeGraph(['mediaPostError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Hola',
                'image_url' => 'https://cdn.example.com/foto.jpg',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('error');
    }

    public function test_instagram_publish_without_connection_is_forbidden(): void
    {
        $this->fakeGraph();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/publish', [
                'caption' => 'Hola',
                'image_url' => 'https://cdn.example.com/foto.jpg',
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();
    }

    public function test_instagram_delete_media_succeeds_and_clears_cache(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_feed:{$this->org->id}", ['media' => []], 100);
        Cache::put("instagram_account:{$this->org->id}", ['linked' => true], 100);

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/media/delete', [
                'media_id' => '17900000000000001',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('success', 'Publicación eliminada');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '17900000000000001'));

        $this->assertNull(Cache::get("instagram_feed:{$this->org->id}"));
        $this->assertNull(Cache::get("instagram_account:{$this->org->id}"));
    }

    public function test_instagram_delete_media_graph_error_sets_flash(): void
    {
        $this->fakeGraph(['deleteError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram')
            ->post('/instagram/media/delete', [
                'media_id' => '17900000000000001',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram')
            ->assertSessionHas('error');
    }
}
