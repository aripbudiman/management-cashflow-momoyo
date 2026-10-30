<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Hanya menyemai Role, Akun Bank Perusahaan, dan 1 Akun Default Admin Pusat.
     * Cabang dan User/Karyawan baru akan ditambahkan mandiri oleh client.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CompanyBankAccountSeeder::class,
            UserAndEmployeeSeeder::class,
        ]);

        // Akun uji tiap role, hanya di lingkungan non-production
        if (! app()->isProduction()) {
            $this->call(TestAccountSeeder::class);
        }
    }
}
