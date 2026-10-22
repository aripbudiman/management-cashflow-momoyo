<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->isAdminPusat() || ((bool) $user?->isStaffCabang() && $user->employee?->currentBranch !== null);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [Rule::requiredIf(fn () => (bool) $this->user()?->isAdminPusat()), 'nullable', 'integer', 'exists:branches,id'],
            'moved_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'type' => ['required', 'in:in,out'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.kind' => ['required', 'in:in,out,waste'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $allowed = $this->input('type') === 'in' ? ['in'] : ['out', 'waste'];
            foreach ($this->input('items') as $line) {
                if (! in_array($line['kind'], $allowed, true)) {
                    $validator->errors()->add('items', 'Jenis barang tidak sesuai dengan jenis transaksi.');
                    break;
                }
            }
        }];
    }
}
