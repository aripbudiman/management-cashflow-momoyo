<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWorkSectionRequest extends FormRequest
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
            'kode_bidang' => ['required', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:150'],
        ];
    }
}
