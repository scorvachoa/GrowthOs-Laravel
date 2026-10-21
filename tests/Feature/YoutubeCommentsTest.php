<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Services\YouTube\CommentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class YoutubeCommentsTest extends TestCase
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

    private function sampleThread(Channel $channel): array
    {
        return [
            'id' => 'comment-1',
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'channel_color' => $channel->color,
            'video_id' => 'aaaaaaaaaaa',
            'video_title' => 'Video de prueba',
            'video_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            'author' => 'Ana',
            'avatar' => 'https://example.com/avatar.jpg',
            'text' => '¡Gran video!',
            'published_at' => now()->subDay()->toIso8601String(),
            'likes' => 5,
            'total_replies' => 1,
            'replies' => [],
        ];
    }

    public function test_comments_page_requires_view_youtube_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get('/youtube/comments')->assertStatus(403);
    }

    public function test_comments_page_renders_threads_for_connected_channels(): void
    {
        $channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'youtube_channel_id' => 'UC123',
        ]);

        $this->mock(CommentsService::class, function (MockInterface $mock) use ($channel) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('channelAvatars')
                ->andReturn(['UC123' => 'https://example.com/thumb.jpg']);
            $mock->shouldReceive('channelThreads')
                ->once()
                ->withArgs(fn (Channel $c) => $c->id === $channel->id)
                ->andReturn([$this->sampleThread($channel)]);
        });

        $this->actingAs($this->admin)
            ->get('/youtube/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Youtube/Comments')
                ->has('channels', 1)
                ->where('channels.0.name', $channel->name)
                ->where('channels.0.thumbnail', 'https://example.com/thumb.jpg')
                ->has('threads', 1)
                ->where('threads.0.author', 'Ana')
                ->where('threads.0.video_title', 'Video de prueba')
                ->where('api_connected', true));
    }

    public function test_channels_without_id_or_from_other_orgs_are_excluded(): void
    {
        Channel::factory()->create(['organization_id' => $this->org->id]);
        $otherOrg = Organization::factory()->create();
        Channel::factory()->create([
            'organization_id' => $otherOrg->id,
            'youtube_channel_id' => 'UCOTHER',
        ]);
        $mine = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'youtube_channel_id' => 'UCMINE',
        ]);

        $this->mock(CommentsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('channelAvatars')->andReturn([]);
            $mock->shouldReceive('channelThreads')->once()->andReturn([]);
        });

        $this->actingAs($this->admin)
            ->get('/youtube/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('channels', 1)
                ->where('channels.0.name', $mine->name)
                ->has('threads', 0));
    }

    public function test_only_threads_from_the_last_seven_days_are_shown(): void
    {
        $channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'youtube_channel_id' => 'UC123',
        ]);

        $recent = $this->sampleThread($channel);
        $old = $this->sampleThread($channel);
        $old['id'] = 'comment-old';
        $old['author'] = 'Old';
        $old['published_at'] = now()->subDays(10)->toIso8601String();

        $this->mock(CommentsService::class, function (MockInterface $mock) use ($channel, $recent, $old) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('channelAvatars')->andReturn([]);
            $mock->shouldReceive('channelThreads')
                ->once()
                ->withArgs(fn (Channel $c) => $c->id === $channel->id)
                ->andReturn([$old, $recent]);
        });

        $this->actingAs($this->admin)
            ->get('/youtube/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Youtube/Comments')
                ->has('threads', 1)
                ->where('threads.0.author', 'Ana'));
    }

    public function test_comments_page_handles_missing_api_key(): void
    {
        Channel::factory()->create([
            'organization_id' => $this->org->id,
            'youtube_channel_id' => 'UC123',
        ]);

        $this->mock(CommentsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturn(false);
            $mock->shouldReceive('channelAvatars')->andReturn([]);
            $mock->shouldReceive('channelThreads')->once()->andReturn([]);
        });

        $this->actingAs($this->admin)
            ->get('/youtube/comments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('channels', 1)
                ->has('threads', 0)
                ->where('api_connected', false));
    }
}
