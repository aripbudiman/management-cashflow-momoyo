<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
