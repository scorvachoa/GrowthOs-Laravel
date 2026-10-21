<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWeeklySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Admin');
        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id]);
    }

    public function test_weekly_context_marks_task_done_when_all_sessions_completed(): void
    {
        $this->actingAs($this->admin);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $done = $this->makeTask('Tarea con sesion terminada', 'editing', $week->toDateString());
        $done->sessions()->create([
            'date' => $week->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'organization_id' => $this->org->id,
        ]);

        $context = app(DashboardService::class)->weeklySummaryContext($this->admin);
        $row = collect($context['tareas_de_la_semana'])->firstWhere('titulo', $done->title);

        $this->assertSame('Completado', $row['estado']);
        $this->assertSame('Edición', $row['estado_tarea']);
        $this->assertSame('1/1 completadas', $row['sesiones']);

        $this->assertSame(1, $context['por_estado']['Completado'] ?? 0);
        $this->assertSame(1, $context['totales_semana']['completadas_por_sesion']);
        $this->assertSame(1, $context['totales_semana']['completadas_total']);
        $this->assertSame(1, $context['totales']['completadas']);
    }

    public function test_weekly_context_partial_sessions_keep_task_in_progress(): void
    {
        $this->actingAs($this->admin);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $partial = $this->makeTask('Tarea a medias', 'editing', $week->toDateString());
        $partial->sessions()->create([
            'date' => $week->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'organization_id' => $this->org->id,
        ]);
        $partial->sessions()->create([
            'date' => $week->copy()->addDay()->toDateString(),
            'time_range' => null,
            'status' => 'in_progress',
            'organization_id' => $this->org->id,
        ]);

        $context = app(DashboardService::class)->weeklySummaryContext($this->admin);
        $row = collect($context['tareas_de_la_semana'])->firstWhere('titulo', $partial->title);

        $this->assertSame('Edición', $row['estado']);
        $this->assertArrayNotHasKey('estado_tarea', $row);
        $this->assertSame('1/2 completadas', $row['sesiones']);
        $this->assertSame(0, $context['totales_semana']['completadas_por_sesion']);
        $this->assertSame(1, $context['por_estado']['Edición'] ?? 0);
    }

    public function test_weekly_context_marks_done_when_latest_session_completed(): void
    {
        $this->actingAs($this->admin);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $stale = $this->makeTask('Sesion antigua abierta', 'editing', $week->toDateString());
        $stale->sessions()->create([
            'date' => $week->toDateString(),
            'time_range' => null,
            'status' => 'in_progress',
            'organization_id' => $this->org->id,
        ]);
        $stale->sessions()->create([
            'date' => $week->copy()->addDay()->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'organization_id' => $this->org->id,
        ]);

        $context = app(DashboardService::class)->weeklySummaryContext($this->admin);
        $row = collect($context['tareas_de_la_semana'])->firstWhere('titulo', $stale->title);

        $this->assertSame('Completado', $row['estado']);
        $this->assertSame('1/2 completadas', $row['sesiones']);
        $this->assertSame(1, $context['totales_semana']['completadas_por_sesion']);
        $this->assertSame(1, $context['por_estado']['Completado'] ?? 0);
    }

    public function test_weekly_context_rows_omit_session_info_without_sessions(): void
    {
        $this->actingAs($this->admin);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $published = $this->makeTask('Publicada', 'published', $week->toDateString());
        $noSession = $this->makeTask('Sin sesiones', 'script_ready', $week->toDateString());

        $context = app(DashboardService::class)->weeklySummaryContext($this->admin);
        $rows = collect($context['tareas_de_la_semana']);

        $publishedRow = $rows->firstWhere('titulo', $published->title);
        $this->assertSame('Publicado', $publishedRow['estado']);
        $this->assertArrayNotHasKey('estado_tarea', $publishedRow);
        $this->assertArrayNotHasKey('sesiones', $publishedRow);

        $noSessionRow = $rows->firstWhere('titulo', $noSession->title);
        $this->assertSame('Guion listo', $noSessionRow['estado']);
        $this->assertArrayNotHasKey('sesiones', $noSessionRow);
    }

    public function test_weekly_context_totals_and_completion_pct_are_session_aware(): void
    {
        $this->actingAs($this->admin);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $done = $this->makeTask('Terminada por sesion', 'editing', $week->toDateString());
        $done->sessions()->create([
            'date' => $week->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'organization_id' => $this->org->id,
        ]);
        $this->makeTask('Publicada', 'published', $week->toDateString());
        $this->makeTask('En curso', 'editing', Carbon::today()->toDateString());
        $this->makeTask('Guion listo', 'script_ready', Carbon::today()->toDateString());
        $this->makeTask('Vencida', 'editing', $week->copy()->subDays(7)->toDateString());

        $context = app(DashboardService::class)->weeklySummaryContext($this->admin);

        $this->assertSame(5, $context['totales']['tareas_video']);
        $this->assertSame(3, $context['totales']['en_progreso']);
        $this->assertSame(2, $context['totales']['completadas']);
        $this->assertSame(1, $context['totales']['vencidas']);

        $this->assertSame(4, $context['totales_semana']['tareas']);
        $this->assertSame(1, $context['totales_semana']['publicadas']);
        $this->assertSame(1, $context['totales_semana']['completadas_por_sesion']);
        $this->assertSame(2, $context['totales_semana']['completadas_total']);
        $this->assertSame(2, $context['totales_semana']['en_progreso']);
        $this->assertSame(0, $context['totales_semana']['vencidas']);
        $this->assertSame(50, $context['cumplimiento_semana_pct']);

        $weekTitles = collect($context['tareas_de_la_semana'])->pluck('titulo');
        $this->assertTrue($weekTitles->contains('Terminada por sesion'));
        $this->assertFalse($weekTitles->contains('Vencida'));
    }

    private function makeTask(string $title, string $status, string $date): VideoTask
    {
        return VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $this->channel->id,
            'title' => $title,
            'status' => $status,
            'task_date' => $date,
            'time_range' => '09:00-11:00',
            'is_pending' => false,
        ]);
    }
}
