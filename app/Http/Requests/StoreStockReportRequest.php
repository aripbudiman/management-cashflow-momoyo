<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaffCabang() && $this->user()->employee?->currentBranch !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:500'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.physical_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.physical_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
