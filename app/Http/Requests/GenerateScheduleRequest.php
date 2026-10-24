<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScheduleRequest extends FormRequest
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
        return ['alasan' => ['nullable', 'string', 'max:255']];
    }
}
