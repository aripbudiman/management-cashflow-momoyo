<?php

namespace App\Repositories;

use App\Models\EmployeeSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleRepository
{
    /**
     * @return Collection<int, EmployeeSchedule>
     */
    public function forBranchBetween(int $branchId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return EmployeeSchedule::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    /**
     * @return Collection<int, EmployeeSchedule>
     */
    public function forEmployeeBetween(int $employeeId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return EmployeeSchedule::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    public function findForDate(int $employeeId, string $date): ?EmployeeSchedule
    {
        return EmployeeSchedule::query()
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        EmployeeSchedule::upsert(
            $rows,
            ['employee_id', 'date'],
            ['branch_id', 'is_off', 'shift_id', 'shift_name', 'start_time', 'end_time', 'late_tolerance_minutes', 'created_by', 'updated_at'],
        );
    }

    /**
     * @param  array<int, string>  $dates
     */
    public function deleteDates(int $branchId, int $employeeId, array $dates): void
    {
        if ($dates === []) {
            return;
        }

        EmployeeSchedule::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employeeId)
            ->whereIn('date', $dates)
            ->delete();
    }
}
