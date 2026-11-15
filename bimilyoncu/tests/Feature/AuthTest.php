<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'password' => 'gizli-sifre',
            'password_confirmation' => 'gizli-sifre',
        ], $overrides);
    }

    public function test_user_can_register(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload())
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ayse@example.com']);
    }

    public function test_register_requires_matching_passwords(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['password_confirmation' => 'farkli-sifre']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_and_logout(): void
    {
        User::factory()->create(['email' => 'ayse@example.com', 'password' => 'gizli-sifre']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'ayse@example.com',
            'password' => 'gizli-sifre',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'ayse@example.com');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function fakeGoogleUser(array $attributes = []): void
    {
        $attributes = array_merge([
            'id' => 'google-123',
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'email_verified' => true,
        ], $attributes);

        $googleUser = (new SocialiteUser)->setRaw($attributes)->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('stateless->user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_login_creates_user_without_password(): void
    {
        $this->fakeGoogleUser();

        $this->postJson('/api/auth/google', ['code' => 'auth-code'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Ayşe Yılmaz')
            ->assertJsonPath('user.has_password', false)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', ['email' => 'ayse@example.com', 'google_id' => 'google-123', 'password' => null]);
    }

    public function test_google_user_can_set_password_once(): void
    {
        $this->fakeGoogleUser();
        $token = $this->postJson('/api/auth/google', ['code' => 'auth-code'])->json('token');

        $this->withToken($token)->postJson('/api/auth/password', [
            'password' => 'yeni-sifre',
            'password_confirmation' => 'yeni-sifre',
        ])->assertOk()->assertJsonPath('user.has_password', true);

        // Şifre belirlendikten sonra bu uçla değiştirilemez
        $this->withToken($token)->postJson('/api/auth/password', [
            'password' => 'baska-sifre',
            'password_confirmation' => 'baska-sifre',
        ])->assertForbidden();

        $this->postJson('/api/auth/login', ['email' => 'ayse@example.com', 'password' => 'yeni-sifre'])->assertOk();
    }

    public function test_google_login_links_existing_unverified_account_and_resets_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ayse@example.com', 'password' => 'eski-sifre']);
        $user->createToken('auth');
        $this->fakeGoogleUser();

        $this->postJson('/api/auth/google', ['code' => 'auth-code'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.has_password', false);

        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_google_login_keeps_password_of_verified_account(): void
    {
        $user = User::factory()->create(['email' => 'ayse@example.com', 'password' => 'eski-sifre']);
        $this->fakeGoogleUser();

        $this->postJson('/api/auth/google', ['code' => 'auth-code'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.has_password', true);
    }

    public function test_google_login_rejects_unverified_google_email(): void
    {
        $this->fakeGoogleUser(['email_verified' => false]);

        $this->postJson('/api/auth/google', ['code' => 'auth-code'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ayse@example.com', 'password' => 'gizli-sifre']);

        $this->postJson('/api/auth/login', [
            'email' => 'ayse@example.com',
            'password' => 'yanlis',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
