<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = [
            ['name' => 'The Tourist Hotel & Cafe Akihabara', 'phone' => '03-6806-0308', 'google_maps_url' => 'https://maps.google.com/?cid=1362432029353315237', 'address' => '1-chōme-6-6 Taitō, Taito City, Tokyo 110-0016, Japan'],
            ['name' => 'Asakusa View Hotel Annex Rokku', 'phone' => '0570-003-235', 'google_maps_url' => 'https://maps.google.com/?cid=16266720147423180527', 'address' => '2-chōme-9-10 Asakusa, Taito City, Tokyo 111-0032, Japan'],
            ['name' => 'OMO3 Tokyo Akasaka by Hoshino Resorts', 'phone' => '050-3134-8095', 'google_maps_url' => 'https://maps.google.com/?cid=5458771663436051778', 'address' => '4-chōme-3-2 Akasaka, Minato City, Tokyo 107-0052, Japan'],
            ['name' => 'HOTEL GROOVE SHINJUKU, A PARKROYAL Hotel', 'phone' => '03-6233-8888', 'google_maps_url' => 'https://maps.google.com/?cid=13938664711029483214', 'address' => '東急歌舞伎町タワ, 1-chōme-29-1 Kabukichō, Shinjuku City, Tokyo 160-0021, Japan'],
            ['name' => 'The Blossom Hibiya', 'phone' => '03-3591-8702', 'google_maps_url' => 'https://maps.google.com/?cid=6461953290758206689', 'address' => '1-chōme-1-13 Shinbashi, Minato City, Tokyo 105-0004, Japan'],
            ['name' => 'Hotel Metropolitan Tokyo Ikebukuro', 'phone' => '03-3980-1111', 'google_maps_url' => 'https://maps.google.com/?cid=1669693716906684867', 'address' => '1-chōme-6-1 Nishiikebukuro, Toshima City, Tokyo 171-8505, Japan'],
            ['name' => 'Keio Plaza Hotel Tokyo PremierGrand', 'phone' => '03-3344-0111', 'google_maps_url' => 'https://maps.google.com/?cid=15123209713363761362', 'address' => '2-chōme-2-1 Nishishinjuku, Shinjuku City, Tokyo 160-0023, Japan'],
            ['name' => 'Richmond Hotel Premier Asakusa', 'phone' => '03-5806-3155', 'google_maps_url' => 'https://maps.google.com/?cid=14638168679095614495', 'address' => '2-chōme-6-7 Asakusa, Taito City, Tokyo 111-0032, Japan'],
            ['name' => 'The Knot Tokyo Shinjuku', 'phone' => '03-3375-6511', 'google_maps_url' => 'https://maps.google.com/?cid=3571431522569493413', 'address' => '4-chōme-31-1 Nishishinjuku, Shinjuku City, Tokyo 160-0023, Japan'],
            ['name' => 'DEL style Ikebukuro Higashiguchi by Daiwa Roynet Hotel', 'phone' => '03-6811-2880', 'google_maps_url' => 'https://maps.google.com/?cid=18083700517211325424', 'address' => '1-chōme-20-8 Minamiikebukuro, Toshima City, Tokyo 171-0022, Japan'],
        ];

        foreach ($hotels as $hotel) {
            Hotel::updateOrCreate(
                ['name' => $hotel['name']],
                [
                    'phone' => $hotel['phone'],
                    'google_maps_url' => $hotel['google_maps_url'],
                    'address' => $hotel['address'],
                ]
            );
        }
    }
}