<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('post_types')->insert([
            [
                'name' => 'type1',
                'value' => 'Dökme Yük',
                'image' => 'dokme.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Paletli Yük',
                'image' => 'paletli.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Paket / Koli',
                'image' => 'paket_koli.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Çuval',
                'image' => 'cuval.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Sıvı',
                'image' => 'sivi.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Soğuk Zincir',
                'image' => 'soguk_zincir.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Şoklanmış Ürün',
                'image' => 'soklanmis.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Blok Yük',
                'image' => 'blok.png',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Parça Yük',
                'image' => null,
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'type1',
                'value' => 'Belirlenmiş',
                'image' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->call(VehicleCatalogSeeder::class);
        $this->call(CityDistrictSeeder::class);
    }
}
