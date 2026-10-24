<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectDocumentRequest extends FormRequest
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
            'kategori' => ['required', Rule::in(['gambar_kerja', 'rendering_3d', 'material_list', 'lainnya'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx,dwg', 'max:20480'],
        ];
    }
}
