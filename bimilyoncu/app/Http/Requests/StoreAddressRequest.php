<?php

namespace App\Http\Requests;

use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAddressRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            // Türkiye cep/sabit: 0 ile ya da 0'sız 10 hane; boşluk, tire, parantez serbest
            'phone' => ['required', 'string', 'regex:/^(\+?90|0)?[\s\-()]*\d([\s\-()]*\d){9}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            // İlçe seçilen ile ait olmalı
            'district_id' => [
                'required', 'integer',
                Rule::exists('districts', 'id')->where('city_id', $this->integer('city_id')),
            ],
            'neighbourhood' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:150'],
            'building_no' => ['required', 'string', 'max:10'],
            'floor' => ['required', 'string', 'max:10'],
            'apartment_no' => ['required', 'string', 'max:10'],
            'postal_code' => ['required', 'digits:5'],
            'directions' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->user()->addresses()->count() >= Address::MAX_PER_USER) {
                    $validator->errors()->add('title', 'En fazla '.Address::MAX_PER_USER.' adres kaydedebilirsin.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Adres başlığı zorunludur.',
            'full_name.required' => 'Ad soyad zorunludur.',
            'phone.required' => 'Telefon zorunludur.',
            'phone.regex' => 'Geçerli bir telefon numarası girin.',
            'city_id.required' => 'İl seçin.',
            'city_id.exists' => 'Geçerli bir il seçin.',
            'district_id.required' => 'İlçe seçin.',
            'district_id.exists' => 'Seçtiğin ile ait bir ilçe seçin.',
            'neighbourhood.required' => 'Mahalle zorunludur.',
            'street.required' => 'Cadde/sokak zorunludur.',
            'building_no.required' => 'Bina/kapı no zorunludur.',
            'floor.required' => 'Kat zorunludur.',
            'apartment_no.required' => 'Daire no zorunludur.',
            'postal_code.required' => 'Posta kodu zorunludur.',
            'postal_code.digits' => 'Posta kodu 5 haneli olmalıdır.',
        ];
    }
}
