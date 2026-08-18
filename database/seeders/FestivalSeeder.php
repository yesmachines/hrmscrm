<?php

namespace Database\Seeders;

use App\Models\Festival;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FestivalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            1 => ['Onam', 'Vishu', 'Good Friday', 'Eid al-Fitr', 'Eid al-Adha', 'Christmas', 'Pongal', 'Deepavali'], // India
            2 => ['Eid al-Fitr', 'Eid al-Adha', 'Christmas'], // Pakistan
            3 => ['Christmas', 'Good Friday', 'Eid al-Fitr', 'Eid al-Adha'], // Uganda
            4 => ['Christmas', 'Good Friday', 'Eid al-Fitr', 'Eid al-Adha'], // Ghana
            5 => ['Christmas', 'Good Friday', 'Eid al-Fitr', 'Eid al-Adha'], // Philippines
        ];

        foreach ($mappings as $countryId => $festivals) {
            foreach ($festivals as $festivalName) {
                // Get or create the festival
                $festival = Festival::firstOrCreate(
                    ['name' => $festivalName],
                    ['shortcode' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $festivalName), 0, 3)).date('y').rand(10, 99)]
                );

                // Attach to country if not already attached
                $exists = DB::table('festival_nationality')
                    ->where('festival_id', $festival->id)
                    ->where('country_id', $countryId)
                    ->exists();

                if (! $exists) {
                    DB::table('festival_nationality')->insert([
                        'festival_id' => $festival->id,
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
