<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Repositories\LeaveRequestRepository;
use App\Repositories\ScheduleRepository;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepository $schedules,
        private readonly LeaveRequestRepository $leaves,
    ) {}

    /**
     * Data grid jadwal karyawan x tanggal untuk satu cabang dan bulan.
     *
     * @return array<string, mixed>
     */
    public function grid(Branch $branch, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $schedules = $this->schedules->forBranchBetween($branch->id, $start, $end);

        // Karyawan aktif cabang ini + karyawan yang pernah dijadwalkan di cabang ini pada bulan tsb (snapshot)
        $employees = Employee::query()
            ->where(function ($q) use ($branch, $schedules) {
                $q->where(fn ($w) => $w->where('current_branch_id', $branch->id)->where('status', 'active'))
                    ->orWhereIn('id', $schedules->pluck('employee_id')->unique());
            })
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nik', 'current_branch_id', 'status']);

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = [
                'date' => $d->toDateString(),
                'day' => $d->day,
                'weekday' => $d->translatedFormat('D'),
                'is_sunday' => $d->isSunday(),
            ];
        }

        return [
            'days' => $days,
            'employees' => $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'nik' => $e->nik,
                'editable' => $e->current_branch_id === $branch->id && $e->status === 'active',
            ])->values(),
            'shifts' => Shift::where('branch_id', $branch->id)->orderBy('start_time')->get(),
            'schedules' => $schedules->map(fn (EmployeeSchedule $s) => [
                'employee_id' => $s->employee_id,
                'date' => $s->date->toDateString(),
                'is_off' => $s->is_off,
                'shift_id' => $s->shift_id,
            ])->values(),
        ];
    }

    /**
     * Simpan perubahan jadwal. type: 'shift' | 'off' | null (hapus).
     *
     * @param  array<int, array{employee_id: int, date: string, type: ?string, shift_id: ?int}>  $entries
     */
    public function save(Branch $branch, array $entries, int $userId): void
    {
        $shifts = Shift::where('branch_id', $branch->id)->get()->keyBy('id');
        $now = now();
        $upserts = [];
        $deletes = [];

        foreach ($entries as $entry) {
            $type = $entry['type'] ?? null;

            if ($type === null || $type === '') {
                $deletes[$entry['employee_id']][] = $entry['date'];

                continue;
            }

            $shift = $type === 'shift' ? $shifts->get((int) $entry['shift_id']) : null;

            $upserts[] = [
                'employee_id' => $entry['employee_id'],
                'branch_id' => $branch->id,
                'date' => $entry['date'],
                'is_off' => $type === 'off',
                'shift_id' => $shift?->id,
                'shift_name' => $shift?->shift_name,
                'start_time' => $shift?->start_time,
                'end_time' => $shift?->end_time,
                'late_tolerance_minutes' => $shift?->late_tolerance_minutes,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($branch, $upserts, $deletes) {
            $this->schedules->upsertMany($upserts);

            foreach ($deletes as $employeeId => $dates) {
                $this->schedules->deleteDates($branch->id, (int) $employeeId, $dates);
            }
        });
    }

    public function forDate(Employee $employee, string $date): ?EmployeeSchedule
    {
        return $this->schedules->findForDate($employee->id, $date);
    }

    /**
     * Periode absensi satu karyawan (bulan berjalan): hari lewat + hari mendatang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function employeePeriod(Employee $employee, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = Carbon::today();

        $schedules = $this->schedules->forEmployeeBetween($employee->id, $start, $end)
            ->keyBy(fn (EmployeeSchedule $s) => $s->date->toDateString());

        $leaveByDate = [];
        foreach ($this->leaves->approvedForEmployeeBetween($employee->id, $start, $end) as $leave) {
            foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $d) {
                $leaveByDate[$d->toDateString()] = LeaveRequestService::TYPE_LABELS[$leave->type] ?? $leave->type;
            }
        }

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->date->toDateString());

        $period = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $schedule = $schedules->get($key);
            $attendance = $attendances->get($key);

            $period[] = [
                'date' => $key,
                'day' => $d->day,
                'weekday' => $d->translatedFormat('l'),
                'is_today' => $d->isSameDay($today),
                'is_past' => $d->lt($today),
                'is_off' => (bool) $schedule?->is_off,
                'shift_name' => $schedule?->shift_name,
                'start_time' => $schedule?->start_time ? substr($schedule->start_time, 0, 5) : null,
                'end_time' => $schedule?->end_time ? substr($schedule->end_time, 0, 5) : null,
                'is_scheduled' => $schedule !== null,
                'leave_label' => $leaveByDate[$key] ?? null,
                'attendance' => $attendance ? [
                    'status' => $attendance->status,
                    'clock_in_time' => $attendance->clock_in_time ? substr($attendance->clock_in_time, 0, 5) : null,
                    'clock_out_time' => $attendance->clock_out_time ? substr($attendance->clock_out_time, 0, 5) : null,
                    'clock_in_photo_url' => $attendance->clock_in_photo_full_url,
                    'clock_out_photo_url' => $attendance->clock_out_photo_full_url,
                    'clock_in_distance_meters' => $attendance->clock_in_distance_meters,
                    'clock_out_distance_meters' => $attendance->clock_out_distance_meters,
                    'notes' => $attendance->notes,
                    'location_mode' => $attendance->location_mode,
                    'clock_in_lat' => $attendance->clock_in_lat,
                    'clock_in_long' => $attendance->clock_in_long,
                    'clock_out_lat' => $attendance->clock_out_lat,
                    'clock_out_long' => $attendance->clock_out_long,
                ] : null,
            ];
        }

        return $period;
    }
}
