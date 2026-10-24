<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemScheduleRequest extends FormRequest
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
            'tgl_rencana_mulai' => ['nullable', 'date'],
            'tgl_rencana_selesai' => ['nullable', 'date', 'after_or_equal:tgl_rencana_mulai'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tgl_rencana_mulai' => 'tanggal mulai', 'tgl_rencana_selesai' => 'tanggal selesai'];
    }
}
