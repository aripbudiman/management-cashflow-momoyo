<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetoranTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_id',
        'bank_account_id',
        'transfer_amount',
        'transfer_time',
        'proof_receipt_url',
        'status',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_amount' => 'decimal:2',
            'transfer_time' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(DailyCashflow::class, 'cashflow_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'bank_account_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
