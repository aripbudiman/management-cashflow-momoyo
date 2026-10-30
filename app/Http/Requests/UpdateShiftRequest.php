<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->managesBranch((int) $this->route('shift')?->branch_id);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Shift tidak boleh dipindah ke cabang lain
            'branch_id' => ['required', 'integer', Rule::in([$this->route('shift')?->branch_id])],
            'shift_name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ];
    }
}
