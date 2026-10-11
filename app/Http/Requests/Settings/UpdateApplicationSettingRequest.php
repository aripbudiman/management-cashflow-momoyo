<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationSettingRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:50'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
            'remove_icon' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'app_name.required' => 'Nama aplikasi wajib diisi.',
            'icon.mimes' => 'Icon harus berformat PNG, JPG, atau WEBP.',
            'icon.max' => 'Ukuran icon maksimal 2 MB.',
            'icon.dimensions' => 'Ukuran icon minimal 64×64 piksel.',
        ];
    }
}
