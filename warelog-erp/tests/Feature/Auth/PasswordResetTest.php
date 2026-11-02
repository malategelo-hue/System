<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PortalPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get(route('user.password.request'));

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('user.password.email'), ['email' => $user->email]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, PortalPasswordReset::class, function ($notification) use ($user) {
            $expectedUrl = route('user.password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]);

            $this->assertSame($expectedUrl, $notification->toMail($user)->actionUrl);

            return true;
        });
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('user.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PortalPasswordReset::class, function ($notification) {
            $response = $this->get(route('user.password.reset', ['token' => $notification->token]));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('user.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PortalPasswordReset::class, function ($notification) use ($user) {
            $response = $this->post(route('user.password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('new-password', $user->fresh()->password));

            return true;
        });
    }
}
