<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramTest extends TestCase
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

    private function defaultAccount(): array
    {
        return [
            'instagram_business_account' => [
                'id' => self::IG_ID,
                'username' => 'inkago',
                'name' => 'InkaGo',
                'profile_picture_url' => 'https://cdn.example.com/ig.jpg',
                'followers_count' => 500,
                'follows_count' => 40,
                'media_count' => 12,
            ],
        ];
    }

    private function defaultMedia(): array
    {
        return [
            'data' => [
                [
                    'id' => '17900000000000001',
                    'caption' => 'Nuevo video en el canal',
                    'media_type' => 'IMAGE',
                    'media_url' => 'https://cdn.example.com/ig1.jpg',
                    'permalink' => 'https://www.instagram.com/p/abc/',
                    'timestamp' => now()->subHours(3)->toIso8601String(),
                    'like_count' => 10,
                    'comments_count' => 2,
                    'username' => 'inkago',
                ],
            ],
        ];
    }

    private function defaultInsights(): array
    {
        $current = now()->subDay()->toIso8601String();
        $previous = now()->subDays(35)->toIso8601String();

        return [
            'data' => [
                ['name' => 'reach', 'period' => 'day', 'values' => [
                    ['value' => 100, 'end_time' => $current],
                    ['value' => 50, 'end_time' => $previous],
                ]],
                ['name' => 'impressions', 'period' => 'day', 'values' => [
                    ['value' => 300, 'end_time' => $current],
                    ['value' => 120, 'end_time' => $previous],
                ]],
                ['name' => 'follower_count', 'period' => 'day', 'values' => [
                    ['value' => 500, 'end_time' => $current],
                    ['value' => 480, 'end_time' => $previous],
                ]],
            ],
        ];
    }

    private function fakeGraph(array $opts = []): void
    {
        $error = fn (string $message, int $status = 403) => Http::response([
            'error' => ['message' => $message, 'type' => 'OAuthException'],
        ], $status);

        Http::fake(function ($request) use ($opts, $error) {
            $url = $request->url();

            if ($request->method() === 'DELETE') {
                return Http::response(['success' => true]);
            }

            if ($request->method() === 'POST') {
                if (str_contains($url, '/replies')) {
                    return isset($opts['replyError'])
                        ? $error('(#200) Permission denied')
                        : Http::response(['id' => 'reply1']);
                }

                if (str_contains($url, '/media_publish')) {
                    return Http::response(['id' => 'ig_media_9']);
                }

                if (str_contains($url, '/media')) {
                    return isset($opts['mediaPostError'])
                        ? $error('(#100) Invalid media', 400)
                        : Http::response(['id' => 'container1']);
                }

                if (str_contains($url, '/likes')) {
                    return isset($opts['likeError'])
                        ? $error('(#100) Requires instagram_manage_engagement')
                        : Http::response(['success' => true]);
                }

                // Comment hide/unhide (POST /{comment-id} with hide=...).
                return isset($opts['hideError'])
                    ? $error('(#100) Requires instagram_manage_comments')
                    : Http::response(['success' => true]);
            }

            if (str_contains($url, 'status_code')) {
                return Http::response(['id' => 'container1', 'status_code' => 'FINISHED']);
            }

            if (str_contains($url, '/comments')) {
                return isset($opts['commentsError'])
                    ? $error('(#100) Requires instagram_manage_comments')
                    : Http::response($opts['comments'] ?? ['data' => []]);
            }

            if (str_contains($url, '/insights')) {
                return isset($opts['insightsError'])
                    ? $error('(#100) Invalid insights metric')
                    : Http::response($opts['insights'] ?? $this->defaultInsights());
            }

            if (str_contains($url, '/media')) {
                return Http::response($opts['media'] ?? $this->defaultMedia());
            }

            if (str_contains($url, 'instagram_business_account')) {
                return isset($opts['accountError'])
                    ? $error('(#100) Requires instagram_basic')
                    : Http::response($opts['account'] ?? $this->defaultAccount());
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

            return $error('(#100) Unexpected call: '.$url);
        });
    }

    public function test_instagram_index_renders_account_feed_and_insights(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Index')
                ->where('connection.page_name', 'InkaGo')
                ->where('account.linked', true)
                ->where('account.username', 'inkago')
                ->where('account.followers', 500)
                ->where('account.media_count', 12)
                ->has('feed.media', 1)
                ->where('feed.media.0.id', '17900000000000001')
                ->where('feed.media.0.likes', 10)
                ->where('feed.error', false)
                ->where('insights.available', true)
                ->where('insights.totals.reach', 100)
                ->where('insights.totals.impressions', 300)
                ->where('insights.totals.followers', 500)
                ->where('insights.previous_totals.reach', 50));
    }

    public function test_instagram_index_reports_unlinked_account(): void
    {
        $this->fakeGraph(['account' => []]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Index')
                ->where('connection.page_name', 'InkaGo')
                ->where('account.linked', false)
                ->where('account.error', null)
                ->has('feed.media', 0)
                ->where('feed.error', false)
                ->where('insights', null));
    }

    public function test_instagram_index_surfaces_graph_error_on_account(): void
    {
        $this->fakeGraph(['accountError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Index')
                ->where('account.linked', false)
                ->where('account.stale', true)
                ->has('account.error')
                ->has('feed.media', 0)
                ->where('insights', null));
    }

    public function test_instagram_insights_unavailable_when_metrics_rejected(): void
    {
        $this->fakeGraph(['insightsError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Index')
                ->where('account.linked', true)
                ->where('insights.available', false)
                ->has('insights.error')
                ->where('insights.totals.reach', 0));
    }

    public function test_instagram_insights_chunks_requests_into_30_day_windows(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram?days=90')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Index')
                ->where('days', 90)
                ->where('insights.available', true)
                // Fixture days (1 and 35 days ago) both fall inside the
                // 90-day current window; merged values must not duplicate.
                ->where('insights.totals.reach', 150)
                ->where('insights.previous_totals.reach', 0));

        // 90*2 days = 180 days must be split into six <=30 day requests.
        $insightsCalls = Http::recorded(fn ($request) => str_contains($request->url(), '/insights'))->count();
        $this->assertSame(6, $insightsCalls);
    }

    public function test_instagram_comments_page_renders_threads(): void
    {
        $this->fakeGraph([
            'comments' => ['data' => [
                [
                    'id' => 'c1',
                    'text' => 'Me encanta este video',
                    'timestamp' => now()->subHours(2)->toIso8601String(),
                    'username' => 'maria_ig',
                    'like_count' => 3,
                    'replies' => ['data' => [
                        ['id' => 'r1', 'text' => 'Gracias!', 'timestamp' => now()->subHour()->toIso8601String(), 'username' => 'inkago'],
                    ]],
                ],
                [
                    'id' => 'c2',
                    'text' => '¿Cuándo el próximo?',
                    'timestamp' => now()->subDays(1)->toIso8601String(),
                    'username' => 'pedro_ig',
                    'like_count' => 0,
                    'replies' => ['data' => []],
                ],
                [
                    'id' => 'c3',
                    'text' => 'Comentario de la propia cuenta',
                    'timestamp' => now()->subDays(1)->toIso8601String(),
                    'username' => 'inkago',
                    'like_count' => 0,
                    'replies' => ['data' => []],
                ],
            ]],
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/instagram/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Instagram/Comments')
                ->where('account.linked', true)
                ->where('comments.total', 2)
                ->where('comments.unresponded', 1)
                ->where('comments.error', false)
                ->has('comments.threads', 2)
                ->where('comments.threads.0.id', 'c1')
                ->where('comments.threads.0.responded', true)
                ->where('comments.threads.0.author', 'maria_ig')
                ->where('comments.threads.0.post.snippet', 'Nuevo video en el canal')
                ->has('comments.threads.0.replies', 1)
                ->where('comments.threads.0.replies.0.author_is_page', true)
                ->where('comments.threads.1.id', 'c2')
                ->where('comments.threads.1.responded', false));
    }

    public function test_instagram_comments_reply_succeeds_and_clears_cache(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/reply', [
                'comment_id' => '1789_222',
                'message' => 'Gracias por comentar!',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '1789_222/replies')
            && $request['text'] === 'Gracias por comentar!');

        $this->assertNull(Cache::get("instagram_comments:{$this->org->id}"));
    }

    public function test_instagram_comments_reply_requires_message(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/reply', [
                'comment_id' => '1789_222',
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('message');
    }

    public function test_instagram_comments_reply_graph_error_sets_flash(): void
    {
        $this->fakeGraph(['replyError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/reply', [
                'comment_id' => '1789_222',
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('error');
    }

    public function test_instagram_comments_hide_succeeds_and_clears_cache(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/hide', [
                'comment_id' => '1789_222',
                'hidden' => true,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '1789_222')
            && ($request['hide'] ?? null) === true);

        $this->assertNull(Cache::get("instagram_comments:{$this->org->id}"));
    }

    public function test_instagram_comments_delete_succeeds_and_clears_cache(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/delete', [
                'comment_id' => '1789_222',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '1789_222'));

        $this->assertNull(Cache::get("instagram_comments:{$this->org->id}"));
    }

    public function test_instagram_comments_like_and_unlike_succeed(): void
    {
        $this->fakeGraph();
        $this->createConnection();

        Cache::put("instagram_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/like', [
                'comment_id' => '1789_222',
                'liked' => true,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), self::IG_ID.'/likes')
            && ($request['comment_id'] ?? null) === '1789_222');

        $this->assertNull(Cache::get("instagram_comments:{$this->org->id}"));

        Cache::put("instagram_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/like', [
                'comment_id' => '1789_222',
                'liked' => false,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), self::IG_ID.'/likes')
            && str_contains($request->url(), 'comment_id=1789_222'));

        $this->assertNull(Cache::get("instagram_comments:{$this->org->id}"));
    }

    public function test_instagram_comments_like_graph_error_sets_flash(): void
    {
        $this->fakeGraph(['likeError' => true]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/instagram/comments')
            ->post('/instagram/comments/like', [
                'comment_id' => '1789_222',
                'liked' => true,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/instagram/comments')
            ->assertSessionHas('error');
    }

    public function test_instagram_comments_moderation_validation(): void
    {
        $this->createConnection();

        $base = ['organization_id' => $this->org->id];

        $this->actingAs($this->admin)
            ->post('/instagram/comments/hide', ['comment_id' => '1789_222'] + $base)
            ->assertSessionHasErrors('hidden');

        $this->actingAs($this->admin)
            ->post('/instagram/comments/like', ['comment_id' => 'bad id!', 'liked' => true] + $base)
            ->assertSessionHasErrors('comment_id');

        $this->actingAs($this->admin)
            ->post('/instagram/comments/delete', ['comment_id' => 'bad id!'] + $base)
            ->assertSessionHasErrors('comment_id');
    }

    public function test_instagram_requires_empresa_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get('/instagram')->assertForbidden();
        $this->actingAs($employee)->get('/instagram/comments')->assertForbidden();
        $this->actingAs($employee)->get('/instagram/messages')->assertForbidden();

        $payload = ['comment_id' => '1789_222', 'organization_id' => $this->org->id];

        $this->actingAs($employee)->post('/instagram/comments/reply', $payload + ['message' => 'Hola'])->assertForbidden();
        $this->actingAs($employee)->post('/instagram/comments/hide', $payload + ['hidden' => true])->assertForbidden();
        $this->actingAs($employee)->post('/instagram/comments/delete', $payload)->assertForbidden();
        $this->actingAs($employee)->post('/instagram/comments/like', $payload + ['liked' => true])->assertForbidden();
        $this->actingAs($employee)->post('/instagram/messages/send', ['conversation_id' => 't_ig.111', 'message' => 'Hola', 'organization_id' => $this->org->id])->assertForbidden();
        $this->actingAs($employee)->post('/instagram/publish', ['caption' => 'x', 'image_url' => 'https://cdn.example.com/f.jpg', 'organization_id' => $this->org->id])->assertForbidden();
        $this->actingAs($employee)->post('/instagram/media/delete', ['media_id' => '17900000000000001', 'organization_id' => $this->org->id])->assertForbidden();
    }
}
