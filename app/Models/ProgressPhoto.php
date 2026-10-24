<?php

namespace App\Models;

use App\Concerns\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    use ResolvesStorageUrl;

    protected $fillable = ['progress_log_id', 'file_path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return $this->storageUrl($this->file_path);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(ProgressLog::class, 'progress_log_id');
    }
}
