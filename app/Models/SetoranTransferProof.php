<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SetoranTransferProof extends Model
{
    protected $fillable = ['transfer_id', 'photo_url'];

    protected $appends = ['photo_full_url'];

    public function getPhotoFullUrlAttribute(): string
    {
        if (str_starts_with($this->photo_url, 'http') || str_starts_with($this->photo_url, 'data:')) {
            return $this->photo_url;
        }

        $disk = config('filesystems.default', 's3');

        return $disk === 's3'
            ? Storage::disk('s3')->url($this->photo_url)
            : asset('storage/'.$this->photo_url);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(SetoranTransfer::class, 'transfer_id');
    }
}
