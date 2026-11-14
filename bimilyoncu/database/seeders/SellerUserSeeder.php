<?php

namespace Database\Seeders;

use App\Models\Seller;
use Illuminate\Database\Seeder;

/**
 * Akcıl Mobilya için satıcı paneli hesabı (geliştirme ortamı).
 */
class SellerUserSeeder extends Seeder
{
    public function run(): void
    {
        $seller = Seller::where('slug', 'akcil-mobilya')->first();
        if (! $seller || $seller->users()->exists()) {
            return;
        }

        $seller->users()->create([
            'name' => 'Akcıl Mobilya Yetkilisi',
            'email' => 'satici@akcilmobilya.com',
            'password' => 'Satici1234!',
        ]);
    }
}
