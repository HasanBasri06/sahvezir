<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode as PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Şifremi unuttum: e-postaya 6 haneli kod gönderilir, kod doğrulanınca yeni şifre belirlenir.
 */
class PasswordResetController extends Controller
{
    private const MINUTES = 15;

    /** Bu kadar yanlış denemeden sonra kod geçersiz olur, yenisi istenir */
    private const MAX_ATTEMPTS = 5;

    /**
     * Kayıtlı e-postaya kod gönderir. Hesabın olup olmadığı belli olmasın diye yanıt her zaman aynıdır.
     */
    public function send(Request $request): JsonResponse
    {
        $email = $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            ['email.required' => 'E-posta zorunludur.', 'email.email' => 'Geçerli bir e-posta girin.'],
        )['email'];

        if (User::where('email', $email)->exists()) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetCode::updateOrCreate(['email' => $email], [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::MINUTES),
            ]);
            Mail::to($email)->send(new PasswordResetCodeMail($code, self::MINUTES));
        }

        return response()->json(['message' => 'Bu e-posta kayıtlıysa 6 haneli kod gönderildi.']);
    }

    /** Kod adımı: kod doğruysa yeni şifre adımına geçilir */
    public function verify(Request $request): JsonResponse
    {
        $this->checkCode($request);

        return response()->json(['message' => 'Kod doğrulandı.']);
    }

    /** Yeni şifre: kod tekrar doğrulanır, şifre değişir, eski oturumlar kapanır ve kullanıcı giriş yapar */
    public function reset(Request $request): JsonResponse
    {
        $request->validate(
            ['password' => ['required', 'confirmed', Password::min(8)]],
            [
                'password.required' => 'Şifre zorunludur.',
                'password.confirmed' => 'Şifreler eşleşmiyor.',
                'password.min' => 'Şifre en az 8 karakter olmalıdır.',
            ],
        );
        $record = $this->checkCode($request);

        $user = User::where('email', $record->email)->firstOrFail();
        $user->password = $request->input('password');
        // Kod e-postaya geldiği için adres doğrulanmış sayılır
        $user->email_verified_at ??= now();
        $user->save();
        $user->tokens()->delete();
        $record->delete();

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    private function checkCode(Request $request): PasswordResetCode
    {
        $data = $request->validate(
            ['email' => ['required', 'email'], 'code' => ['required', 'digits:6']],
            ['code.required' => 'Kodu girin.', 'code.digits' => 'Kod 6 haneli olmalıdır.'],
        );

        $record = PasswordResetCode::find($data['email']);
        if (! $record || $record->expires_at->isPast()) {
            $record?->delete();
            throw ValidationException::withMessages(['code' => 'Kodun süresi doldu, yeni kod iste.']);
        }

        if (! Hash::check($data['code'], $record->code_hash)) {
            $record->increment('attempts');
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();
                throw ValidationException::withMessages(['code' => 'Çok fazla yanlış deneme yaptın, yeni kod iste.']);
            }
            throw ValidationException::withMessages(['code' => 'Kod hatalı, tekrar dene.']);
        }

        return $record;
    }
}
