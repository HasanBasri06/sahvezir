<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use Illuminate\Database\Seeder;

class CityDistrictSeeder extends Seeder
{
    /**
     * Tüm Türkiye illeri (81 il) - ProvinceID sırasına göre (district.json ile eşleşmesi için)
     */
    private function getProvinces(): array
    {
        return [
            ['name' => 'Adana', 'code' => 'ADA'],
            ['name' => 'Adıyaman', 'code' => 'ADI'],
            ['name' => 'Afyonkarahisar', 'code' => 'AFY'],
            ['name' => 'Ağrı', 'code' => 'AGR'],
            ['name' => 'Amasya', 'code' => 'AMA'],
            ['name' => 'Ankara', 'code' => 'ANK'],
            ['name' => 'Antalya', 'code' => 'ANT'],
            ['name' => 'Artvin', 'code' => 'ART'],
            ['name' => 'Aydın', 'code' => 'AYD'],
            ['name' => 'Balıkesir', 'code' => 'BAL'],
            ['name' => 'Bilecik', 'code' => 'BIL'],
            ['name' => 'Bingöl', 'code' => 'BIN'],
            ['name' => 'Bitlis', 'code' => 'BIT'],
            ['name' => 'Bolu', 'code' => 'BOL'],
            ['name' => 'Burdur', 'code' => 'BUR'],
            ['name' => 'Bursa', 'code' => 'BRS'],
            ['name' => 'Çanakkale', 'code' => 'CAN'],
            ['name' => 'Çankırı', 'code' => 'CKR'],
            ['name' => 'Çorum', 'code' => 'COR'],
            ['name' => 'Denizli', 'code' => 'DEN'],
            ['name' => 'Diyarbakır', 'code' => 'DIY'],
            ['name' => 'Edirne', 'code' => 'EDI'],
            ['name' => 'Elazığ', 'code' => 'ELA'],
            ['name' => 'Erzincan', 'code' => 'ERC'],
            ['name' => 'Erzurum', 'code' => 'ERZ'],
            ['name' => 'Eskişehir', 'code' => 'ESK'],
            ['name' => 'Gaziantep', 'code' => 'GAZ'],
            ['name' => 'Giresun', 'code' => 'GIR'],
            ['name' => 'Gümüşhane', 'code' => 'GUM'],
            ['name' => 'Hakkari', 'code' => 'HAK'],
            ['name' => 'Hatay', 'code' => 'HAT'],
            ['name' => 'Isparta', 'code' => 'ISP'],
            ['name' => 'Mersin', 'code' => 'MER'],  // Eski adı: İçel
            ['name' => 'İstanbul', 'code' => 'IST'],
            ['name' => 'İzmir', 'code' => 'IZM'],
            ['name' => 'Kars', 'code' => 'KAR'],
            ['name' => 'Kastamonu', 'code' => 'KAS'],
            ['name' => 'Kayseri', 'code' => 'KAY'],
            ['name' => 'Kırklareli', 'code' => 'KIR'],
            ['name' => 'Kırşehir', 'code' => 'KRS'],
            ['name' => 'Kocaeli', 'code' => 'KOC'],
            ['name' => 'Konya', 'code' => 'KON'],
            ['name' => 'Kütahya', 'code' => 'KUT'],
            ['name' => 'Malatya', 'code' => 'MAL'],
            ['name' => 'Manisa', 'code' => 'MAN'],
            ['name' => 'Kahramanmaraş', 'code' => 'KAH'],
            ['name' => 'Mardin', 'code' => 'MAR'],
            ['name' => 'Muğla', 'code' => 'MUG'],
            ['name' => 'Muş', 'code' => 'MUS'],
            ['name' => 'Nevşehir', 'code' => 'NEV'],
            ['name' => 'Niğde', 'code' => 'NIG'],
            ['name' => 'Ordu', 'code' => 'ORD'],
            ['name' => 'Rize', 'code' => 'RIZ'],
            ['name' => 'Sakarya', 'code' => 'SAK'],
            ['name' => 'Samsun', 'code' => 'SAM'],
            ['name' => 'Siirt', 'code' => 'SII'],
            ['name' => 'Sinop', 'code' => 'SIN'],
            ['name' => 'Sivas', 'code' => 'SIV'],
            ['name' => 'Tekirdağ', 'code' => 'TEK'],
            ['name' => 'Tokat', 'code' => 'TOK'],
            ['name' => 'Trabzon', 'code' => 'TRA'],
            ['name' => 'Tunceli', 'code' => 'TUN'],
            ['name' => 'Şanlıurfa', 'code' => 'SAN'],
            ['name' => 'Uşak', 'code' => 'USA'],
            ['name' => 'Van', 'code' => 'VAN'],
            ['name' => 'Yozgat', 'code' => 'YOZ'],
            ['name' => 'Zonguldak', 'code' => 'ZON'],
            ['name' => 'Aksaray', 'code' => 'AKS'],
            ['name' => 'Bayburt', 'code' => 'BAY'],
            ['name' => 'Karaman', 'code' => 'KRM'],
            ['name' => 'Kırıkkale', 'code' => 'KRK'],
            ['name' => 'Batman', 'code' => 'BAT'],
            ['name' => 'Şırnak', 'code' => 'SIR'],
            ['name' => 'Bartın', 'code' => 'BRT'],
            ['name' => 'Ardahan', 'code' => 'ARD'],
            ['name' => 'Iğdır', 'code' => 'IGD'],
            ['name' => 'Yalova', 'code' => 'YAL'],
            ['name' => 'Karabük', 'code' => 'KRB'],
            ['name' => 'Kilis', 'code' => 'KIL'],
            ['name' => 'Osmaniye', 'code' => 'OSM'],
            ['name' => 'Düzce', 'code' => 'DUZ'],
        ];
    }

    public function run(): void
    {
        District::query()->delete();
        City::query()->delete();

        $provinces = $this->getProvinces();
        foreach ($provinces as $p) {
            City::create([
                'name' => $p['name'],
                'code' => $p['code'],
            ]);
        }

        $districtUrl = 'https://raw.githubusercontent.com/liophin/TurkeyProvinces-2022/main/district.json';
        $json = @file_get_contents($districtUrl);

        if ($json === false || $json === '') {
            $this->command->warn('İlçe listesi yüklenemedi. Sadece iller eklendi. (URL: ' . $districtUrl . ')');
            return;
        }

        $districts = json_decode($json, true);
        if (! is_array($districts)) {
            $this->command->warn('İlçe JSON parse edilemedi.');
            return;
        }

        $count = 0;
        foreach ($districts as $d) {
            $cityId = (int) ($d['ProvinceID'] ?? $d['ProvinceId'] ?? 0);
            $name = $d['Title'] ?? $d['title'] ?? '';
            if ($cityId < 1 || $cityId > 81 || $name === '') {
                continue;
            }
            District::create([
                'city_id' => $cityId,
                'name' => $name,
            ]);
            $count++;
        }

        $this->command->info('81 il ve ' . $count . ' ilçe eklendi.');
    }
}
