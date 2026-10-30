<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestAccountSeeder extends Seeder
{
    /**
     * Akun uji untuk tiap role (password semua: "password"). Idempotent, hanya dijalankan di non-production.
     *
     * admin@momoyo.id   Admin Pusat   (absen fleksibel, hanya memantau)
     * kepala@momoyo.id  Kepala Toko   (menyusun jadwal/shift & menyetujui izin cabangnya)
     * staff@momoyo.id   Staff Cabang
     * staff2@momoyo.id  Staff Cabang  (untuk uji alur pengajuan izin & jadwal)
     */
    public function run(): void
    {
        $branch = $this->testBranch();

        $accounts = [
            ['admin@momoyo.id', 'Admin Pusat Momoyo', 'admin_pusat', 'admin_pusat', 'EMP-TEST-ADM', 'flexible'],
            ['kepala@momoyo.id', 'Kepala Toko Test', 'kepala_toko', 'kepala_toko', 'EMP-TEST-KT', 'radius'],
            ['staff@momoyo.id', 'Staff Test Satu', 'staff_cabang', 'staff_test1', 'EMP-TEST-S1', 'radius'],
            ['staff2@momoyo.id', 'Staff Test Dua', 'staff_cabang', 'staff_test2', 'EMP-TEST-S2', 'radius'],
        ];

        foreach ($accounts as [$email, $name, $roleName, $username, $nik, $attendanceType]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'role_id' => Role::where('name', $roleName)->value('id'),
                    'name' => $name,
                    'username' => $username,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nik' => $nik,
                    'full_name' => $name,
                    'phone_number' => '081200000000',
                    'current_branch_id' => $branch->id,
                    'status' => 'active',
                    'attendance_type' => $attendanceType,
                    'birth_place' => 'Bekasi',
                    'birth_date' => '2000-01-01',
                    'address' => 'Alamat akun uji',
                    'bank_name' => 'Bank Mandiri',
                    'bank_account_number' => '1234567890',
                    'bank_account_name' => strtoupper($name),
                ]
            );
        }
    }

    /**
     * Cabang uji: pakai cabang aktif pertama; jika belum ada cabang, buat satu beserta shift standarnya.
     */
    private function testBranch(): Branch
    {
        $branch = Branch::where('is_active', true)->orderBy('id')->first();
        if ($branch) {
            return $branch;
        }

        $branch = Branch::create([
            'branch_code' => 'MMY-TEST',
            'branch_name' => 'Momoyo Cabang Uji',
            'address' => 'Alamat cabang uji',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        foreach ([['Shift Pagi (Opening)', '09:00', '17:00'], ['Shift Sore (Closing)', '15:00', '23:00']] as [$name, $start, $end]) {
            Shift::create(['branch_id' => $branch->id, 'shift_name' => $name, 'start_time' => $start, 'end_time' => $end, 'late_tolerance_minutes' => 15]);
        }

        return $branch;
    }
}
