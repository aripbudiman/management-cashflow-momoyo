<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends EmployeeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $userId = $employee?->user_id;

        return [
            ...$this->profileRules(),
            'nik' => ['required', 'string', 'max:30', Rule::unique('employees', 'nik')->ignore($employee?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6'],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
        ];
    }
}
