<?php

namespace App\Http\Controllers;

use App\Mail\ContactSupportMail;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    use ResponseTrait;

    /**
     * Konu, İçerik (ve giriş yoksa E-posta) ile destek@tasibul.com'a e-posta gönderir.
     * Giriş yoksa email zorunludur.
     */
    public function store(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $rules = [
            'konu' => 'required|string|max:255',
            'icerik' => 'required|string|max:10000',
            'email' => 'nullable|email',
        ];
        $messages = [
            'konu.required' => 'Konu alanı zorunludur.',
            'icerik.required' => 'İçerik alanı zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
        ];

        $validated = $request->validate($rules, $messages);

        $userName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null;
        $userEmail = $user ? ($user->email ?? null) : ($validated['email'] ?? null);

        try {
            Mail::to('destek@tasibul.com')->send(new ContactSupportMail(
                konu: $validated['konu'],
                icerik: $validated['icerik'],
                userName: $userName ?: null,
                userEmail: $userEmail
            ));
        } catch (\Throwable $e) {
            return $this->error(
                [],
                'Mesajınız gönderilemedi.',
                $e->getMessage(),
                500
            );
        }

        return $this->success([], 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.');
    }
}
