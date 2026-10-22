<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'item_id', 'type', 'input_qty', 'unit_id', 'base_qty', 'unit_price', 'note',
    ];

    protected function casts(): array
    {
        return [
            'input_qty' => 'decimal:2',
            'base_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'transaction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
