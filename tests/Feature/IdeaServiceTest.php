<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Idea;
use App\Models\User;
use App\Services\IdeaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdeaService $service;

    private User $user;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->service = new IdeaService;
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
        $this->actingAs($this->user);

        $this->channel = Channel::factory()->create([
            'organization_id' => $this->user->organization_id,
        ]);
    }

    public function test_list_returns_ideas_for_channel(): void
    {
        Idea::factory()->count(3)->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $result = $this->service->list($this->channel->id);

        $this->assertCount(3, $result->items());
    }

    public function test_list_filters_by_search(): void
    {
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Video tutorial de Laravel',
        ]);
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Review de producto nuevo',
        ]);

        $result = $this->service->list($this->channel->id, 'tutorial');

        $this->assertCount(1, $result->items());
        $this->assertStringContainsString('tutorial', $result->items()[0]->content);
    }

    public function test_list_filters_by_status_used(): void
    {
        Idea::factory()->used()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $result = $this->service->list($this->channel->id, '', 'date_desc', 'used');

        $this->assertCount(1, $result->items());
        $this->assertTrue($result->items()[0]->is_used);
    }

    public function test_list_filters_by_status_pending(): void
    {
        Idea::factory()->used()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $result = $this->service->list($this->channel->id, '', 'date_desc', 'pending');

        $this->assertCount(1, $result->items());
        $this->assertFalse($result->items()[0]->is_used);
    }

    public function test_list_sorts_alpha_asc(): void
    {
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Zebra',
        ]);
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Alpha',
        ]);

        $result = $this->service->list($this->channel->id, '', 'alpha_asc');

        $this->assertEquals('Alpha', $result->items()[0]->content);
    }

    public function test_create_bulk_inserts_ideas(): void
    {
        $lines = ['Idea 1', 'Idea 2', 'Idea 3'];

        $count = $this->service->createBulk($this->channel->id, $lines);

        $this->assertEquals(3, $count);
        $this->assertDatabaseCount('ideas', 3);
    }

    public function test_create_bulk_skips_empty_lines(): void
    {
        $lines = ['Idea 1', '', '  ', 'Idea 2'];

        $count = $this->service->createBulk($this->channel->id, $lines);

        $this->assertEquals(2, $count);
    }

    public function test_create_bulk_sets_organization(): void
    {
        $this->service->createBulk($this->channel->id, ['Test idea']);

        $idea = Idea::first();
        $this->assertEquals($this->user->organization_id, $idea->organization_id);
    }

    public function test_toggle_used(): void
    {
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'is_used' => false,
        ]);

        $this->service->toggleUsed($idea, true);
        $idea->refresh();

        $this->assertTrue($idea->is_used);
    }

    public function test_delete_idea(): void
    {
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $this->service->delete($idea);

        $this->assertDatabaseMissing('ideas', ['id' => $idea->id]);
    }

    public function test_bulk_update_mark_used(): void
    {
        $ideas = Idea::factory()->count(3)->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $count = $this->service->bulkUpdate($ideas->pluck('id')->toArray(), 'mark_used');

        $this->assertEquals(3, $count);
        $this->assertCount(3, Idea::where('is_used', true)->where('organization_id', $this->user->organization_id)->get());
    }

    public function test_bulk_update_delete(): void
    {
        $ideas = Idea::factory()->count(2)->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
        ]);

        $count = $this->service->bulkUpdate($ideas->pluck('id')->toArray(), 'delete');

        $this->assertEquals(2, $count);
        $this->assertDatabaseCount('ideas', 0);
    }

    public function test_export_ideas_format(): void
    {
        Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Pending idea',
            'is_used' => false,
        ]);
        Idea::factory()->used()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->user->organization_id,
            'content' => 'Used idea',
        ]);

        $export = $this->service->exportIdeas($this->channel->id);

        $this->assertStringContainsString('Ideas pendientes', $export);
        $this->assertStringContainsString('Pending idea', $export);
        $this->assertStringContainsString('Ideas usadas', $export);
        $this->assertStringContainsString('Used idea', $export);
    }
}
