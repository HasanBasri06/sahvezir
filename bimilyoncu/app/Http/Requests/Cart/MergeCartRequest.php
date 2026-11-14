<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class MergeCartRequest extends FormRequest
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
            'items' => ['required', 'array', 'max:100'],
            ...AddCartItemRequest::itemRules('items.*.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AddCartItemRequest::itemMessages('items.*.');
    }
}
