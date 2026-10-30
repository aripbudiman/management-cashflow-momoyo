<?php

namespace App\Http\Requests;

use App\Models\CentralExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCentralExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'category' => ['required', Rule::in(CentralExpense::CATEGORIES)],
            'nominal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'expense_date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.min' => 'Nominal minimal Rp 1.',
        ];
    }
}
