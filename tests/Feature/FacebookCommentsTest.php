<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookCommentsTest extends TestCase
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

    private function fakeCommentsGraph(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/feed')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'post1_1',
                            'message' => 'Nuevo video en el canal',
                            'created_time' => now()->subDay()->toIso8601String(),
                            'permalink_url' => 'https://www.facebook.com/InkaGo/posts/post1',
                            'comments' => ['data' => [
                                [
                                    'id' => 'c1',
                                    'message' => 'Me encanta este video',
                                    'from' => ['id' => 'u1', 'name' => 'Maria Lopez'],
                                    'created_time' => now()->subHours(3)->toIso8601String(),
                                    'likes' => ['summary' => ['count' => 2]],
                                    'can_reply' => true,
                                    'comments' => ['data' => [
                                        [
                                            'id' => 'c1r1',
                                            'message' => 'Gracias!',
                                            'from' => ['id' => '1258598414001431', 'name' => 'InkaGo'],
                                            'created_time' => now()->subHours(2)->toIso8601String(),
                                            'likes' => ['summary' => ['count' => 0]],
                                        ],
                                    ]],
                                ],
                                [
                                    'id' => 'c2',
                                    'message' => '¿Cuándo el próximo?',
                                    'from' => ['id' => 'u2', 'name' => 'Pedro Ruiz'],
                                    'created_time' => now()->subDays(2)->toIso8601String(),
                                    'likes' => ['summary' => ['count' => 0]],
                                    'can_reply' => true,
                                    'comments' => ['data' => []],
                                ],
                                [
                                    'id' => 'c3',
                                    'message' => 'Comentario de la propia pagina',
                                    'from' => ['id' => '1258598414001431', 'name' => 'InkaGo'],
                                    'created_time' => now()->subDays(1)->toIso8601String(),
                                    'likes' => ['summary' => ['count' => 0]],
                                    'comments' => ['data' => []],
                                ],
                            ]],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'id' => '1258598414001431',
                'name' => 'InkaGo',
                'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                'followers_count' => 100,
                'fan_count' => 90,
            ]);
        });
    }

    public function test_facebook_comments_page_renders_threads(): void
    {
        $this->fakeCommentsGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Comments')
                ->where('connection.page_name', 'InkaGo')
                ->where('comments.total', 2)
                ->where('comments.unresponded', 1)
                ->where('comments.error', false)
                ->has('comments.threads', 2)
                ->where('comments.threads.0.id', 'c1')
                ->where('comments.threads.0.responded', true)
                ->where('comments.threads.0.author', 'Maria Lopez')
                ->where('comments.threads.0.post.snippet', 'Nuevo video en el canal')
                ->has('comments.threads.0.replies', 1)
                ->where('comments.threads.0.replies.0.author_is_page', true)
                ->where('comments.threads.1.id', 'c2')
                ->where('comments.threads.1.responded', false));
    }

    public function test_facebook_comments_page_handles_graph_error(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#10) Insufficient privileges', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Comments')
                ->where('comments.error', true)
                ->where('comments.total', 0));
    }

    public function test_facebook_comments_reply_succeeds_and_clears_cache(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'reply1']);
            }

            return Http::response([
                'id' => '1258598414001431',
                'name' => 'InkaGo',
                'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
                'followers_count' => 100,
                'fan_count' => 90,
            ]);
        });
        $this->createConnection();

        Cache::put("facebook_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/reply', [
                'comment_id' => '111_222',
                'message' => 'Gracias por comentar!',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), '111_222/comments')
            && $request['message'] === 'Gracias por comentar!');

        $this->assertNull(Cache::get("facebook_comments:{$this->org->id}"));
    }

    public function test_facebook_comments_reply_requires_message(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/reply', [
                'comment_id' => '111_222',
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('message');
    }

    public function test_facebook_comments_reply_graph_error_sets_flash(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#200) Permission denied', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/reply', [
                'comment_id' => '111_222',
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/comments')
            ->assertSessionHas('error');
    }

    public function test_facebook_comments_hide_succeeds_and_clears_cache(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true]),
        ]);
        $this->createConnection();

        Cache::put("facebook_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/hide', [
                'comment_id' => '111_222',
                'hidden' => true,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '111_222')
            && ($request['is_hidden'] ?? null) === true);

        $this->assertNull(Cache::get("facebook_comments:{$this->org->id}"));
    }

    public function test_facebook_comments_delete_succeeds_and_clears_cache(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true]),
        ]);
        $this->createConnection();

        Cache::put("facebook_comments:{$this->org->id}", ['threads' => []], 100);

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/delete', [
                'comment_id' => '111_222',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/comments')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '111_222'));

        $this->assertNull(Cache::get("facebook_comments:{$this->org->id}"));
    }

    public function test_facebook_comments_hide_graph_error_sets_flash(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#200) Permission denied', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook/comments')
            ->post('/facebook/comments/hide', [
                'comment_id' => '111_222',
                'hidden' => false,
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/comments')
            ->assertSessionHas('error');
    }

    public function test_facebook_comments_moderation_validation(): void
    {
        $this->createConnection();

        $base = ['organization_id' => $this->org->id];

        $this->actingAs($this->admin)
            ->post('/facebook/comments/hide', ['comment_id' => '111_222'] + $base)
            ->assertSessionHasErrors('hidden');

        $this->actingAs($this->admin)
            ->post('/facebook/comments/hide', ['comment_id' => 'bad id!', 'hidden' => true] + $base)
            ->assertSessionHasErrors('comment_id');

        $this->actingAs($this->admin)
            ->post('/facebook/comments/delete', ['comment_id' => 'bad id!'] + $base)
            ->assertSessionHasErrors('comment_id');
    }

    public function test_facebook_comments_requires_empresa_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get('/facebook/comments')->assertForbidden();

        $payload = ['comment_id' => '111_222', 'organization_id' => $this->org->id];

        $this->actingAs($employee)
            ->post('/facebook/comments/reply', $payload + ['message' => 'Hola'])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post('/facebook/comments/hide', $payload + ['hidden' => true])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post('/facebook/comments/delete', $payload)
            ->assertForbidden();
    }
}
