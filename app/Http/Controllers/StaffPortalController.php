<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffPortalController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    /**
     * Jadwal shift & libur bulanan milik karyawan yang login.
     */
    public function schedule(Request $request): Response
    {
        $employee = $request->user()->employee;
        $monthParam = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfDay();

        return Inertia::render('staff/Schedule', [
            'month' => $monthParam,
            'monthLabel' => $month->translatedFormat('F Y'),
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'period' => $employee ? $this->schedules->employeePeriod($employee, $month) : [],
        ]);
    }

    /**
     * Profil karyawan (data pribadi & rekening gaji) — hanya baca.
     */
    public function profile(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee?->load('currentBranch:id,branch_name,branch_code');

        return Inertia::render('staff/Profile', [
            'employee' => $employee ? [
                'full_name' => $employee->full_name,
                'nik' => $employee->nik,
                'face_photo_url' => $employee->face_photo_url,
                'birth_place' => $employee->birth_place,
                'birth_date' => $employee->birth_date?->toDateString(),
                'address' => $employee->address,
                'phone_number' => $employee->phone_number,
                'bank_name' => $employee->bank_name,
                'bank_account_number' => $employee->bank_account_number,
                'bank_account_name' => $employee->bank_account_name,
                'branch' => $employee->currentBranch?->only(['branch_name', 'branch_code']),
            ] : null,
            'account' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }
}
