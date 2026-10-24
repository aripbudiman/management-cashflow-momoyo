<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', Rule::unique('projects', 'kode')->ignore($this->route('project'))],
            'nama' => ['required', 'string', 'max:150'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'pic_id' => ['nullable', 'integer', 'exists:employees,id'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'luas_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tgl_mulai_rencana' => ['nullable', 'date'],
            'tgl_selesai_rencana' => ['nullable', 'date', 'after_or_equal:tgl_mulai_rencana'],
            'nilai_kontrak' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'progress_mode' => ['sometimes', Rule::in(['weighted', 'simple'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode' => 'kode proyek',
            'nama' => 'nama proyek',
            'branch_id' => 'cabang',
            'tgl_selesai_rencana' => 'tanggal selesai rencana',
        ];
    }
}
