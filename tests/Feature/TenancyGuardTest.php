<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenancyGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    private VideoTask $taskA;

    private Idea $ideaA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->adminA = User::factory()->create(['organization_id' => $this->orgA->id]);
        $this->adminA->assignRole('Admin');
        $this->adminB = User::factory()->create(['organization_id' => $this->orgB->id]);
        $this->adminB->assignRole('Admin');

        $this->taskA = VideoTask::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->adminA->id,
        ]);
        $this->ideaA = Idea::factory()->create([
            'organization_id' => $this->orgA->id,
        ]);
    }

    public function test_user_without_organization_sees_no_scoped_data(): void
    {
        $orphan = User::factory()->create(['organization_id' => null]);

        $this->actingAs($orphan);

        $this->assertSame(0, VideoTask::count());
        $this->assertNull(VideoTask::find($this->taskA->id));
        $this->assertSame(0, Idea::count());
        $this->assertNull(Idea::find($this->ideaA->id));
    }

    public function test_super_admin_without_organization_keeps_global_read_access(): void
    {
        $superAdmin = User::factory()->create(['organization_id' => null]);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin);

        $this->assertNotNull(VideoTask::find($this->taskA->id));
        $this->assertNotNull(Idea::find($this->ideaA->id));
    }

    public function test_user_only_sees_own_organization_data(): void
    {
        $this->actingAs($this->adminB);

        $this->assertSame(0, VideoTask::count());
        $this->assertNull(VideoTask::find($this->taskA->id));
        $this->assertNull(Idea::find($this->ideaA->id));
    }

    public function test_org_admin_cannot_create_a_company(): void
    {
        $this->assertTrue($this->adminA->can('create empresa'));

        $this->actingAs($this->adminA)
            ->get('/company/create')
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->post('/company', ['name' => 'Empresa Bloqueada'])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizations', ['name' => 'Empresa Bloqueada']);
    }

    public function test_super_admin_can_create_a_company_with_default_roles(): void
    {
        $superAdmin = User::factory()->create(['organization_id' => null]);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin)
            ->post('/company', ['name' => 'Empresa Nueva'])
            ->assertRedirect(route('company.index'));

        $organization = Organization::where('name', 'Empresa Nueva')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['Admin', 'Employee'],
            Role::where('organization_id', $organization->id)->pluck('name')->all()
        );
    }

    public function test_task_share_rejects_user_from_another_organization(): void
    {
        $this->actingAs($this->adminA)
            ->post('/task-shares', [
                'shareable_type' => 'video_tasks',
                'shareable_id' => $this->taskA->id,
                'user_ids' => [$this->adminB->id],
                'roles' => ['reader'],
            ])
            ->assertSessionHasErrors('user_ids.0');

        $this->assertDatabaseMissing('task_sharings', [
            'shareable_id' => $this->taskA->id,
            'shared_with_user_id' => $this->adminB->id,
        ]);
    }

    public function test_roles_index_exposes_only_own_company_to_non_super_admin(): void
    {
        $this->adminA->givePermissionTo('view roles');

        $this->actingAs($this->adminA)
            ->get('/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roles/Index')
                ->has('companies', 1)
                ->where('companies.0.id', $this->orgA->id));
    }

    public function test_users_index_hides_super_admins_from_admins(): void
    {
        $superAdmin = User::factory()->create(['organization_id' => null]);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($this->adminA)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('super_admins', 0));

        $this->actingAs($superAdmin)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('super_admins', 1));
    }

    public function test_users_index_only_lists_users_from_own_organization(): void
    {
        $this->actingAs($this->adminA)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.email', $this->adminA->email));
    }

    public function test_task_routes_reject_user_from_another_organization(): void
    {
        $originalStatus = $this->taskA->status;

        $this->actingAs($this->adminB)
            ->get('/tasks/'.$this->taskA->id)
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->patch('/tasks/'.$this->taskA->id.'/status', ['status' => 'published'])
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->get('/tasks/'.$this->taskA->id.'/edit')
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->delete('/tasks/'.$this->taskA->id)
            ->assertNotFound();

        $this->assertDatabaseHas('video_tasks', [
            'id' => $this->taskA->id,
            'status' => $originalStatus,
        ]);
    }

    public function test_idea_mutation_routes_reject_user_from_another_organization(): void
    {
        $originalContent = $this->ideaA->content;

        $this->actingAs($this->adminB)
            ->patch('/ideas/'.$this->ideaA->id, ['content' => 'Contenido ajeno'])
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->patch('/ideas/'.$this->ideaA->id.'/used', ['used' => true])
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->delete('/ideas/'.$this->ideaA->id)
            ->assertNotFound();

        $this->assertDatabaseHas('ideas', [
            'id' => $this->ideaA->id,
            'content' => $originalContent,
        ]);
    }

    public function test_user_edit_rejected_for_user_from_another_organization(): void
    {
        $this->actingAs($this->adminA)
            ->get('/users/'.$this->adminB->id.'/edit')
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $this->adminB->id,
            'organization_id' => $this->orgB->id,
        ]);
    }
}
