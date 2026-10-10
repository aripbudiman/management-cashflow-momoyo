<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Repositories\LeaveRequestRepository;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceRecapService
{
    public function __construct(private readonly LeaveRequestRepository $leaves) {}

    /**
     * Rekap kehadiran per karyawan untuk satu cabang dan bulan.
     * Memakai branch_id snapshot di jadwal/absensi, bukan cabang karyawan saat ini.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function recap(int $branchId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();
        $today = Carbon::today()->toDateString();

        $schedules = EmployeeSchedule::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->get(['employee_id', 'date', 'is_off', 'shift_name', 'start_time', 'end_time']);

        $attendances = Attendance::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->get(['employee_id', 'date', 'status', 'clock_in_time', 'clock_out_time']);

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $leavesByEmp = [];
        foreach ($this->leaves->approvedForBranchBetween($branchId, $monthStart, $monthEnd) as $leave) {
            foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $d) {
                if ($d->between($monthStart, $monthEnd)) {
                    $leavesByEmp[$leave->employee_id][$d->toDateString()] = $leave->type;
                }
            }
        }

        $employeeIds = $schedules->pluck('employee_id')
            ->merge($attendances->pluck('employee_id'))
            ->merge(array_keys($leavesByEmp))
            ->unique();

        $employees = Employee::query()
            ->where(function ($q) use ($branchId, $employeeIds) {
                $q->where(fn ($w) => $w->where('current_branch_id', $branchId)->where('status', 'active'))
                    ->orWhereIn('id', $employeeIds);
            })
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nik']);

        $schedulesByEmp = $schedules->groupBy('employee_id');
        $attendancesByEmp = $attendances->groupBy('employee_id');

        $rows = $employees->map(function (Employee $e) use ($schedulesByEmp, $attendancesByEmp, $leavesByEmp, $today) {
            $sch = $schedulesByEmp->get($e->id, collect());
            $att = $attendancesByEmp->get($e->id, collect());
            $attDates = $att->map(fn ($a) => $a->date->toDateString())->all();

            $shiftDays = $sch->where('is_off', false);
            $alpha = $shiftDays
                ->filter(fn ($s) => $s->date->toDateString() < $today && ! in_array($s->date->toDateString(), $attDates, true))
                ->count();

            return [
                'employee_id' => $e->id,
                'full_name' => $e->full_name,
                'nik' => $e->nik,
                'scheduled_days' => $shiftDays->count(),
                'off_days' => $sch->where('is_off', true)->count(),
                'on_time' => $att->where('status', 'hadir_tepat_waktu')->count(),
                'late' => $att->where('status', 'terlambat')->count(),
                'alpha' => $alpha,
                'leave_days' => count($leavesByEmp[$e->id] ?? []),
                'days' => $this->dailyDetail($sch, $att, $leavesByEmp[$e->id] ?? [], $today),
            ];
        })->values();

        return [
            'rows' => $rows,
            'totals' => [
                'scheduled_days' => (int) $rows->sum('scheduled_days'),
                'off_days' => (int) $rows->sum('off_days'),
                'on_time' => (int) $rows->sum('on_time'),
                'late' => (int) $rows->sum('late'),
                'alpha' => (int) $rows->sum('alpha'),
                'leave_days' => (int) $rows->sum('leave_days'),
            ],
        ];
    }

    /**
     * Rincian per tanggal satu karyawan (hanya tanggal yang punya jadwal atau absensi).
     *
     * @param  Collection<int, EmployeeSchedule>  $schedules
     * @param  Collection<int, Attendance>  $attendances
     * @param  array<string, string>  $leaves  tanggal => jenis izin/sakit/cuti_tahunan
     * @return array<int, array<string, mixed>>
     */
    private function dailyDetail(Collection $schedules, Collection $attendances, array $leaves, string $today): array
    {
        $schByDate = $schedules->keyBy(fn ($s) => $s->date->toDateString());
        $attByDate = $attendances->keyBy(fn ($a) => $a->date->toDateString());

        return $schByDate->keys()->merge($attByDate->keys())->merge(array_keys($leaves))->unique()->sort()->values()
            ->map(function (string $date) use ($schByDate, $attByDate, $today) {
                $sch = $schByDate->get($date);
                $att = $attByDate->get($date);
                $carbon = Carbon::parse($date);

                if ($att) {
                    $status = $att->status;
                } elseif (isset($leaves[$date])) {
                    $status = $leaves[$date];
                } elseif ($sch?->is_off) {
                    $status = 'libur';
                } elseif ($date < $today) {
                    $status = 'alpha';
                } else {
                    $status = 'belum';
                }

                return [
                    'date' => $date,
                    'weekday' => $carbon->translatedFormat('l'),
                    'status' => $status,
                    'shift_name' => $sch?->shift_name,
                    'start_time' => $sch?->start_time ? substr($sch->start_time, 0, 5) : null,
                    'end_time' => $sch?->end_time ? substr($sch->end_time, 0, 5) : null,
                    'clock_in_time' => $att?->clock_in_time ? substr($att->clock_in_time, 0, 5) : null,
                    'clock_out_time' => $att?->clock_out_time ? substr($att->clock_out_time, 0, 5) : null,
                ];
            })->all();
    }
}
