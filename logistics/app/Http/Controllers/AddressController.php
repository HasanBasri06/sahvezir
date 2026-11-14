<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ResponseTrait;

    public function getAllByUser(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();
        return $this->success(['addresses' => $addresses]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive,pending',
        ]);

        $address = Address::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'city' => $validated['city'],
            'district' => $validated['district'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
        ]);

        return $this->success(['address' => $address], 'Adres eklendi.', 201);
    }

    public function update(Request $request, Address $address)
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return $this->error([], 'Bu adresi düzenleme yetkiniz yok.', 'Forbidden', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive,pending',
        ]);

        $address->update([
            'name' => $validated['name'],
            'city' => $validated['city'],
            'district' => $validated['district'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->success(['address' => $address->fresh()], 'Adres güncellendi.');
    }
}
