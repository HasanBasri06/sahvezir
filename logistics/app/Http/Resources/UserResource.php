<?php

namespace App\Http\Resources;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get user's role from roles table

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name.' '.$this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'image' => PublicStorageUrl::absoluteForRequest($request, $this->profile_image),
            'type' => $this->type,
            'suitable_postings' => $this->suitable_postings ?? 'no',
            'user_city' => $this->userDetail?->user_city,
            'user_district' => $this->userDetail?->user_district,
            'payment_confirm' => (bool) $this->payment_confirm,
            'verified' => (bool) $this->verified,
            'panel_tour_completed' => $this->panel_tour_completed_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'phoneFormatted' => $this->phoneFormatted,
            'expo_push_token' => $this->pushDevice?->expo_push_token,
            'platform' => $this->pushDevice?->platform,
        ];
    }
}
