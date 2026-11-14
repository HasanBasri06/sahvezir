<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Address
 */
class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'city' => ['id' => $this->city_id, 'name' => $this->city->name],
            'district' => ['id' => $this->district_id, 'name' => $this->district->name],
            'neighbourhood' => $this->neighbourhood,
            'street' => $this->street,
            'building_no' => $this->building_no,
            'floor' => $this->floor,
            'apartment_no' => $this->apartment_no,
            'postal_code' => $this->postal_code,
            'directions' => $this->directions,
        ];
    }
}
