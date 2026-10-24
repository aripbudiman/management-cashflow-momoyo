<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressLog extends Model
{
    protected $fillable = [
        'work_item_id',
        'user_id',
        'progress_dari',
        'progress_ke',
        'catatan',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_dari' => 'integer',
            'progress_ke' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }
}
