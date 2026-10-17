<?php

namespace App\Http\Requests;

class StoreEmployeeRequest extends EmployeeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'nik' => ['required', 'string', 'max:30', 'unique:employees,nik'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
        ];
    }
}
