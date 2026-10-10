<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideLeaveRequest extends FormRequest
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
            'approval_notes' => [$this->routeIs('leaves.reject') ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['approval_notes.required' => 'Alasan penolakan wajib diisi.'];
    }
}
