<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\AI\AIContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiPhase2Test extends TestCase
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

    public function test_generate_title_thumbnails_requires_ai_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->postJson('/tasks/generate-title-thumbnails', ['script' => str_repeat('guion de prueba ', 10)])
            ->assertForbidden();
    }

    public function test_generate_title_thumbnails_returns_titles_and_thumbnails(): void
    {
        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldReceive('generateTitlesAndThumbnails')
                ->once()
                ->andReturn([
                    'titles' => ['Título uno', 'Título dos'],
                    'thumbnails' => ['Idea de thumbnail uno'],
                ]);
        });

        $this->actingAs($this->admin)
            ->postJson('/tasks/generate-title-thumbnails', [
                'script' => str_repeat('guion de prueba ', 10),
                'title' => 'Título actual',
            ])
            ->assertCreated()
            ->assertJson([
                'titles' => ['Título uno', 'Título dos'],
                'thumbnails' => ['Idea de thumbnail uno'],
            ]);
    }

    public function test_weekly_summary_requires_view_dashboard_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->getJson('/dashboard/weekly-summary')
            ->assertForbidden();
    }

    public function test_weekly_summary_generates_once_and_caches_per_week(): void
    {
        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldReceive('generateWeeklySummary')
                ->once()
                ->andReturn("RESUMEN GENERAL:\nTodo bien.\n\nLO QUE FUNCIONO:\n- Publicaciones");
        });

        $first = $this->actingAs($this->admin)->getJson('/dashboard/weekly-summary');
        $first->assertCreated()->assertJsonPath('summary', "RESUMEN GENERAL:\nTodo bien.\n\nLO QUE FUNCIONO:\n- Publicaciones");

        $second = $this->actingAs($this->admin)->getJson('/dashboard/weekly-summary');
        $second->assertOk()->assertJsonPath('summary', "RESUMEN GENERAL:\nTodo bien.\n\nLO QUE FUNCIONO:\n- Publicaciones");
    }

    public function test_weekly_summary_cache_key_is_per_organization(): void
    {
        Cache::flush();

        $otherOrg = Organization::factory()->create();
        $otherAdmin = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherAdmin->assignRole('Admin');

        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldReceive('generateWeeklySummary')
                ->twice()
                ->andReturn('Resumen');
        });

        $this->actingAs($this->admin)->getJson('/dashboard/weekly-summary')->assertCreated();
        $this->actingAs($otherAdmin)->getJson('/dashboard/weekly-summary')->assertCreated();
    }
}
