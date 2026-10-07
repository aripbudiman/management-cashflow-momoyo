<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $shifts = [
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Pagi (Opening)',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'late_tolerance_minutes' => 15,
                ],
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Sore (Closing)',
                    'start_time' => '15:00:00',
                    'end_time' => '23:00:00',
                    'late_tolerance_minutes' => 15,
                ],
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Middle',
                    'start_time' => '12:00:00',
                    'end_time' => '20:00:00',
                    'late_tolerance_minutes' => 15,
                ],
            ];

            foreach ($shifts as $s) {
                Shift::updateOrCreate(
                    [
                        'branch_id' => $s['branch_id'],
                        'shift_name' => $s['shift_name'],
                    ],
                    $s
                );
            }
        }
    }
}
