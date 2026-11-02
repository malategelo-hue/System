<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_driver_registration_creates_a_pending_driver_account(): void
    {
        $this->get('/register')->assertOk()->assertSee('Driver Sign Up');

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Str0ng-pass-123',
            'password_confirmation' => 'Str0ng-pass-123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'user',
            'is_active' => false,
        ]);
    }

    public function test_login_pages_link_directly_to_driver_login_and_signup(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('register'))
            ->assertDontSee('Admins and drivers can sign in here')
            ->assertDontSee('Driver login');
        $this->get(route('user.login'))
            ->assertRedirect(route('login'));
        $this->get(route('user.portal'))
            ->assertRedirect(route('login'));
    }

    public function test_old_account_setup_route_is_disabled(): void
    {
        $this->get('/setup-accounts')->assertNotFound();
    }

    public function test_database_seeder_provisions_the_default_admin_account(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@warelog.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'admin@warelog.com')->firstOrFail());
    }
}
