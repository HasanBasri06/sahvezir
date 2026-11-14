<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $vehiclesDir = base_path('../client/src/assets/images/vehicles');

        if (!File::isDirectory($vehiclesDir)) {
            $this->command?->warn("Vehicles directory not found: {$vehiclesDir}");
            return;
        }

        $vehicleFiles = collect(File::files($vehiclesDir))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'svg'], true))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        if ($vehicleFiles->isEmpty()) {
            $this->command?->warn("No vehicle images found in: {$vehiclesDir}");
            return;
        }

        $availableImages = [];
        foreach ($vehicleFiles as $file) {
            $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $normalizedStem = (string) Str::of($stem)->ascii()->lower()->replace('-', '_')->replace(' ', '_');
            $availableImages[$normalizedStem] = $file->getFilename();
        }

        $resolveImage = fn (string $key, string $fallback = 'otomobil') => $availableImages[$key] ?? ($availableImages[$fallback] ?? null);

        $catalog = [
            [
                'car' => ['name' => 'Tir', 'image' => $resolveImage('tir_tenteli', 'kirkayak'), 'price' => 5000, 'cost_per_km' => 31, 'width' => 2.55, 'height' => 3.40, 'order' => 1],
                'details' => [
                    ['name' => 'Dorse Tipi', 'value' => 'Tenteli Dorse', 'image' => $resolveImage('tir_tenteli', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Damper Dorse', 'image' => $resolveImage('tir_damper', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Lowbed Dorse', 'image' => $resolveImage('tir_lowbed', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Tanker Dorse', 'image' => $resolveImage('tir_tanker', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Silobas Dorse', 'image' => $resolveImage('tir_silobas', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Container Dorse', 'image' => $resolveImage('tir_container', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Acik Dorse', 'image' => $resolveImage('acik_dorse', 'kirkayak')],
                    ['name' => 'Dorse Tipi', 'value' => 'Termokin Dorse', 'image' => $resolveImage('termokin_tir', 'kirkayak')],
                ],
            ],
            [
                'car' => ['name' => '10 Teker Kamyon', 'image' => $resolveImage('10_teker_kamyon', 'otomobil'), 'price' => 3400, 'cost_per_km' => 22, 'width' => 2.45, 'height' => 2.90, 'order' => 2],
                'details' => [
                    ['name' => 'Kasa Tipi', 'value' => 'Kapali', 'image' => $resolveImage('10_teker_kamyon', 'otomobil')],
                    ['name' => 'Kasa Tipi', 'value' => 'Acik', 'image' => $resolveImage('10_teker_acik_kamyon', '10_teker_kamyon')],
                ],
            ],
            [
                'car' => ['name' => '8 Teker Kamyon', 'image' => $resolveImage('8_teker_kamyon', 'otomobil'), 'price' => 3000, 'cost_per_km' => 20, 'width' => 2.45, 'height' => 2.80, 'order' => 3],
                'details' => [
                    ['name' => 'Kasa Tipi', 'value' => 'Kapali', 'image' => $resolveImage('8_teker_kamyon', 'otomobil')],
                    ['name' => 'Kasa Tipi', 'value' => 'Acik', 'image' => $resolveImage('8_teker_kamyon', 'otomobil')],
                ],
            ],
            [
                'car' => ['name' => '6 Teker Kamyon', 'image' => $resolveImage('6_teker_kamyon', 'otomobil'), 'price' => 2600, 'cost_per_km' => 18, 'width' => 2.45, 'height' => 2.70, 'order' => 4],
                'details' => [
                    ['name' => 'Kasa Tipi', 'value' => 'Kapali', 'image' => $resolveImage('6_teker_kamyon', 'otomobil')],
                    ['name' => 'Kasa Tipi', 'value' => 'Acik', 'image' => $resolveImage('6_teker_kamyon', 'otomobil')],
                ],
            ],
            [
                'car' => ['name' => 'Panelvan', 'image' => $resolveImage('panelvan', 'otomobil'), 'price' => 1500, 'cost_per_km' => 14, 'width' => 2.00, 'height' => 2.30, 'order' => 5],
                'details' => [
                    ['name' => 'Uzunluk', 'value' => 'Orta', 'image' => $resolveImage('panelvan', 'otomobil')],
                    ['name' => 'Uzunluk', 'value' => 'Uzun', 'image' => $resolveImage('uzun_panelvan', 'panelvan')],
                    ['name' => 'Uzunluk', 'value' => 'Ekstra Uzun', 'image' => $resolveImage('uzun_panelvan', 'panelvan')],
                ],
            ],
            [
                'car' => ['name' => 'Minivan', 'image' => $resolveImage('minivan', 'panelvan'), 'price' => 1200, 'cost_per_km' => 12, 'width' => 1.90, 'height' => 1.90, 'order' => 6],
                'details' => [
                    ['name' => 'Kasa Tipi', 'value' => 'Kapali', 'image' => $resolveImage('minivan', 'panelvan')],
                ],
            ],
            [
                'car' => ['name' => 'Otomobil', 'image' => $resolveImage('otomobil', 'panelvan'), 'price' => 900, 'cost_per_km' => 10, 'width' => 1.80, 'height' => 1.50, 'order' => 7],
            ],
            [
                'car' => ['name' => 'Motosiklet', 'image' => $resolveImage('motosiklet', 'otomobil'), 'price' => 450, 'cost_per_km' => 6, 'width' => 0.70, 'height' => 1.10, 'order' => 8],
            ],
        ];

        foreach ($catalog as $item) {
            $car = Car::query()->updateOrCreate(
                ['name' => $item['car']['name']],
                [
                    'image' => $item['car']['image'],
                    'price' => $item['car']['price'],
                    'cost_per_km' => $item['car']['cost_per_km'],
                    'width' => $item['car']['width'],
                    'height' => $item['car']['height'],
                    'order' => $item['car']['order'],
                    'status' => 'active',
                ]
            );

            CarDetail::query()->where('car_id', $car->id)->delete();

            foreach ($item['details'] as $detail) {
                CarDetail::query()->create([
                    'car_id' => $car->id,
                    'name' => $detail['name'],
                    'value' => $detail['value'],
                    'image' => $detail['image'],
                ]);
            }
        }
    }
}
