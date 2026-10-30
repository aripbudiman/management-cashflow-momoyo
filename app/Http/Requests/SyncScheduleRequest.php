<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncScheduleRequest extends FormRequest
{
    /**
     * Hanya Kepala Toko yang menyusun jadwal, dan hanya untuk cabangnya sendiri.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->managesBranch((int) $this->input('branch_id'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'week' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.date' => ['required', 'date_format:Y-m-d'],
            'entries.*.type' => ['nullable', 'in:shift,off'],
            'entries.*.shift_id' => ['nullable', 'required_if:entries.*.type,shift', 'integer', 'exists:shifts,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $branchId = (int) $this->input('branch_id');
            $weekStart = Carbon::parse($this->input('week'))->startOfDay();
            $entries = collect($this->input('entries'));

            if (! $weekStart->isMonday()) {
                $validator->errors()->add('week', 'Minggu harus dimulai hari Senin.');

                return;
            }

            $weekEnd = $weekStart->copy()->addDays(6)->toDateString();
            if ($entries->contains(fn ($e) => $e['date'] < $weekStart->toDateString() || $e['date'] > $weekEnd)) {
                $validator->errors()->add('entries', 'Ada tanggal di luar minggu yang dipilih.');
            }

            // Tanggal yang sudah lewat sudah terpakai (absensi & rekap), tidak boleh diubah
            if ($entries->contains(fn ($e) => $e['date'] < Carbon::today()->toDateString())) {
                $validator->errors()->add('entries', 'Jadwal tanggal yang sudah lewat tidak bisa diubah.');
            }

            $validShiftIds = Shift::where('branch_id', $branchId)->pluck('id');
            $shiftIds = $entries->where('type', 'shift')->pluck('shift_id')->map(fn ($v) => (int) $v)->unique();
            if ($shiftIds->diff($validShiftIds)->isNotEmpty()) {
                $validator->errors()->add('entries', 'Ada shift yang bukan milik cabang ini.');
            }

            $validEmployeeIds = Employee::where('current_branch_id', $branchId)->where('status', 'active')->pluck('id');
            $employeeIds = $entries->pluck('employee_id')->map(fn ($v) => (int) $v)->unique();
            if ($employeeIds->diff($validEmployeeIds)->isNotEmpty()) {
                $validator->errors()->add('entries', 'Ada karyawan yang tidak aktif di cabang ini.');
            }
        }];
    }
}
