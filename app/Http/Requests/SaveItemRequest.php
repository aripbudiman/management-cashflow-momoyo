<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'is_active' => ['required', 'boolean'],
            'units' => ['required', 'array', 'min:1', 'max:6'],
            'units.*.unit_id' => ['required', 'integer', 'distinct', 'exists:units,id'],
            'units.*.factor' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'price_unit_id' => ['required', 'integer'],
            'default_in_unit_id' => ['nullable', 'integer'],
            'default_out_unit_id' => ['nullable', 'integer'],
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

            $units = collect($this->input('units'));
            $factors = $units->pluck('factor')->map(fn ($f) => round((float) $f, 2))->sort()->values();

            if ($factors->first() !== 1.0) {
                $validator->errors()->add('units', 'Satuan terkecil harus berisi 1 (faktor 1).');
            } elseif ($factors->unique()->count() !== $factors->count()) {
                $validator->errors()->add('units', 'Setiap satuan harus punya isi yang berbeda.');
            }

            $ids = $units->pluck('unit_id')->map(fn ($id) => (int) $id);
            foreach (['price_unit_id', 'default_in_unit_id', 'default_out_unit_id'] as $key) {
                if ($this->filled($key) && ! $ids->contains((int) $this->input($key))) {
                    $validator->errors()->add($key, 'Satuan harus salah satu dari satuan barang ini.');
                }
            }
        }];
    }
}
