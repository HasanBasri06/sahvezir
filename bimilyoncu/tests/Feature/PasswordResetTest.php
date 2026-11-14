<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function requestCode(string $email): ?string
    {
        $code = null;
        $this->postJson('/api/auth/forgot-password', ['email' => $email])->assertOk();
        Mail::assertSent(PasswordResetCode::class, function (PasswordResetCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_user_resets_password_with_emailed_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ayse@example.com']);
        $user->createToken('eski');

        $code = $this->requestCode('ayse@example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->postJson('/api/auth/forgot-password/verify', ['email' => 'ayse@example.com', 'code' => $code])
            ->assertOk();

        $this->postJson('/api/auth/forgot-password/reset', [
            'email' => 'ayse@example.com',
            'code' => $code,
            'password' => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $this->assertTrue(Hash::check('yeni-sifre-123', $user->fresh()->password));
        // Eski oturum kapandı, yalnızca yeni giriş token'ı kaldı; kod tekrar kullanılamaz
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_unknown_email_gets_same_answer_without_mail(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/forgot-password', ['email' => 'yok@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Bu e-posta kayıtlıysa 6 haneli kod gönderildi.');
        Mail::assertNothingSent();
    }

    public function test_wrong_codes_lock_the_code_after_five_tries(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ayse@example.com']);
        $code = $this->requestCode('ayse@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/forgot-password/verify', ['email' => 'ayse@example.com', 'code' => $wrong])
                ->assertJsonValidationErrors(['code' => 'Kod hatalı']);
        }
        $this->postJson('/api/auth/forgot-password/verify', ['email' => 'ayse@example.com', 'code' => $wrong])
            ->assertJsonValidationErrors(['code' => 'Çok fazla']);

        // Doğru kod da artık geçmez
        $this->postJson('/api/auth/forgot-password/verify', ['email' => 'ayse@example.com', 'code' => $code])
            ->assertJsonValidationErrors(['code' => 'süresi doldu']);
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ayse@example.com']);
        $code = $this->requestCode('ayse@example.com');

        $this->travel(16)->minutes();
        $this->postJson('/api/auth/forgot-password/verify', ['email' => 'ayse@example.com', 'code' => $code])
            ->assertJsonValidationErrors(['code' => 'süresi doldu']);
    }
}
