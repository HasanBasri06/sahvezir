<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Admin panelinden yüklenen araç görselleri `public/assets/vehicles` altına yazılıyor ve
     * depoda olmadıkları için deploy sonrası siliniyor (canlıda 404). Dosyası diskte olmayan
     * araç / varyant kayıtlarını depoda bulunan katalog görsellerine yönlendirir.
     * Diskte dosyası olan (çalışan) görsellere dokunmaz.
     */
    private const CAR_IMAGES = [
        'Tır' => 'tır_tenteli.png',
        '10 Teker Kamyon' => '10_teker_kamyon.png',
        '8 Teker Kamyon' => '8_teker_kamyon.png',
        '6 Teker Kamyon' => '6_teker_kamyon.png',
        'Panelvan' => 'panelvan.png',
        'Minivan' => 'minivan.png',
        'Otomobil' => 'otomobil.png',
        'Motosiklet' => 'motosiklet.png',
        'Kırkayak' => 'kirkayak.png',
    ];

    /** [araç adı => [varyant değeri => dosya]] */
    private const DETAIL_IMAGES = [
        'Tır' => [
            'Tenteli Dorse' => 'tır_tenteli.png',
            'Damper Dorse' => 'tır_damper.png',
            'Lowbed Dorse' => 'tır_lowbed.png',
            'Tanker Dorse' => 'tır_tanker.png',
            'Silobas Dorse' => 'tır_silobas.png',
            'Container Dorse' => 'tır_container.png',
            'Açık Dorse' => 'acik_dorse.png',
            'Termokin Dorse' => 'termokin_tır.png',
        ],
        '10 Teker Kamyon' => ['Kapalı' => '10_teker_kamyon.png', 'Açık' => '10_teker_acik_kamyon.png'],
        '8 Teker Kamyon' => ['Kapalı' => '8_teker_kamyon.png', 'Açık' => '10_teker_acik_kamyon.png'],
        '6 Teker Kamyon' => ['Kapalı' => '6_teker_kamyon.png', 'Açık' => '10_teker_acik_kamyon.png'],
        'Panelvan' => ['Orta' => 'panelvan.png', 'Uzun' => 'uzun_panelvan.png', 'Ekstra Uzun' => 'uzun_panelvan.png'],
    ];

    public function up(): void
    {
        $exists = fn (?string $file) => $file !== null && trim($file) !== ''
            && is_file(public_path('assets/vehicles/'.basename(str_replace('\\', '/', $file))));

        foreach (DB::table('cars')->get(['id', 'name', 'image']) as $car) {
            $name = trim((string) $car->name);

            $fallback = self::CAR_IMAGES[$name] ?? null;
            if ($fallback && ! $exists($car->image) && $exists($fallback)) {
                DB::table('cars')->where('id', $car->id)->update(['image' => $fallback]);
            }

            foreach (self::DETAIL_IMAGES[$name] ?? [] as $value => $file) {
                foreach (DB::table('car_detail')->where('car_id', $car->id)->where('value', $value)->get(['id', 'image']) as $detail) {
                    if (! $exists($detail->image) && $exists($file)) {
                        DB::table('car_detail')->where('id', $detail->id)->update(['image' => $file]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Veri düzeltmesi; geri alınacak bir şey yok (eski adların dosyaları zaten mevcut değildi).
    }
};
