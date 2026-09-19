<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Organization $org;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->org = Organization::factory()->create();
        $this->superAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->superAdmin->assignRole('Super Admin');
        $this->actingAs($this->superAdmin);

        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_super_admin_can_view_idea(): void
    {
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($this->superAdmin->can('view', $idea));
    }

    public function test_super_admin_can_create_idea(): void
    {
        $this->assertTrue($this->superAdmin->can('create', Idea::class));
    }

    public function test_super_admin_can_update_idea(): void
    {
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($this->superAdmin->can('update', $idea));
    }

    public function test_super_admin_can_delete_idea(): void
    {
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($this->superAdmin->can('delete', $idea));
    }

    public function test_admin_can_view_idea_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($admin->can('view', $idea));
    }

    public function test_admin_cannot_view_idea_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $otherOrg->id,
        ]);

        $this->assertFalse($admin->can('view', $idea));
    }

    public function test_admin_can_update_idea_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($admin->can('update', $idea));
    }

    public function test_admin_cannot_update_idea_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $otherOrg->id,
        ]);

        $this->assertFalse($admin->can('update', $idea));
    }

    public function test_admin_can_delete_idea_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $this->org->id,
        ]);

        $this->assertTrue($admin->can('delete', $idea));
    }

    public function test_admin_cannot_delete_idea_in_different_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        $idea = Idea::factory()->create([
            'channel_id' => $this->channel->id,
            'organization_id' => $otherOrg->id,
        ]);

        $this->assertFalse($admin->can('delete', $idea));
    }
}
