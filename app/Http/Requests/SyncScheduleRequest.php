<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'month' => ['required', 'date_format:Y-m'],
            'entries' => ['required', 'array', 'min:1', 'max:3000'],
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
            $month = $this->input('month');
            $entries = collect($this->input('entries'));

            if ($entries->contains(fn ($e) => ! str_starts_with($e['date'], $month))) {
                $validator->errors()->add('entries', 'Ada tanggal di luar bulan yang dipilih.');
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

    public function month(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->input('month').'-01')->startOfDay();
    }
}
