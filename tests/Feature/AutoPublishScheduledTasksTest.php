<?php

namespace Tests\Feature;

use App\Enums\VideoTaskStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use App\Services\YouTube\YouTubeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AutoPublishScheduledTasksTest extends TestCase
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

    private function createScheduledTask(array $overrides = []): VideoTask
    {
        return VideoTask::factory()->create(array_merge([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(2),
            'status' => VideoTaskStatus::Scheduled->value,
            'is_pending' => false,
            'youtube_url' => 'https://www.youtube.com/shorts/aaaaaaaaaaa',
        ], $overrides));
    }

    public function test_command_publishes_tasks_whose_video_is_already_live(): void
    {
        $live = $this->createScheduledTask();
        $waiting = $this->createScheduledTask(['youtube_url' => 'https://www.youtube.com/shorts/bbbbbbbbbbb']);

        $this->mock(YouTubeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('liveVideoIds')->once()->andReturn(['aaaaaaaaaaa']);
        });

        $this->artisan('tasks:publish-scheduled')->assertSuccessful();

        $this->assertSame(VideoTaskStatus::Published->value, $live->fresh()->status);
        $this->assertSame(VideoTaskStatus::Scheduled->value, $waiting->fresh()->status);
        $this->assertTrue($live->activities()->where('description', 'Publicado automáticamente')->exists());
    }

    public function test_command_skips_tasks_without_a_usable_youtube_url(): void
    {
        $withoutUrl = $this->createScheduledTask(['youtube_url' => null]);
        $otherPlatform = $this->createScheduledTask(['youtube_url' => 'https://www.tiktok.com/@user/video/7123456789']);

        $this->mock(YouTubeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('liveVideoIds')->never();
        });

        $this->artisan('tasks:publish-scheduled')->assertSuccessful();

        $this->assertSame(VideoTaskStatus::Scheduled->value, $withoutUrl->fresh()->status);
        $this->assertSame(VideoTaskStatus::Scheduled->value, $otherPlatform->fresh()->status);
    }

    public function test_command_does_nothing_when_api_key_is_missing(): void
    {
        $task = $this->createScheduledTask();

        $this->mock(YouTubeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturn(false);
            $mock->shouldReceive('liveVideoIds')->never();
        });

        $this->artisan('tasks:publish-scheduled')->assertSuccessful();

        $this->assertSame(VideoTaskStatus::Scheduled->value, $task->fresh()->status);
    }

    public function test_today_summary_does_not_trigger_auto_publish(): void
    {
        $task = $this->createScheduledTask();

        $this->mock(YouTubeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->never();
            $mock->shouldReceive('liveVideoIds')->never();
        });

        $this->actingAs($this->admin)
            ->getJson('/tasks/today-summary')
            ->assertOk();

        $this->assertSame(VideoTaskStatus::Scheduled->value, $task->fresh()->status);
    }
}
