<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssuePlacementRequest extends FormRequest
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
            'penandatangan_nama' => ['required', 'string', 'max:150'],
            'penandatangan_jabatan' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['penandatangan_nama' => 'nama penandatangan', 'penandatangan_jabatan' => 'jabatan penandatangan'];
    }
}
