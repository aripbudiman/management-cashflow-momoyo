<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRabRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'file RAB'];
    }
}
