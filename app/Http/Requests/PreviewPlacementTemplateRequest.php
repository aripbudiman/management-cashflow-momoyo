<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPlacementTemplateRequest extends FormRequest
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
            'margin_cm' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }
}
