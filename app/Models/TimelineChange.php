<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineChange extends Model
{
    protected $fillable = ['work_item_id', 'user_id', 'field', 'nilai_lama', 'nilai_baru', 'alasan'];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
