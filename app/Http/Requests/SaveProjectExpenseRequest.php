<?php

namespace App\Http\Requests;

use App\Models\ProjectExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(ProjectExpense::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'proofs' => ['nullable', 'array', 'max:5'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_proof_ids' => ['nullable', 'array'],
            'remove_proof_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proofs.*.mimes' => 'Bukti harus berupa foto (jpg, png, webp).',
            'proofs.*.max' => 'Ukuran foto bukti maksimal 5 MB.',
            'description.required' => 'Keterangan wajib diisi.',
            'nominal.required' => 'Nominal wajib diisi.',
            'expense_date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
        ];
    }
}
