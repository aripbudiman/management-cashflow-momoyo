<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'branch_code' => 'MMY-JKT-01',
                'branch_name' => 'Momoyo Tebet Raya',
                'address' => 'Jl. Tebet Raya No. 45, Tebet Barat, Jakarta Selatan 12810',
                'latitude' => -6.229728,
                'longitude' => 106.855364,
                'radius_meters' => 50,
                'is_active' => true,
            ],
            [
                'branch_code' => 'MMY-DPK-01',
                'branch_name' => 'Momoyo Margonda',
                'address' => 'Jl. Margonda Raya No. 230, Beji, Kota Depok 16423',
                'latitude' => -6.373200,
                'longitude' => 106.832700,
                'radius_meters' => 60,
                'is_active' => true,
            ],
            [
                'branch_code' => 'MMY-TNG-01',
                'branch_name' => 'Momoyo Gading Serpong',
                'address' => 'Ruko Boulevard Gading Serpong Blok AA3 No. 12, Tangerang 15810',
                'latitude' => -6.241500,
                'longitude' => 106.628800,
                'radius_meters' => 50,
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(['branch_code' => $branch['branch_code']], $branch);
        }
    }
}
