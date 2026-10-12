<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportItemsRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name' => ['required', 'string', 'max:150'],
            'rows.*.unit' => ['required', 'string', 'max:30'],
            'rows.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
