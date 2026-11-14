<?php

namespace App\Http\Requests\Cart;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tek bir sepet satırının kuralları; birleştirmede her satıra ön ek ile uygulanır.
     * Ürün adı, fiyatı ve görseli istemciden alınmaz, veritabanından okunur.
     *
     * @return array<string, mixed>
     */
    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}product_id" => ['required', 'string', 'max:20'],
            "{$prefix}color" => ['nullable', 'string', 'max:50'],
            "{$prefix}quantity" => ['required', 'integer', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function itemMessages(string $prefix = ''): array
    {
        return [
            "{$prefix}quantity.max" => 'Bir üründen en fazla '.CartItem::MAX_QUANTITY.' adet alabilirsin.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::itemRules(),
            'product_id' => ['required', 'string', 'exists:products,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::itemMessages(),
            'product_id.exists' => 'Ürün bulunamadı.',
        ];
    }
}
