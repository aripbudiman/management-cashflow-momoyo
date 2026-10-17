<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaffCabang() && $this->user()->employee?->currentBranch !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:'.$this->user()?->employee?->current_branch_id],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to_branch_id.different' => 'Cabang tujuan tidak boleh sama dengan cabang asal.'];
    }
}
