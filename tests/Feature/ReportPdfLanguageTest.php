<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPdfLanguageTest extends TestCase
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

    public function test_day_groups_labels_video_and_session_languages_from_task(): void
    {
        $this->actingAs($this->admin);

        $enChannel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'locales' => ['en'],
        ]);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $task = $this->makeTask('Video traducido', $week->toDateString(), $enChannel);
        $task->update(['youtube_url' => 'https://youtube.com/watch?v=en']);
        $task->sessions()->create([
            'date' => $week->copy()->addDay()->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'locale' => 'en',
            'organization_id' => $this->org->id,
        ]);

        $plain = $this->makeTask('Video solo español', $week->toDateString());

        $items = $this->dayItems($week->copy(), $week->copy()->addDays(7));

        $videoItem = collect($items)->firstWhere('title', 'Video traducido');
        $this->assertSame('video', $videoItem['type']);
        $this->assertArrayNotHasKey('locale_label', $videoItem);

        $plainItem = collect($items)->firstWhere('title', 'Video solo español');
        $this->assertSame('video', $plainItem['type']);

        $sessionItem = collect($items)->firstWhere('type', 'session');
        $this->assertNotNull($sessionItem);
        $this->assertSame('Video traducido', $sessionItem['title']);
        $this->assertArrayNotHasKey('locale_label', $sessionItem);
        $this->assertSame('https://youtube.com/watch?v=en', $sessionItem['youtube_url']);
    }

    public function test_session_without_url_does_not_fall_back_to_another_url(): void
    {
        $this->actingAs($this->admin);

        $enChannel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'locales' => ['en'],
        ]);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $task = $this->makeTask('Video sin link en ingles', $week->toDateString(), $enChannel);
        $task->sessions()->create([
            'date' => $week->copy()->addDay()->toDateString(),
            'time_range' => null,
            'status' => 'completed',
            'locale' => 'en',
            'organization_id' => $this->org->id,
        ]);

        $items = $this->dayItems($week->copy(), $week->copy()->addDays(7));
        $sessionItem = collect($items)->firstWhere('type', 'session');

        $this->assertNotNull($sessionItem);
        $this->assertNull($sessionItem['youtube_url']);
    }

    public function test_report_view_renders_no_language_line(): void
    {
        $this->actingAs($this->admin);

        $enChannel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'locales' => ['en'],
        ]);

        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $this->makeTask('Video en español', $week->toDateString());
        $this->makeTask('Video en ingles', $week->toDateString(), $enChannel);

        $start = $week->copy();
        $end = $week->copy()->addDays(7);
        $service = app(ReportService::class);
        $tasks = $service->loadTasksForRange($this->org->id, $start, $end);
        $extras = $service->loadExtraTasksForRange($this->org->id, $start, $end);
        $days = $service->buildDayGroups($this->org->id, $tasks, $extras, $start, $end);

        $html = view('pdf.report', [
            'title' => 'Reporte diario',
            'days' => $days,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'company' => ['name' => 'GrowthOS', 'primary_color' => '#4f46e5', 'logo_base64' => null],
            'systemName' => 'GrowthOS',
            'userName' => $this->admin->name,
        ])->render();

        $this->assertStringNotContainsString('<strong>Idioma:</strong>', $html);
        $this->assertStringNotContainsString('task-lang', $html);
    }

    public function test_pdf_export_endpoint_returns_pdf(): void
    {
        $week = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $this->makeTask('Video para el PDF', $week->toDateString());

        $response = $this->actingAs($this->admin)
            ->get('/task-reports/pdf?scope=dia&day='.$week->toDateString());

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    private function dayItems(Carbon $start, Carbon $end): array
    {
        $service = app(ReportService::class);
        $tasks = $service->loadTasksForRange($this->org->id, $start, $end);
        $extras = $service->loadExtraTasksForRange($this->org->id, $start, $end);
        $days = $service->buildDayGroups($this->org->id, $tasks, $extras, $start, $end);

        return collect($days)
            ->flatMap(fn ($day) => $day['tasks'])
            ->values()
            ->all();
    }

    private function makeTask(string $title, string $date, ?Channel $channel = null): VideoTask
    {
        $channel = $channel ?? $this->channel;

        return VideoTask::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $channel->id,
            'locale' => ($channel->locales ?: ['es'])[0],
            'title' => $title,
            'status' => 'editing',
            'task_date' => $date,
            'time_range' => '09:00-11:00',
            'is_pending' => false,
        ]);
    }
}
