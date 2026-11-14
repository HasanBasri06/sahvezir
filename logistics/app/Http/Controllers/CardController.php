<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class CardController extends Controller
{
    use ResponseTrait;

    public function getAllByUser(Request $request)
    {
        $cards = Card::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['cards' => $cards]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'card_holder_name' => 'required|string|max:255',
            'last_four' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'exp_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            'exp_year' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'brand' => 'nullable|string|max:50',
        ]);

        $card = Card::create([
            'user_id' => $request->user()->id,
            'card_holder_name' => $validated['card_holder_name'],
            'last_four' => $validated['last_four'],
            'exp_month' => $validated['exp_month'],
            'exp_year' => $validated['exp_year'],
            'brand' => $validated['brand'] ?? null,
            'provider' => 'manual',
            'provider_card_id' => 'card_' . Str::random(24),
        ]);

        return $this->success(['card' => $card], 'Kart başarıyla eklendi.', 201);
    }

    public function destroy(Request $request, Card $card)
    {
        if ($card->user_id !== $request->user()->id) {
            return $this->error(null, 'Yetkisiz işlem.', 'Unauthorized', 403);
        }
        $card->delete();
        $cards = Card::where('user_id', $request->user()->id)->latest()->get();
        return $this->success(['cards' => $cards], 'Kart silindi.');
    }
}
