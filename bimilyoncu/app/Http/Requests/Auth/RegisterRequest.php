<?php

namespace App\Http\Requests\Auth;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'recaptcha_token' => ['required', 'string', new Recaptcha],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'İsim soyisim zorunludur.',
            'email.required' => 'E-posta zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.unique' => 'Bu e-posta adresiyle zaten bir hesap var.',
            'password.required' => 'Şifre zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'password.min' => 'Şifre en az 8 karakter olmalıdır.',
            'recaptcha_token.required' => 'Lütfen robot olmadığınızı doğrulayın.',
        ];
    }
}
