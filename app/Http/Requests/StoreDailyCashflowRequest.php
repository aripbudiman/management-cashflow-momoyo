<?php

namespace App\Http\Requests;

use App\Services\CashflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDailyCashflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->employee?->current_branch_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'wallets' => ['required', 'array'],
            'expenses' => ['nullable', 'array', 'max:50'],
            'expenses.*.description' => ['required', 'string', 'max:255'],
            'expenses.*.nominal' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'expenses.*.photos' => ['nullable', 'array', 'max:5'],
            'expenses.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'expenses.*.keep_photo_ids' => ['nullable', 'array', 'max:5'],
            'expenses.*.keep_photo_ids.*' => ['integer'],
            'transfer_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'proofs' => ['nullable', 'array', 'max:5'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'keep_proof_ids' => ['nullable', 'array', 'max:5'],
            'keep_proof_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        foreach (array_keys(CashflowService::WALLETS) as $key) {
            $rules["wallets.{$key}"] = ['nullable', 'numeric', 'min:0', 'max:999999999999'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transfer_amount.required' => 'Nominal transfer wajib diisi.',
            'proofs.*.mimes' => 'Bukti transfer harus berupa foto (jpg, png, webp).',
            'proofs.*.max' => 'Ukuran foto bukti maksimal 5 MB.',
            'expenses.*.photos.*.mimes' => 'Foto bukti pengeluaran harus berupa foto (jpg, png, webp).',
            'expenses.*.photos.*.max' => 'Ukuran foto bukti pengeluaran maksimal 5 MB.',
            'report_date.before_or_equal' => 'Tanggal closing tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $proofCount = count($this->input('keep_proof_ids', [])) + count($this->file('proofs') ?? []);
            if ($proofCount < 1) {
                $validator->errors()->add('proofs', 'Foto bukti transfer ke pusat wajib diupload (minimal 1).');
            } elseif ($proofCount > 5) {
                $validator->errors()->add('proofs', 'Maksimal 5 foto bukti transfer.');
            }

            $tunai = (float) $this->input('wallets.tunai', 0);
            $expense = collect($this->input('expenses', []))->sum(fn ($e) => (float) $e['nominal']);

            foreach ($this->input('expenses', []) as $i => $e) {
                if (count($e['keep_photo_ids'] ?? []) + count($this->file("expenses.{$i}.photos") ?? []) > 5) {
                    $validator->errors()->add("expenses.{$i}.photos", 'Maksimal 5 foto per pengeluaran.');
                }
            }

            if ($expense > $tunai) {
                $validator->errors()->add('expenses', 'Total pengeluaran melebihi tunai di kasir.');
            }
        }];
    }
}
