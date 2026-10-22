<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransaction extends Model
{
    protected $fillable = [
        'branch_id', 'type', 'moved_at', 'user_id', 'note', 'status', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransactionItem::class, 'transaction_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'transaction_id');
    }
}
