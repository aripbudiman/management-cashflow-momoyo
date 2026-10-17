<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * Aturan umum profil karyawan (identitas, data pribadi, rekening gaji).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function profileRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/', 'required_with:bank_name'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'current_branch_id' => ['required', 'exists:branches,id'],
            'status' => ['required', 'in:active,inactive,probation'],
            'username' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik.unique' => 'NIK ini sudah terdaftar untuk karyawan lain.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'username.unique' => 'Username ini sudah digunakan.',
            'password.min' => 'Password minimal 6 karakter.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'phone_number.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, + dan -.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'bank_account_number.required_with' => 'Nomor rekening wajib diisi jika nama bank diisi.',
        ];
    }

    /**
     * Field profil yang disimpan ke tabel employees.
     *
     * @return array<string, mixed>
     */
    public function employeeData(): array
    {
        $data = $this->safe()->only([
            'birth_place', 'birth_date', 'address', 'phone_number',
            'bank_name', 'bank_account_number', 'bank_account_name',
            'current_branch_id', 'status',
        ]);

        // Nama pemilik rekening default ke nama karyawan jika bank diisi tapi atas nama kosong
        if (! empty($data['bank_name']) && empty($data['bank_account_name'])) {
            $data['bank_account_name'] = trim($this->input('full_name'));
        }

        return $data;
    }
}
