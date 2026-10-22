<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPES = [
        'in' => 'Barang Masuk',
        'out' => 'Barang Keluar',
        'waste' => 'Rusak / Expired',
        'adjustment' => 'Penyesuaian Opname',
        'transfer_in' => 'Transfer Masuk',
        'transfer_out' => 'Transfer Keluar',
    ];

    protected $fillable = [
        'branch_id', 'item_id', 'type', 'qty', 'unit_price', 'report_id',
        'transfer_id', 'transaction_id', 'user_id', 'moved_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'moved_at' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
