<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $org = Organization::firstOrFail();
        $org->update(['invite_code' => 'TEST-1234']);

        $response = $this->post('/register', [
            'invite_code' => 'TEST-1234',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame($org->id, $user->organization_id);
        $this->assertTrue($user->hasRole('Employee'));
    }

    public function test_registration_with_admin_invite_code_assigns_admin_role(): void
    {
        $org = Organization::firstOrFail();
        $org->update(['admin_invite_code' => 'ROOT-9999']);

        $response = $this->post('/register', [
            'invite_code' => 'ROOT-9999',
            'name' => 'Org Admin',
            'email' => 'orgadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'orgadmin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Admin'));
        $this->assertNull($org->fresh()->admin_invite_code);
    }

    public function test_registration_rejects_an_invalid_invite_code(): void
    {
        $response = $this->post('/register', [
            'invite_code' => 'NOPE-0000',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
