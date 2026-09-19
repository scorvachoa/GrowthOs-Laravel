<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->service = new UserService;
    }

    public function test_create_user_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = $this->service->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'Employee',
        ]);

        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue($user->hasRole('Employee'));
    }

    public function test_create_user_without_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = $this->service->create([
            'name' => 'No Role',
            'email' => 'norole@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotNull($user);
        $this->assertEquals('No Role', $user->name);
    }

    public function test_create_user_sets_organization(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = $this->service->create([
            'name' => 'Org User',
            'email' => 'org@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals($admin->organization_id, $user->organization_id);
    }

    public function test_update_user_name_and_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $updated = $this->service->update($user, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('updated@example.com', $updated->email);
    }

    public function test_update_user_with_password(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->service->update($user, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_update_user_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $user->assignRole('Employee');

        $updated = $this->service->update($user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'Admin',
        ]);

        $this->assertTrue($updated->hasRole('Admin'));
        $this->assertFalse($updated->hasRole('Employee'));
    }

    public function test_non_super_admin_cannot_assign_super_admin_role(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No puedes asignar el rol Super Admin');

        $this->service->create([
            'name' => 'Bad User',
            'email' => 'bad@example.com',
            'password' => 'password123',
            'role' => 'Super Admin',
        ]);
    }
}
