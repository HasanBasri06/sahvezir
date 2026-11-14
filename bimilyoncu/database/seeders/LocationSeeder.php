<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 81 il, 973 ilçe ve ilçelerin posta kodları.
 *
 * Kaynak: turkey-neighbourhoods 4.0.3 (MIT) — database/data/turkiye.json bu paketten üretildi.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // Tekrar çalıştırılabilir: veri zaten yüklüyse dokunma
        if (DB::table('cities')->exists()) {
            return;
        }

        $cities = json_decode(file_get_contents(database_path('data/turkiye.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($cities) {
            foreach ($cities as $city) {
                DB::table('cities')->insert(['id' => $city['code'], 'name' => $city['name']]);

                foreach ($city['districts'] as $district) {
                    $districtId = DB::table('districts')->insertGetId([
                        'city_id' => $city['code'],
                        'name' => $district['name'],
                    ]);

                    DB::table('district_postal_codes')->insert(array_map(
                        fn (string $code) => ['district_id' => $districtId, 'code' => $code],
                        $district['postal_codes'],
                    ));
                }
            }
        });
    }
}
