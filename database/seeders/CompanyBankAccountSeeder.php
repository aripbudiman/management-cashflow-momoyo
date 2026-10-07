<?php

namespace Database\Seeders;

use App\Models\CompanyBankAccount;
use Illuminate\Database\Seeder;

class CompanyBankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'bank_name' => 'BCA (Bank Central Asia)',
                'account_number' => '8830192831',
                'account_holder_name' => 'PT MOMOYO KULINER NUSANTARA',
                'is_active' => true,
            ],
            [
                'bank_name' => 'Mandiri',
                'account_number' => '1370019283712',
                'account_holder_name' => 'PT MOMOYO KULINER NUSANTARA',
                'is_active' => true,
            ],
            [
                'bank_name' => 'BRI (Bank Rakyat Indonesia)',
                'account_number' => '034101002341308',
                'account_holder_name' => 'PT MOMOYO KULINER NUSANTARA',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            CompanyBankAccount::updateOrCreate(
                ['account_number' => $acc['account_number']],
                $acc
            );
        }
    }
}
