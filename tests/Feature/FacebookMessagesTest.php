<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookMessagesTest extends TestCase
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

    private function pageInfoResponse()
    {
        return Http::response([
            'id' => '1258598414001431',
            'name' => 'InkaGo',
            'picture' => ['data' => ['url' => 'https://cdn.example.com/pic.jpg']],
            'followers_count' => 100,
            'fan_count' => 90,
        ]);
    }

    private function conversationsResponse()
    {
        return Http::response([
            'data' => [
                [
                    'id' => 't_1.111',
                    'snippet' => 'Hola, quiero informacion',
                    'updated_time' => now()->subHours(2)->toIso8601String(),
                    'message_count' => 3,
                    'unread_count' => 1,
                    'participants' => ['data' => [
                        ['id' => 'u_psid', 'name' => 'Maria Lopez'],
                        ['id' => '1258598414001431', 'name' => 'InkaGo'],
                    ]],
                ],
                [
                    'id' => 't_1.222',
                    'snippet' => 'Gracias!',
                    'updated_time' => now()->subDays(1)->toIso8601String(),
                    'message_count' => 2,
                    'unread_count' => 0,
                    'participants' => ['data' => [
                        ['id' => 'u_psid2', 'name' => 'Pedro Ruiz'],
                    ]],
                ],
            ],
        ]);
    }

    private function threadResponse()
    {
        return Http::response([
            'data' => [
                [
                    'id' => 'm1',
                    'message' => 'Hola, quiero informacion',
                    'created_time' => now()->subHours(2)->toIso8601String(),
                    'from' => ['id' => 'u_psid', 'name' => 'Maria Lopez'],
                ],
                [
                    'id' => 'm2',
                    'message' => 'Claro, en que podemos ayudarte?',
                    'created_time' => now()->subHours(1)->toIso8601String(),
                    'from' => ['id' => '1258598414001431', 'name' => 'InkaGo'],
                ],
            ],
        ]);
    }

    private function fakeMessagesGraph(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/conversations')) {
                return $this->conversationsResponse();
            }

            if (str_contains($url, 't_1.111/messages')) {
                return $this->threadResponse();
            }

            return $this->pageInfoResponse();
        });
    }

    public function test_facebook_messages_page_renders_conversations(): void
    {
        $this->fakeMessagesGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Messages')
                ->where('connection.page_name', 'InkaGo')
                ->where('conversations.error', false)
                ->has('conversations.conversations', 2)
                ->where('conversations.conversations.0.id', 't_1.111')
                ->where('conversations.conversations.0.title', 'Maria Lopez')
                ->where('conversations.conversations.0.unread_count', 1)
                ->where('conversations.conversations.1.title', 'Pedro Ruiz')
                ->where('thread', null)
                ->where('conversation_id', null));
    }

    public function test_facebook_messages_page_renders_thread(): void
    {
        $this->fakeMessagesGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/messages?conversation=t_1.111')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Messages')
                ->where('conversation_id', 't_1.111')
                ->has('thread.messages', 2)
                ->where('thread.error', false)
                ->where('thread.messages.0.is_page', false)
                ->where('thread.messages.0.from_name', 'Maria Lopez')
                ->where('thread.messages.1.is_page', true));
    }

    public function test_facebook_messages_conversations_filtered_to_messenger(): void
    {
        $this->fakeMessagesGraph();
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/messages')
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/conversations')
            && str_contains($request->url(), 'platform=messenger'));
    }

    public function test_facebook_messages_page_handles_graph_error(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => '(#10) Insufficient privileges', 'type' => 'OAuthException'],
            ], 403),
        ]);
        $this->createConnection();

        $this->actingAs($this->admin)
            ->get('/facebook/messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facebook/Messages')
                ->where('conversations.error', true)
                ->where('conversations.conversations', []));
    }

    public function test_facebook_messages_send_succeeds_and_clears_cache(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['recipient_id' => 'u_psid']);
            }

            if (str_contains($request->url(), 't_1.111')) {
                return Http::response([
                    'id' => 't_1.111',
                    'participants' => ['data' => [
                        ['id' => 'u_psid', 'name' => 'Maria Lopez'],
                        ['id' => '1258598414001431', 'name' => 'InkaGo'],
                    ]],
                ]);
            }

            return $this->pageInfoResponse();
        });
        $this->createConnection();

        Cache::put("facebook_conversations:{$this->org->id}", ['conversations' => []], 100);
        Cache::put("facebook_messages:{$this->org->id}:t_1.111", ['messages' => []], 100);

        $this->actingAs($this->admin)
            ->from('/facebook/messages?conversation=t_1.111')
            ->post('/facebook/messages/send', [
                'conversation_id' => 't_1.111',
                'message' => 'Hola! Gracias por escribir.',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/messages?conversation=t_1.111')
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/messages')) {
                return false;
            }

            return str_contains($request['recipient'], 'u_psid')
                && json_decode($request['message'], true)['text'] === 'Hola! Gracias por escribir.'
                && $request['messaging_type'] === 'RESPONSE';
        });

        $this->assertNull(Cache::get("facebook_conversations:{$this->org->id}"));
        $this->assertNull(Cache::get("facebook_messages:{$this->org->id}:t_1.111"));
    }

    public function test_facebook_messages_send_requires_message(): void
    {
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook/messages?conversation=t_1.111')
            ->post('/facebook/messages/send', [
                'conversation_id' => 't_1.111',
                'organization_id' => $this->org->id,
            ])
            ->assertSessionHasErrors('message');
    }

    public function test_facebook_messages_send_graph_error_sets_flash(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'error' => ['message' => '(#200) Permission denied', 'type' => 'OAuthException'],
                ], 403);
            }

            return $this->pageInfoResponse();
        });
        $this->createConnection();

        $this->actingAs($this->admin)
            ->from('/facebook/messages?conversation=t_1.111')
            ->post('/facebook/messages/send', [
                'conversation_id' => 't_1.111',
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect('/facebook/messages?conversation=t_1.111')
            ->assertSessionHas('error');
    }

    public function test_facebook_messages_requires_empresa_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get('/facebook/messages')->assertForbidden();

        $this->actingAs($employee)
            ->post('/facebook/messages/send', [
                'conversation_id' => 't_1.111',
                'message' => 'Hola',
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();
    }
}
