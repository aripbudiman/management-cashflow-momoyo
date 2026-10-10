<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->employee;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:izin,sakit,cuti_tahunan'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
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

            // Sakit boleh diajukan mundur maks 7 hari; izin & cuti harus mulai hari ini atau setelahnya
            $earliest = $this->input('type') === 'sakit' ? now()->subDays(7)->toDateString() : now()->toDateString();
            if ($this->input('start_date') < $earliest) {
                $validator->errors()->add('start_date', $this->input('type') === 'sakit'
                    ? 'Pengajuan sakit maksimal 7 hari ke belakang.'
                    : 'Tanggal mulai tidak boleh sebelum hari ini.');
            }
        }];
    }
}
