<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAndEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ownerRole = Role::where('name', 'owner')->first();
        $superadminRole = Role::where('name', 'superadmin')->first();
        $financeRole = Role::where('name', 'finance_pusat')->first();
        $managerRole = Role::where('name', 'store_manager')->first();
        $crewRole = Role::where('name', 'crew_kasir')->first();

        $tebetBranch = Branch::where('branch_code', 'MMY-JKT-01')->first();
        $margondaBranch = Branch::where('branch_code', 'MMY-DPK-01')->first();

        // 1. Owner
        User::updateOrCreate(
            ['email' => 'owner@momoyo.id'],
            [
                'role_id' => $ownerRole?->id,
                'name' => 'Budi Hartono (Owner)',
                'username' => 'owner_budi',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Superadmin
        User::updateOrCreate(
            ['email' => 'superadmin@momoyo.id'],
            [
                'role_id' => $superadminRole?->id,
                'name' => 'Rian Hidayat (Superadmin)',
                'username' => 'superadmin_rian',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 3. Finance Pusat
        User::updateOrCreate(
            ['email' => 'finance@momoyo.id'],
            [
                'role_id' => $financeRole?->id,
                'name' => 'Dewi Lestari (Finance Pusat)',
                'username' => 'finance_dewi',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 4. Store Manager (Tebet)
        $managerUser = User::updateOrCreate(
            ['email' => 'manager.tebet@momoyo.id'],
            [
                'role_id' => $managerRole?->id,
                'name' => 'Agus Pratama (Store Manager Tebet)',
                'username' => 'manager_tebet',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($tebetBranch) {
            Employee::updateOrCreate(
                ['nik' => 'EMP-MMY-001'],
                [
                    'user_id' => $managerUser->id,
                    'full_name' => 'Agus Pratama',
                    'phone_number' => '081234567890',
                    'current_branch_id' => $tebetBranch->id,
                    'status' => 'active',
                ]
            );
        }

        // 5. Crew Kasir (Tebet)
        $kasirUser = User::updateOrCreate(
            ['email' => 'kasir.tebet@momoyo.id'],
            [
                'role_id' => $crewRole?->id,
                'name' => 'Siti Rahma (Kasir Tebet)',
                'username' => 'kasir_tebet',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($tebetBranch) {
            Employee::updateOrCreate(
                ['nik' => 'EMP-MMY-002'],
                [
                    'user_id' => $kasirUser->id,
                    'full_name' => 'Siti Rahma',
                    'phone_number' => '081298765432',
                    'current_branch_id' => $tebetBranch->id,
                    'status' => 'active',
                ]
            );
        }

        // 6. Crew Kasir (Margonda)
        $kasirMargondaUser = User::updateOrCreate(
            ['email' => 'kasir.margonda@momoyo.id'],
            [
                'role_id' => $crewRole?->id,
                'name' => 'Fajar Santoso (Kasir Margonda)',
                'username' => 'kasir_margonda',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($margondaBranch) {
            Employee::updateOrCreate(
                ['nik' => 'EMP-MMY-003'],
                [
                    'user_id' => $kasirMargondaUser->id,
                    'full_name' => 'Fajar Santoso',
                    'phone_number' => '081377889900',
                    'current_branch_id' => $margondaBranch->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
