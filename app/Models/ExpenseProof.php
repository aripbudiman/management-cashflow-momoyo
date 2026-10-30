<?php

namespace App\Models;

use App\Concerns\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExpenseProof extends Model
{
    use ResolvesStorageUrl;

    protected $fillable = ['photo_url'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return $this->storageUrl($this->photo_url);
    }

    public function proofable(): MorphTo
    {
        return $this->morphTo();
    }
}
