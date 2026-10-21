<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPageTest extends TestCase
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
            'page_id' => '1258598414001431',
            'page_name' => 'InkaGo',
            'page_picture' => 'https://cdn.example.com/pic.jpg',
            'access_token' => 'EAAG-test-token',
            'meta' => ['followers' => 100, 'fan_count' => 90],
        ], $overrides));
    }

    private function fakeGraph(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/feed')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'post1_2',
                            'message' => 'Nuevo video en el canal',
                            'created_time' => now()->subDay()->toIso8601String(),
                            'permalink_url' => 'https://www.facebook.com/InkaGo/posts/post1',
                            'full_picture' => 'https://cdn.example.com/post.jpg',
                            'likes' => ['summary' => ['count' => 12]],
                            'comments' => ['summary' => ['count' => 3]],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'id' => '1258598414001431',
                'name' => 'InkaGo',
                'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                'followers_count' => 150,
                'fan_count' => 140,
            ]);
        });
    }

    public function test_facebook_page_requires_view_empresa_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get('/facebook')->assertStatus(403);
    }

    public function test_facebook_page_renders_connection_and_feed(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('organization_id', $this->org->id)
                ->where('connection.connected', true)
                ->where('connection.page_name', 'InkaGo')
                ->where('connection.stale', false)
                ->where('connection.followers', 150)
                ->where('feed.error', false)
                ->has('feed.posts', 1)
                ->where('feed.posts.0.message', 'Nuevo video en el canal')
                ->where('feed.posts.0.likes', 12)
                ->where('feed.posts.0.comments', 3));
    }

    public function test_facebook_feed_maps_video_posts(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/feed')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'post1_2',
                            'message' => 'Video de la Página',
                            'created_time' => now()->subDay()->toIso8601String(),
                            'permalink_url' => 'https://www.facebook.com/InkaGo/posts/post1',
                            'full_picture' => 'https://cdn.example.com/thumb.jpg',
                            'attachments' => ['data' => [
                                ['media_type' => 'video', 'media' => ['source' => 'https://video.fbcdn.net/clip.mp4']],
                            ]],
                            'likes' => ['summary' => ['count' => 5]],
                            'comments' => ['summary' => ['count' => 1]],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'id' => '1258598414001431',
                'name' => 'InkaGo',
                'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                'followers_count' => 150,
                'fan_count' => 140,
            ]);
        });
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('feed.posts.0.is_video', true)
                ->where('feed.posts.0.video_url', 'https://video.fbcdn.net/clip.mp4')
                ->where('feed.posts.0.full_picture', 'https://cdn.example.com/thumb.jpg'));
    }

    public function test_facebook_page_shows_connect_form_when_not_connected(): void
    {
        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('organization_id', $this->org->id)
                ->where('connection', null)
                ->where('feed', null)
                ->where('insights', null));
    }

    public function test_facebook_page_includes_daily_insights(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/insights')) {
                return Http::response([
                    'data' => [
                        ['name' => 'page_views_total', 'period' => 'day', 'values' => [
                            ['value' => 30, 'end_time' => now()->subDays(2)->toIso8601String()],
                            ['value' => 12, 'end_time' => now()->subDay()->toIso8601String()],
                            ['value' => 7, 'end_time' => now()->subDays(40)->toIso8601String()],
                        ]],
                        ['name' => 'page_post_engagements', 'period' => 'day', 'values' => [
                            ['value' => 8, 'end_time' => now()->subDays(2)->toIso8601String()],
                            ['value' => 5, 'end_time' => now()->subDay()->toIso8601String()],
                        ]],
                        ['name' => 'page_video_views', 'period' => 'day', 'values' => [
                            ['value' => 1, 'end_time' => now()->subDay()->toIso8601String()],
                        ]],
                    ],
                ]);
            }

            return Http::response([
                'id' => '1258598414001431',
                'name' => 'InkaGo',
                'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                'followers_count' => 150,
                'fan_count' => 140,
            ]);
        });
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('insights.available', true)
                ->has('insights.days', 2)
                ->where('insights.days.0.views', 30)
                ->where('insights.totals.views', 42)
                ->where('insights.totals.engagements', 13)
                ->where('insights.totals.video_views', 1)
                ->where('insights.previous_totals.views', 7)
                ->has('insights.previous_days', 1));
    }

    public function test_facebook_page_accepts_range_parameter(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Unsupported', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook?days=7')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('days', 7));

        $this->actingAs($this->admin)
            ->get('/facebook?days=999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('days', 28));
    }

    public function test_facebook_page_shows_insights_permission_hint_when_denied(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#10) Insufficient privileges', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('insights.available', false)
                ->where('connection.stale', true)
                ->where('feed.error', true));
    }

    public function test_facebook_page_handles_graph_api_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Service temporarily unavailable', 'type' => 'APIerror'],
            ], 500),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('connection.connected', true)
                ->where('connection.stale', true)
                ->where('connection.followers', 100)
                ->where('feed.error', true)
                ->has('feed.posts', 0));
    }

    public function test_super_admin_without_active_company_sees_no_connection(): void
    {
        $super = User::factory()->create(['organization_id' => null]);
        $super->assignRole('Super Admin');

        $this->actingAs($super)
            ->get('/facebook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Index')
                ->where('organization_id', null)
                ->where('connection', null));
    }
}
