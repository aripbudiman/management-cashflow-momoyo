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
                'name' => 'owner',
                'display_name' => 'Owner',
                'description' => 'Pemilik bisnis dengan akses monitoring ringkasan eksekutif seluruh cabang.',
            ],
            [
                'name' => 'superadmin',
                'display_name' => 'Superadmin',
                'description' => 'Akses penuh operasional seluruh cabang, master data, geofencing, dan rolling karyawan.',
            ],
            [
                'name' => 'finance_pusat',
                'display_name' => 'Finance Pusat',
                'description' => 'Verifikasi rekonsiliasi setoran harian cabang, pencocokan omzet, dan export laporan.',
            ],
            [
                'name' => 'store_manager',
                'display_name' => 'Store Manager / Head Kasir',
                'description' => 'Manajemen operasional cabang, input closing kasir harian, petty cash, dan approval izin crew.',
            ],
            [
                'name' => 'crew_kasir',
                'display_name' => 'Crew / Kasir Outlet',
                'description' => 'Karyawan outlet untuk clock-in/out selfie GPS, jadwal shift, dan pengajuan izin/sakit/cuti.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
