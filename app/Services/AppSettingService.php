<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppSettingService
{
    /** @var array<string, ?string>|null */
    private ?array $values = null;

    public function name(): string
    {
        return $this->all()['app_name'] ?: (string) config('app.name', 'Laravel');
    }

    public function iconUrl(): ?string
    {
        $path = $this->all()['app_icon'] ?? null;
        if (! $path) {
            return null;
        }

        $disk = config('filesystems.default', 's3');

        return $disk === 's3' ? Storage::disk('s3')->url($path) : asset('storage/'.$path);
    }

    public function update(string $name, ?UploadedFile $icon, bool $removeIcon): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $oldIcon = $this->all()['app_icon'] ?? null;

        $newIcon = $icon ? $disk->putFile('app', $icon, 'public') : null;

        $this->set('app_name', trim($name));

        if ($newIcon) {
            $this->set('app_icon', $newIcon);
        } elseif ($removeIcon) {
            $this->set('app_icon', null);
        }

        if ($oldIcon && ($newIcon || $removeIcon)) {
            $disk->delete($oldIcon);
        }

        $this->values = null;
    }

    /**
     * @return array{app_name: ?string, app_icon: ?string}
     */
    private function all(): array
    {
        if ($this->values === null) {
            // Aman saat migrasi belum dijalankan
            $stored = Schema::hasTable('app_settings') ? AppSetting::pluck('value', 'key')->all() : [];
            $this->values = ['app_name' => $stored['app_name'] ?? null, 'app_icon' => $stored['app_icon'] ?? null];
        }

        return $this->values;
    }

    private function set(string $key, ?string $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
