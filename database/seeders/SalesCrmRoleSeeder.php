<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesCrmRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'superadmin',
            'admin',
            'divisionmanager',
            'salesmanager',
            'coordinators',
            'operations',
            'satellite',
            'servicecoordinator',
            'servicemanager',
            'servicetechnician',
            'dcm',
            'hr',
            'finance',
            'test'
        ];

        $connection = DB::connection('salescrm');
        $now = now();

        foreach ($roles as $name) {
            $role = $connection->table('roles')
                ->where('name', $name)
                ->where('guard_name', 'web');

            if (! $role->exists()) {
                $connection->table('roles')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
