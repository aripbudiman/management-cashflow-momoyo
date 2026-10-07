<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashflowDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_id',
        'payment_method',
        'nominal',
        'settlement_batch_proof_url',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(DailyCashflow::class, 'cashflow_id');
    }
}
