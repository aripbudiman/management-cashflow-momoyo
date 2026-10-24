<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;

trait ResolvesStorageUrl
{
    protected function storageUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return config('filesystems.default', 's3') === 's3'
            ? Storage::disk('s3')->url($path)
            : asset('storage/'.$path);
    }
}
