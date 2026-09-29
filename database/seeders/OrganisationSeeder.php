<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisations = [
            [
                'org_name' => 'Yes Machinery',
                'short_name' => 'YM',
                'status' => 1,
            ],
            [
                'org_name' => 'Yes Machinery Saudi',
                'short_name' => 'YMS',
                'status' => 1,
            ],
            [
                'org_name' => 'Yes Automations',
                'short_name' => 'YA',
                'status' => 1,
            ],
        ];

        $firstOrg = Organisation::query()->find(1);
        if ($firstOrg && in_array(trim($firstOrg->org_name), ['1', 'Default Org', 'Acme Corp'], true)) {
            $firstOrg->update([
                'org_name' => $organisations[0]['org_name'],
                'short_name' => $organisations[0]['short_name'],
                'status' => 1,
            ]);
        }

        foreach ($organisations as $data) {
            Organisation::query()->updateOrCreate(
                ['org_name' => $data['org_name']],
                [
                    'short_name' => $data['short_name'],
                    'status' => $data['status'],
                ]
            );
        }
    }
}
