<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'E-posta veya şifre hatalı.',
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'code' => 'Google ile giriş yapılamadı, lütfen tekrar deneyin.',
            ]);
        }

        if (! ($googleUser->user['email_verified'] ?? false)) {
            throw ValidationException::withMessages([
                'code' => 'Google hesabınızın e-posta adresi doğrulanmamış.',
            ]);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
            ]);
        } elseif (! $user->google_id) {
            // Aynı e-postayla açılmış hesabı Google'a bağla. E-posta daha önce doğrulanmadıysa
            // hesabı bu adresi bilmeyen biri açmış olabilir; eski şifre ve oturumlar iptal edilir,
            // kullanıcı yeni şifresini belirler.
            if (! $user->email_verified_at) {
                $user->password = null;
                $user->tokens()->delete();
            }
            $user->google_id = $googleUser->getId();
        }

        $user->email_verified_at ??= now();
        $user->save();

        return $this->tokenResponse($user);
    }

    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth')->plainTextToken,
        ], $status);
    }
}
