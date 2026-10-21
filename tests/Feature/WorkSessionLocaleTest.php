<?php

namespace Tests\Feature;

use App\Http\Resources\VideoTaskResource;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkSessionLocaleTest extends TestCase
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

    private function monday(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->toDateString();
    }

    private function task(): VideoTask
    {
        return VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => $this->monday(),
        ]);
    }

    public function test_store_session_defaults_locale_to_es(): void
    {
        $task = $this->task();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$task->id.'/sessions', [
            'date' => $this->monday(),
        ]);

        $response->assertOk()->assertJsonPath('session.locale', 'es');

        $session = WorkSession::latest('id')->firstOrFail();
        $this->assertSame('es', $session->locale);
    }

    public function test_store_session_defaults_locale_from_task(): void
    {
        $channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'locales' => ['en'],
        ]);
        $task = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => $this->monday(),
            'channel_id' => $channel->id,
            'locale' => 'en',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$task->id.'/sessions', [
            'date' => $this->monday(),
        ]);

        $response->assertOk()->assertJsonPath('session.locale', 'en');
    }

    public function test_store_session_accepts_supported_locale(): void
    {
        $task = $this->task();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$task->id.'/sessions', [
            'date' => $this->monday(),
            'locale' => 'pt',
        ]);

        $response->assertOk()->assertJsonPath('session.locale', 'pt');

        $this->assertDatabaseHas('work_sessions', [
            'video_task_id' => $task->id,
            'locale' => 'pt',
        ]);
    }

    public function test_store_session_rejects_unsupported_locale(): void
    {
        $task = $this->task();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$task->id.'/sessions', [
            'date' => $this->monday(),
            'locale' => 'xx',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('locale is invalid', $response->getContent());
        $this->assertDatabaseCount('work_sessions', 0);
    }

    public function test_update_session_changes_locale(): void
    {
        $this->actingAs($this->admin);
        $task = $this->task();
        $session = $task->sessions()->create([
            'date' => $this->monday(),
            'time_range' => null,
            'status' => 'in_progress',
            'locale' => 'es',
        ]);

        $response = $this->patchJson('/tasks/'.$task->id.'/sessions/'.$session->id, [
            'status' => 'in_progress',
            'locale' => 'fr',
        ]);

        $response->assertOk()->assertJsonPath('session.locale', 'fr');
        $this->assertSame('fr', $session->fresh()->locale);
    }

    public function test_resource_exposes_session_locale(): void
    {
        $task = $this->task();
        $task->sessions()->create([
            'date' => $this->monday(),
            'time_range' => null,
            'status' => 'in_progress',
            'locale' => 'pt',
        ]);

        $resource = VideoTaskResource::make($task->fresh()->load('sessions'))->toArray(request());

        $this->assertCount(1, $resource['sessions']);
        $this->assertSame('pt', $resource['sessions'][0]['locale']);
    }

    public function test_completing_session_saves_url_on_task_regardless_of_locale(): void
    {
        $this->actingAs($this->admin);
        $task = $this->task();
        $task->update(['youtube_url' => 'https://youtube.com/watch?v=es']);
        $session = $task->sessions()->create([
            'date' => $this->monday(),
            'time_range' => null,
            'status' => 'in_progress',
            'locale' => 'en',
            'organization_id' => $this->org->id,
        ]);

        $this->patchJson('/tasks/'.$task->id.'/sessions/'.$session->id, [
            'status' => 'completed',
            'youtube_url' => 'https://youtube.com/watch?v=en',
        ])->assertOk();

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame('https://youtube.com/watch?v=en', $task->fresh()->youtube_url);
        $this->assertFalse(Schema::hasTable('video_task_translations'));
    }

    public function test_completing_es_session_saves_url_on_task_column(): void
    {
        $this->actingAs($this->admin);
        $task = $this->task();
        $task->update(['youtube_url' => 'https://youtube.com/watch?v=es-original']);
        $session = $task->sessions()->create([
            'date' => $this->monday(),
            'time_range' => null,
            'status' => 'in_progress',
            'locale' => 'es',
            'organization_id' => $this->org->id,
        ]);

        $this->patchJson('/tasks/'.$task->id.'/sessions/'.$session->id, [
            'status' => 'completed',
            'youtube_url' => 'https://youtube.com/watch?v=es-nuevo',
        ])->assertOk();

        $this->assertSame('https://youtube.com/watch?v=es-nuevo', $task->fresh()->youtube_url);
    }

    public function test_tasks_by_date_exposes_session_locale_and_task_data(): void
    {
        $this->actingAs($this->admin);
        $task = $this->task();
        $task->update(['youtube_url' => 'https://youtube.com/watch?v=es']);

        $sessionDate = Carbon::parse($this->monday())->addDay()->toDateString();
        $session = $task->sessions()->create([
            'date' => $sessionDate,
            'time_range' => null,
            'status' => 'in_progress',
            'locale' => 'en',
            'organization_id' => $this->org->id,
        ]);

        $response = $this->getJson('/planning/tasks?fecha='.$sessionDate);

        $response->assertOk();
        $row = collect($response->json())->firstWhere('session_id', $session->id);

        $this->assertNotNull($row);
        $this->assertSame('en', $row['locale']);
        $this->assertSame($task->id, $row['id']);
        $this->assertSame('https://youtube.com/watch?v=es', $row['youtube_url'] ?? null);
    }
}
