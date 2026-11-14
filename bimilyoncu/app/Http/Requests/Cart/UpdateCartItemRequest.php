<?php

namespace App\Http\Requests\Cart;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.max' => 'Bir üründen en fazla '.CartItem::MAX_QUANTITY.' adet alabilirsin.',
        ];
    }
}
