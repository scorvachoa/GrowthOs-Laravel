<?php

namespace Tests\Feature;

use App\Enums\VideoTaskStatus;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningFiltersTest extends TestCase
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

    public function test_today_summary_returns_today_and_overdue_counts(): void
    {
        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
            'status' => VideoTaskStatus::Pending->value,
            'is_pending' => false,
        ]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(3),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);

        // Publicada ayer: no cuenta como vencida.
        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDay(),
            'status' => VideoTaskStatus::Published->value,
            'is_pending' => false,
        ]);

        // En bandeja de pendientes (is_pending): no cuenta en ninguno de los dos contadores.
        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
            'is_pending' => true,
        ]);

        // Todas las sesiones completadas: no cuenta como vencida aunque el status no sea 'published'.
        $doneTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(5),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $doneTask->id,
            'organization_id' => $this->org->id,
            'date' => $doneTask->task_date,
            'status' => 'completed',
        ]);

        // Sesiones a medias (una completada y otra pendiente): sigue contando como vencida.
        $partialTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(4),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $partialTask->id,
            'organization_id' => $this->org->id,
            'date' => $partialTask->task_date,
            'status' => 'completed',
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $partialTask->id,
            'organization_id' => $this->org->id,
            'date' => $partialTask->task_date->copy()->addDay(),
            'time_range' => '11:00-13:00',
            'status' => 'pending',
        ]);

        // Sesión antigua sin cerrar pero la más reciente completada: NO cuenta como vencida.
        $staleTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(6),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $staleTask->id,
            'organization_id' => $this->org->id,
            'date' => $staleTask->task_date,
            'status' => 'in_progress',
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $staleTask->id,
            'organization_id' => $this->org->id,
            'date' => $staleTask->task_date->copy()->addDay(),
            'time_range' => '11:00-13:00',
            'status' => 'completed',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/tasks/today-summary')
            ->assertOk()
            ->assertJson(['today' => 1, 'overdue' => 2]);
    }

    public function test_today_summary_requires_view_planning_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->getJson('/tasks/today-summary')
            ->assertForbidden();
    }

    public function test_today_tasks_returns_today_and_overdue_lists(): void
    {
        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
            'status' => VideoTaskStatus::Pending->value,
            'is_pending' => false,
        ]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(5),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);

        // Todas las sesiones completadas: fuera de la lista de vencidas.
        $doneTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today()->subDays(3),
            'status' => VideoTaskStatus::Editing->value,
            'is_pending' => false,
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $doneTask->id,
            'organization_id' => $this->org->id,
            'date' => $doneTask->task_date,
            'status' => 'completed',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/tasks/today-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'today')
            ->assertJsonCount(1, 'overdue')
            ->assertJsonPath('overdue.0.days_overdue', 5);
    }

    public function test_today_tasks_requires_view_planning_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->getJson('/tasks/today-tasks')
            ->assertForbidden();
    }

    public function test_snapshot_filters_by_channel(): void
    {
        $channelA = Channel::factory()->create(['organization_id' => $this->org->id]);
        $channelB = Channel::factory()->create(['organization_id' => $this->org->id]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $channelA->id,
            'task_date' => Carbon::today(),
        ]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $channelB->id,
            'task_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/planning/calendar/snapshot?year='.Carbon::today()->year.'&month='.Carbon::today()->month.'&channel_id='.$channelA->id
        );

        $response->assertOk();

        $tasks = collect($response->json('tasks_detail_map'))->flatten(1);
        $this->assertCount(1, $tasks);
        $this->assertSame($channelA->id, $tasks->first()['channel_id']);
    }

    public function test_snapshot_filters_by_owner_mine(): void
    {
        $otherUser = User::factory()->create(['organization_id' => $this->org->id]);
        $otherUser->assignRole('Admin');

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
        ]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $otherUser->id,
            'task_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/planning/calendar/snapshot?year='.Carbon::today()->year.'&month='.Carbon::today()->month.'&owner=mine'
        );

        $response->assertOk();

        $tasks = collect($response->json('tasks_detail_map'))->flatten(1);
        $this->assertCount(1, $tasks);
        $this->assertSame($this->admin->id, $tasks->first()['created_by']);
    }

    public function test_snapshot_filters_by_status(): void
    {
        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
            'status' => VideoTaskStatus::Pending->value,
        ]);

        VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'task_date' => Carbon::today(),
            'status' => VideoTaskStatus::Scheduled->value,
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/planning/calendar/snapshot?year='.Carbon::today()->year.'&month='.Carbon::today()->month.'&status=scheduled'
        );

        $response->assertOk();

        $tasks = collect($response->json('tasks_detail_map'))->flatten(1);
        $this->assertCount(1, $tasks);
        $this->assertSame('scheduled', $tasks->first()['status']);
    }

    public function test_index_returns_channels_for_filters(): void
    {
        Channel::factory()->create(['organization_id' => $this->org->id, 'name' => 'Canal Uno']);

        $this->actingAs($this->admin)
            ->get('/planning')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planning/Index')
                ->has('channels', 1)
                ->where('channels.0.name', 'Canal Uno'));
    }

    public function test_snapshot_serializes_month_and_orphan_sessions_with_channel(): void
    {
        $monthStart = Carbon::today()->startOfMonth();
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);

        // Sesión del mes de una tarea dentro del mes.
        $innerTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $channel->id,
            'task_date' => $monthStart->copy()->addDays(9),
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $innerTask->id,
            'organization_id' => $this->org->id,
            'date' => $monthStart->copy()->addDays(11),
            'time_range' => '10:00-12:00',
            'status' => 'completed',
        ]);

        // Sesión huérfana: tarea del mes siguiente con sesión dentro del mes.
        $outerTask = VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $channel->id,
            'task_date' => $monthStart->copy()->addMonth()->addDays(9),
        ]);
        WorkSession::factory()->create([
            'video_task_id' => $outerTask->id,
            'organization_id' => $this->org->id,
            'date' => $monthStart->copy()->addDays(13),
            'time_range' => '10:00-12:00',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/planning/calendar/snapshot?year='.$monthStart->year.'&month='.$monthStart->month
        );

        $response->assertOk();

        $entries = collect($response->json('tasks_detail_map'))->flatten(1);
        $sessionEntries = $entries->where('time_range', '10:00-12:00');

        $this->assertCount(2, $sessionEntries);
        foreach ($sessionEntries as $entry) {
            $this->assertArrayHasKey('channel_id', $entry);
            $this->assertSame($channel->id, $entry['channel_id']);
            $this->assertSame($this->admin->id, $entry['created_by']);
        }
    }
}
