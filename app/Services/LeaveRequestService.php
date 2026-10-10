<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Repositories\LeaveRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public const ANNUAL_QUOTA = 12;

    public const TYPE_LABELS = [
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'cuti_tahunan' => 'Cuti Tahunan',
    ];

    public function __construct(private readonly LeaveRequestRepository $leaves) {}

    /**
     * @return array{quota: int, used: int, pending: int, remaining: int, year: int}
     */
    public function quota(Employee $employee, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');
        $used = $this->leaves->annualLeaveDays($employee->id, $year, ['approved']);
        $pending = $this->leaves->annualLeaveDays($employee->id, $year, ['pending']);

        return [
            'quota' => self::ANNUAL_QUOTA,
            'used' => $used,
            'pending' => $pending,
            'remaining' => max(0, self::ANNUAL_QUOTA - $used - $pending),
            'year' => $year,
        ];
    }

    /**
     * @param  array{type: string, start_date: string, end_date: string, reason: string}  $data
     */
    public function submit(Employee $employee, array $data, ?UploadedFile $attachment): LeaveRequest
    {
        if (! $employee->current_branch_id) {
            throw ValidationException::withMessages(['start_date' => 'Anda belum terdaftar pada cabang aktif. Hubungi Admin Pusat.']);
        }

        if ($this->leaves->hasOverlap($employee->id, $data['start_date'], $data['end_date'])) {
            throw ValidationException::withMessages(['start_date' => 'Sudah ada pengajuan lain pada rentang tanggal ini.']);
        }

        $totalDays = $this->countDays($employee, $data['start_date'], $data['end_date']);

        if ($data['type'] === 'cuti_tahunan') {
            $year = (int) Carbon::parse($data['start_date'])->format('Y');
            $taken = $this->leaves->annualLeaveDays($employee->id, $year, ['approved', 'pending']);
            if ($taken + $totalDays > self::ANNUAL_QUOTA) {
                $left = max(0, self::ANNUAL_QUOTA - $taken);
                throw ValidationException::withMessages(['end_date' => "Sisa jatah cuti tahun {$year} hanya {$left} hari, pengajuan ini {$totalDays} hari."]);
            }
        }

        $path = null;
        if ($attachment) {
            $path = Storage::disk(config('filesystems.default'))->putFile("employees/{$employee->id}/leaves", $attachment);
        }

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'branch_id' => $employee->current_branch_id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'attachment_url' => $path ?: null,
            'status' => 'pending',
        ]);
    }

    /**
     * Setujui: jadwal shift/libur karyawan di rentang tanggal tersebut otomatis dihapus.
     */
    public function approve(LeaveRequest $leave, User $approver, ?string $notes): void
    {
        $this->ensurePending($leave);

        if ($leave->type === 'cuti_tahunan') {
            $year = (int) $leave->start_date->format('Y');
            $used = $this->leaves->annualLeaveDays($leave->employee_id, $year, ['approved'], $leave->id);
            if ($used + $leave->total_days > self::ANNUAL_QUOTA) {
                throw ValidationException::withMessages(['status' => 'Jatah cuti tahunan karyawan ini tidak mencukupi.']);
            }
        }

        DB::transaction(function () use ($leave, $approver, $notes) {
            $leave->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            EmployeeSchedule::query()
                ->where('employee_id', $leave->employee_id)
                ->whereBetween('date', [$leave->start_date->toDateString(), $leave->end_date->toDateString()])
                ->delete();
        });
    }

    public function reject(LeaveRequest $leave, User $approver, string $notes): void
    {
        $this->ensurePending($leave);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    public function cancel(LeaveRequest $leave): void
    {
        $this->ensurePending($leave);

        if ($leave->attachment_url) {
            Storage::disk(config('filesystems.default'))->delete($leave->attachment_url);
        }
        $leave->delete();
    }

    /**
     * Hari yang dihitung = hari kalender dikurangi hari libur terjadwal karyawan.
     */
    private function countDays(Employee $employee, string $start, string $end): int
    {
        $calendar = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        $offDays = EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('is_off', true)
            ->whereBetween('date', [$start, $end])
            ->count();

        return max(1, $calendar - $offDays);
    }

    private function ensurePending(LeaveRequest $leave): void
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Pengajuan ini sudah diproses.']);
        }
    }
}
