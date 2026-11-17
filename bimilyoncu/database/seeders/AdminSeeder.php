<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * İlk site yöneticisi hesabı. Bilgiler .env'deki ADMIN_SEED_* değerlerinden gelir; şifre koda yazılmaz.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL');
        $password = "123456";
        if (! $email || ! $password || Admin::where('email', $email)->exists()) {
            return;
        }

        Admin::create(['name' => env('ADMIN_SEED_NAME', 'Site Yöneticisi'), 'email' => $email, 'password' => $password]);
    }
}
