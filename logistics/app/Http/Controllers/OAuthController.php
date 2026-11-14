<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Socialite;

class OAuthController extends Controller
{
    public function redirect(Request $request)
    {
        return Socialite::driver('google')
            ->redirect();
    }

    public function callback(Request $request)
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        // email yoksa güvenli şekilde reddet (bazı hesaplarda gelebilir)
        if (!$googleUser->getEmail()) {
            return redirect(config('app.frontend_url') . '/login?error=no_email');
        }

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                // İstersen google_id da tut:
                // 'google_id' => $googleUser->getId(),
                // Şifre gerekmiyorsa random bırak:
                'password' => bcrypt(Str::random(32)),
            ]
        );

        // Sanctum token
        $token = $user->createToken('google-login')->plainTextToken;

        // Token'ı query ile frontend’e dön (kolay yol)
        // Daha güvenlisi: kısa ömürlü “code” üretip frontend’den POST ile token almak (istersen onu da yazarım)
        $frontend = env('FRONTEND_URL', 'http://localhost:5173');

        return redirect($frontend . '/auth/callback?token=' . urlencode($token));
    }
}
