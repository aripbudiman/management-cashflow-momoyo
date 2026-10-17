<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReportItem extends Model
{
    protected $fillable = [
        'report_id', 'item_id', 'unit_price', 'opening_qty', 'in_qty', 'out_qty',
        'waste_qty', 'expected_qty', 'physical_qty', 'diff_qty', 'inputs', 'note',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'opening_qty' => 'decimal:2',
            'in_qty' => 'decimal:2',
            'out_qty' => 'decimal:2',
            'waste_qty' => 'decimal:2',
            'expected_qty' => 'decimal:2',
            'physical_qty' => 'decimal:2',
            'diff_qty' => 'decimal:2',
            'inputs' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'report_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
