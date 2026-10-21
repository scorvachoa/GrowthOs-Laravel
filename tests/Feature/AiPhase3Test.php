<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\User;
use App\Services\AI\AIContentService;
use App\Services\AI\DeepLService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPhase3Test extends TestCase
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

    public function test_translate_requires_ai_permission(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->postJson('/tasks/translate', ['target_lang' => 'en', 'title' => 'Hola'])
            ->assertForbidden();
    }

    public function test_translate_returns_translated_fields(): void
    {
        $this->mock(DeepLService::class, function ($mock) {
            $mock->shouldReceive('supportedTargets')
                ->andReturn(['es', 'en', 'pt', 'fr', 'de', 'it', 'ja', 'ko', 'zh']);
            $mock->shouldReceive('translate')
                ->once()
                ->andReturn(['Hello world title', 'Hello world script']);
        });

        $this->actingAs($this->admin)
            ->postJson('/tasks/translate', [
                'target_lang' => 'en',
                'title' => 'Título del mundo',
                'script' => 'Guion del mundo',
            ])
            ->assertOk()
            ->assertJson([
                'title' => 'Hello world title',
                'script' => 'Hello world script',
            ]);
    }

    public function test_translate_rejects_empty_content(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/tasks/translate', ['target_lang' => 'en'])
            ->assertStatus(422);
    }

    public function test_ideas_generate_requires_create_and_ai_permissions(): void
    {
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($employee)
            ->postJson('/ideas/generate', ['channel_id' => $channel->id])
            ->assertForbidden();
    }

    public function test_ideas_generate_creates_ideas(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id, 'name' => 'Canal Viajes']);

        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldReceive('generateIdeas')
                ->once()
                ->with('Canal Viajes', [], 10)
                ->andReturn(['Idea nueva uno de prueba', 'Idea nueva dos de prueba']);
        });

        $this->actingAs($this->admin)
            ->postJson('/ideas/generate', ['channel_id' => $channel->id])
            ->assertCreated()
            ->assertJson(['created' => 2]);

        $this->assertDatabaseHas('ideas', [
            'channel_id' => $channel->id,
            'content' => 'Idea nueva uno de prueba',
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_weekly_command_generates_ideas_for_channels(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id, 'name' => 'Canal Cron']);

        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldReceive('generateIdeas')
                ->once()
                ->withArgs(fn ($name) => $name === 'Canal Cron')
                ->andReturn(['Idea del cron uno', 'Idea del cron dos', 'Idea del cron tres']);
        });

        $this->artisan('ideas:generate', ['--count' => 3])->assertSuccessful();

        $this->assertSame(3, Idea::query()->where('channel_id', $channel->id)->count());
        $this->assertDatabaseHas('ideas', [
            'channel_id' => $channel->id,
            'content' => 'Idea del cron uno',
            'organization_id' => $this->org->id,
            'created_by' => null,
        ]);
    }

    public function test_weekly_command_skips_channels_with_enough_pending_ideas(): void
    {
        $channel = Channel::factory()->create(['organization_id' => $this->org->id]);
        Idea::factory()->count(3)->create([
            'channel_id' => $channel->id,
            'organization_id' => $this->org->id,
            'is_used' => false,
        ]);

        $this->mock(AIContentService::class, function ($mock) {
            $mock->shouldNotReceive('generateIdeas');
        });

        $this->artisan('ideas:generate', ['--count' => 3, '--max-pending' => 3])->assertSuccessful();

        $this->assertSame(3, Idea::query()->where('channel_id', $channel->id)->count());
    }
}
