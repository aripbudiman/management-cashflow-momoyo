<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin_pusat',
                'display_name' => 'Admin Pusat',
                'description' => 'Akses penuh operasional pusat, monitoring seluruh cabang, rekonsiliasi keuangan, dan kelola master karyawan.',
            ],
            [
                'name' => 'staff_cabang',
                'display_name' => 'Staff Cabang',
                'description' => 'Akses operasional gerai cabang untuk absensi selfie GPS, closing kasir harian, kas kecil, dan perizinan.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
