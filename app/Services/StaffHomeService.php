<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyCashflow;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Repositories\LeaveRequestRepository;
use Carbon\Carbon;

class StaffHomeService
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly LeaveRequestRepository $leaves,
    ) {}

    /**
     * Data Beranda mobile untuk staff cabang.
     *
     * @return array<string, mixed>
     */
    public function data(Employee $employee): array
    {
        $employee->loadMissing('currentBranch');
        $branch = $employee->currentBranch;
        $today = Carbon::today()->toDateString();

        $schedule = $this->schedules->forDate($employee, $today);
        $leave = $this->leaves->approvedOnDate($employee->id, $today);

        // Jika ada shift terjadwal hari ini, hanya shift itu yang bisa dipakai saat clock in
        $shifts = $branch ? Shift::where('branch_id', $branch->id)->orderBy('start_time')->get() : collect();
        if ($schedule && ! $schedule->is_off && $schedule->shift_id) {
            $shifts = $shifts->where('id', $schedule->shift_id)->values();
        }

        $attendance = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();

        return [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'nik' => $employee->nik,
                'face_photo_url' => $employee->face_photo_url,
                'is_face_registered' => $employee->hasFaceRegistered(),
                'attendance_type' => $employee->attendance_type,
            ],
            'branch' => $branch?->only(['id', 'branch_name', 'branch_code', 'latitude', 'longitude', 'radius_meters']),
            'shifts' => $shifts,
            'todayAttendance' => $attendance ? [
                'clock_in_time' => $attendance->clock_in_time ? substr($attendance->clock_in_time, 0, 5) : null,
                'clock_out_time' => $attendance->clock_out_time ? substr($attendance->clock_out_time, 0, 5) : null,
                'status' => $attendance->status,
            ] : null,
            'todaySchedule' => $schedule ? [
                'is_off' => $schedule->is_off,
                'shift_name' => $schedule->shift_name,
                'start_time' => $schedule->start_time ? substr($schedule->start_time, 0, 5) : null,
                'end_time' => $schedule->end_time ? substr($schedule->end_time, 0, 5) : null,
            ] : null,
            'todayLeave' => $leave ? (LeaveRequestService::TYPE_LABELS[$leave->type] ?? $leave->type) : null,
            'cashflow' => $branch ? $this->cashflowSummary($branch->id, $today) : null,
            // Kepala Toko: ringkasan tugas pengelolaan cabang
            'manager' => $employee->user?->isKepalaToko() ? [
                'pending_leaves' => $branch
                    ? LeaveRequest::where('branch_id', $branch->id)->where('status', 'pending')
                        ->where('employee_id', '!=', $employee->id)
                        ->whereDoesntHave('employee.user.role', fn ($q) => $q->where('name', 'kepala_toko'))
                        ->count()
                    : 0,
            ] : null,
            'todayDate' => $today,
            'todayDateFormatted' => Carbon::today()->translatedFormat('l, d F Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cashflowSummary(int $branchId, string $date): ?array
    {
        $cashflow = DailyCashflow::with('details')->where('branch_id', $branchId)->where('report_date', $date)->first();
        if (! $cashflow) {
            return null;
        }

        $wallets = $cashflow->details->pluck('nominal', 'payment_method')->map(fn ($v) => (float) $v);

        return [
            'status' => $cashflow->status,
            'total_omset' => (float) $cashflow->total_omset,
            'total_expense' => (float) $cashflow->total_expense,
            'total_setor' => (float) $cashflow->total_net_cash,
            'tunai' => (float) ($wallets['tunai'] ?? 0),
            'qris' => (float) ($wallets['qris'] ?? 0),
            'lainnya' => (float) $wallets->except(['tunai', 'qris'])->sum(),
        ];
    }
}
