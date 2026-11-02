<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee(route('register'))
            ->assertSee('Driver sign up')
            ->assertDontSee('Admins and drivers can sign in here')
            ->assertDontSee('Driver login')
            ->assertDontSee('Admin Login');
    }

    public function test_driver_can_register_pending_account(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Driver',
            'email' => 'new-driver@example.com',
            'phone' => '09123456789',
            'vehicle' => 'Van 1',
            'password' => 'Str0ng-pass-123',
            'password_confirmation' => 'Str0ng-pass-123',
        ]);

        $user = User::where('email', 'new-driver@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->is_active);
        $this->assertGuest();
        $response->assertRedirect(route('login'))
            ->assertSessionHas('status');
        $this->get(route('login'))->assertSee('Registration submitted.');
    }

    public function test_driver_registration_cannot_create_an_admin_account(): void
    {
        $this->post(route('register'), [
            'name' => 'New Driver',
            'email' => 'new-driver@example.com',
            'role' => 'admin',
            'is_active' => true,
            'password' => 'Str0ng-pass-123',
            'password_confirmation' => 'Str0ng-pass-123',
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'new-driver@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->is_active);
    }

    public function test_pending_driver_account_cannot_log_in_until_approved(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('logistics', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('logistics'));

        $response = $this->post('/user/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_admin_and_user_sessions_use_separate_cookies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $adminResponse = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $adminCookie = collect($adminResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.admin_cookie'));

        $this->assertNotNull($adminCookie);

        $this->withCookie($adminCookie->getName(), $adminCookie->getValue())
            ->post('/logout')
            ->assertRedirect('/');
        $loginPage = $this->get('/login')->assertOk();
        $loginCookie = collect($loginPage->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.admin_cookie'));
        $this->assertNotNull($loginCookie);

        $userResponse = $this->withCookie($loginCookie->getName(), $loginCookie->getValue())
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);
        $userCookie = collect($userResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.user_cookie'));
        $this->assertNotNull($userCookie);
        $this->assertNotSame($adminCookie->getName(), $userCookie->getName());
        $userResponse->assertRedirect(route('logistics'));
    }

    public function test_admin_and_driver_accounts_use_the_same_login_and_redirect_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'user']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $driver->email, 'password' => 'password'])
            ->assertRedirect(route('logistics'));
    }

    public function test_driver_can_log_in_with_mixed_case_email_and_open_the_driver_page(): void
    {
        $driver = User::factory()->create([
            'role' => 'user',
            'email' => 'driver@example.com',
            'is_active' => true,
        ]);

        $loginPage = $this->get('/login')->assertOk();
        $adminCookie = collect($loginPage->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.admin_cookie'));
        $this->assertNotNull($adminCookie);
        $this->assertNull(collect($loginPage->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.user_cookie')));

        $response = $this->withCookie($adminCookie->getName(), $adminCookie->getValue())
            ->post('/login', [
                'email' => 'DRIVER@Example.com',
                'password' => 'password',
            ])->assertRedirect(route('logistics'));

        $driverCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.user_cookie'));
        $this->assertNotNull($driverCookie);

        $this->withCookie($driverCookie->getName(), $driverCookie->getValue())
            ->get(route('logistics'))
            ->assertOk()
            ->assertSee('My Deliveries');

        $this->assertAuthenticatedAs($driver);
    }

    public function test_admin_can_log_in_after_a_driver_session_has_ended(): void
    {
        $driver = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $loginPage = $this->get('/login')->assertOk();
        $driverLoginCookie = collect($loginPage->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.admin_cookie'));
        $this->assertNotNull($driverLoginCookie);

        $driverResponse = $this->withCookie($driverLoginCookie->getName(), $driverLoginCookie->getValue())
            ->post('/login', ['email' => $driver->email, 'password' => 'password'])
            ->assertRedirect(route('logistics'));
        $driverSessionCookie = collect($driverResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.user_cookie'));
        $this->assertNotNull($driverSessionCookie);

        $logoutResponse = $this->withCookie($driverSessionCookie->getName(), $driverSessionCookie->getValue())
            ->post('/user/logout')
            ->assertRedirect('/');
        $driverSessionCookie = collect($logoutResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.user_cookie'));
        $this->assertNotNull($driverSessionCookie);

        $loginPage = $this->withCookie($driverSessionCookie->getName(), $driverSessionCookie->getValue())
            ->get('/login')
            ->assertOk();
        $adminLoginCookie = collect($loginPage->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.admin_cookie'));
        $this->assertNotNull($adminLoginCookie);

        $this->withCookie($adminLoginCookie->getName(), $adminLoginCookie->getValue())
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_and_driver_pages_show_separate_portal_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ADMIN PORTAL')
            ->assertSee('href="/payments"', false)
            ->assertSee('href="/inventory/stock-in"', false)
            ->assertSee('href="/inventory/stock-out"', false)
            ->assertSee('href="/tracking"', false)
            ->assertDontSee('STAFF PORTAL');

        $this->actingAs($staff)->get(route('logistics'))
            ->assertOk()
            ->assertSee('DRIVER PORTAL')
            ->assertDontSee('href="/inventory"', false)
            ->assertDontSee('href="/inventory/stock-in"', false)
            ->assertSee('href="/logistics"', false)
            ->assertDontSee('href="/payments"', false)
            ->assertDontSee('ADMIN PORTAL');
    }

    public function test_users_are_redirected_away_from_the_other_portals_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        $this->actingAs($staff)->get(route('payments.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($admin)->get(route('logistics'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_and_driver_login_and_signup_paths_are_direct(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'));

        $this->get(route('user.login'))
            ->assertRedirect(route('login'));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Driver Sign Up')
            ->assertSee(route('login'));

        $this->get(route('user.portal'))
            ->assertRedirect(route('login'));
    }
}
