<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideStockReportRequest extends FormRequest
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
            'revision_notes' => [$this->routeIs('stock-verifications.revise') ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['revision_notes.required' => 'Alasan revisi wajib diisi.'];
    }
}
