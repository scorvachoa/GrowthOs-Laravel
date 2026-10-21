<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoTaskTranslateTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Channel $esChannel;

    private Channel $enChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Admin');
        $this->esChannel = Channel::factory()->create(['organization_id' => $this->org->id, 'locales' => ['es']]);
        $this->enChannel = Channel::factory()->create(['organization_id' => $this->org->id, 'locales' => ['en']]);

        config(['services.deepl.api_key' => 'test-key']);
    }

    private function monday(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->toDateString();
    }

    private function sourceTask(array $attrs = []): VideoTask
    {
        return VideoTask::factory()->create(array_merge([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'channel_id' => $this->esChannel->id,
            'title' => 'Tarea original',
            'script' => str_repeat('Guion original. ', 10),
            'copy' => 'Copy original',
            'status' => 'editing',
            'task_date' => $this->monday(),
            'time_range' => '09:00-11:00',
            'is_pending' => false,
        ], $attrs));
    }

    private function fakeDeepL(): void
    {
        Http::fake(['*api.deepl.com*' => Http::response([
            'translations' => [
                ['text' => 'Translated title'],
                ['text' => 'Translated script'],
                ['text' => 'Translated copy'],
            ],
        ], 200)]);
    }

    public function test_translate_task_creates_linked_sister_task(): void
    {
        $this->fakeDeepL();
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$source->id.'/translate', [
            'locale' => 'en',
            'channel_id' => $this->enChannel->id,
            'task_date' => $this->monday(),
            'time_range' => '14:00-16:00',
        ]);

        $response->assertStatus(201);
        $taskId = $response->json('task_id');
        $this->assertNotNull($taskId);
        $this->assertSame('/tasks/'.$taskId, $response->json('redirect'));

        $new = VideoTask::findOrFail($taskId);
        $this->assertSame($this->enChannel->id, $new->channel_id);
        $this->assertSame('en', $new->locale);
        $this->assertSame('Translated title', $new->title);
        $this->assertSame('Translated script', $new->script);
        $this->assertSame('Translated copy', $new->copy);
        $this->assertNull($new->youtube_url);
        $this->assertSame('pending', $new->status);
        $this->assertSame($source->id, $new->translated_from_task_id);
        $this->assertNotNull($new->translation_group_id);

        $source->refresh();
        $this->assertSame($new->translation_group_id, $source->translation_group_id);
        $this->assertSame('Tarea original', $source->title);

        Http::assertSentCount(1);
    }

    public function test_translate_task_rejects_channel_language_mismatch(): void
    {
        $this->fakeDeepL();
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$source->id.'/translate', [
            'locale' => 'fr',
            'channel_id' => $this->enChannel->id,
            'task_date' => $this->monday(),
            'time_range' => '14:00-16:00',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no corresponde al idioma', $response->json('error'));
        $this->assertSame(1, VideoTask::count());
        Http::assertNothingSent();
    }

    public function test_translate_task_rejects_occupied_slot(): void
    {
        $this->fakeDeepL();
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$source->id.'/translate', [
            'locale' => 'en',
            'channel_id' => $this->enChannel->id,
            'task_date' => $this->monday(),
            'time_range' => '09:00-11:00',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, VideoTask::count());
    }

    public function test_translate_task_requires_permissions(): void
    {
        $this->fakeDeepL();
        $source = $this->sourceTask();
        $employee = User::factory()->create(['organization_id' => $this->org->id]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->postJson('/tasks/'.$source->id.'/translate', [
                'locale' => 'en',
                'channel_id' => $this->enChannel->id,
                'task_date' => $this->monday(),
                'time_range' => '14:00-16:00',
            ])
            ->assertStatus(403);

        $this->assertSame(1, VideoTask::count());
    }

    public function test_translate_task_fails_when_deepl_errors(): void
    {
        Http::fake(['*api.deepl.com*' => Http::response('boom', 500)]);
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$source->id.'/translate', [
            'locale' => 'en',
            'channel_id' => $this->enChannel->id,
            'task_date' => $this->monday(),
            'time_range' => '14:00-16:00',
        ]);

        $response->assertStatus(502);
        $this->assertStringContainsString('DeepL', $response->json('error'));
        $this->assertSame(1, VideoTask::count());
    }

    public function test_translate_content_returns_translated_fields(): void
    {
        $this->fakeDeepL();
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->postJson('/tasks/'.$source->id.'/translate-content', [
            'locale' => 'en',
        ]);

        $response->assertOk();
        $this->assertSame('Translated title', $response->json('title'));
        $this->assertSame('Translated script', $response->json('script'));
        $this->assertSame('Translated copy', $response->json('copy'));

        $source->refresh();
        $this->assertSame('Tarea original', $source->title);
        $this->assertSame('Copy original', $source->copy);
    }

    public function test_translate_candidates_returns_tasks_and_excludes_pending(): void
    {
        $source = $this->sourceTask();
        $this->sourceTask([
            'title' => 'Pendiente oculto',
            'is_pending' => true,
            'time_range' => '11:00-13:00',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/tasks/translate-candidates?q=original');

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame($source->id, $results[0]['id']);
        $this->assertSame('Tarea original', $results[0]['title']);
        $this->assertSame('es', $results[0]['locale']);
    }

    public function test_store_links_translated_from_task(): void
    {
        $source = $this->sourceTask();

        $response = $this->actingAs($this->admin)->post('/tasks', [
            'task_date' => $this->monday(),
            'time_range' => '14:00-16:00',
            'title' => 'Copia enlazada',
            'status' => 'pending',
            'channel_id' => $this->enChannel->id,
            'translated_from_task_id' => $source->id,
        ]);

        $response->assertRedirect();

        $new = VideoTask::where('title', 'Copia enlazada')->firstOrFail();
        $this->assertSame($source->id, $new->translated_from_task_id);
        $this->assertNotNull($new->translation_group_id);

        $source->refresh();
        $this->assertSame($new->translation_group_id, $source->translation_group_id);
    }

    public function test_show_returns_translation_history(): void
    {
        $source = $this->sourceTask();
        $group = (string) Str::uuid();
        $source->update(['translation_group_id' => $group]);
        $sister = $this->sourceTask([
            'title' => 'Hermana en ingles',
            'channel_id' => $this->enChannel->id,
            'locale' => 'en',
            'time_range' => '11:00-13:00',
            'translation_group_id' => $group,
            'translated_from_task_id' => $source->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/tasks/'.$source->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VideoTasks/Show')
                ->has('translation_history', 2)
                ->where('translation_history.0.id', $source->id)
                ->where('translation_history.0.is_original', true)
                ->where('translation_history.0.is_self', true)
                ->where('translation_history.1.id', $sister->id)
                ->where('translation_history.1.locale', 'en')
                ->where('translation_history.1.is_original', false)
                ->where('translation_history.1.is_self', false));
    }

    public function test_show_returns_empty_history_for_unlinked_task(): void
    {
        $source = $this->sourceTask();

        $this->actingAs($this->admin)
            ->get('/tasks/'.$source->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VideoTasks/Show')
                ->has('translation_history', 0));
    }
}
