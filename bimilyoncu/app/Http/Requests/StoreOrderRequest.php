<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kart numarasının kendisi asla gönderilmez; yalnızca marka ve son 4 hane.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_id' => [
                'required', 'integer',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'card_brand' => ['nullable', 'string', Rule::in(['visa', 'mastercard', 'troy', 'amex'])],
            'card_last4' => ['required', 'digits:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Lütfen bir teslimat adresi seç.',
            'address_id.exists' => 'Seçtiğin adres bulunamadı.',
        ];
    }
}
