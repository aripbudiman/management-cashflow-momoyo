<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlacementRequest extends FormRequest
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
        $project = $this->route('project');

        return [
            'tgl_mulai' => ['required', 'date'],
            'tgl_selesai' => ['required', 'date', 'after_or_equal:tgl_mulai'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'revision_of' => ['nullable', 'integer', Rule::exists('project_placements', 'id')->where('project_id', $project?->id)],
            'employees' => ['required', 'array', 'min:1', 'max:50'],
            'employees.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'employees.*.peran' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tgl_mulai' => 'tanggal mulai',
            'tgl_selesai' => 'tanggal selesai',
            'employees' => 'karyawan',
            'employees.*.employee_id' => 'karyawan',
            'employees.*.peran' => 'peran',
        ];
    }
}
