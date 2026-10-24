<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePlacementTemplateRequest extends FormRequest
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
            'html' => ['required', 'string', 'max:200000'],
            'number_format' => ['required', 'string', 'max:100', 'regex:/\{urut\}/'],
            'margin_cm' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['number_format.regex' => 'Format nomor harus memuat {urut} supaya nomor surat tidak kembar.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['html' => 'isi template', 'number_format' => 'format nomor', 'margin_cm' => 'margin'];
    }
}
