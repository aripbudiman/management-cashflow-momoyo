<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAndEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Hanya 1 akun default (Admin Pusat) untuk login perdana client.
     * Cabang dan karyawan/pengguna lainnya akan ditambahkan mandiri oleh client melalui sistem.
     */
    public function run(): void
    {
        $adminPusatRole = Role::where('name', 'admin_pusat')->first();

        User::updateOrCreate(
            ['email' => 'admin@momoyo.id'],
            [
                'role_id' => $adminPusatRole?->id,
                'name' => 'Admin Pusat Momoyo',
                'username' => 'admin_pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
