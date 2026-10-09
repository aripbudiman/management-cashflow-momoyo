<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = [
        'proof_receipt_full_url',
    ];

    protected function casts(): array
    {
        return [
            'transfer_amount' => 'decimal:2',
            'transfer_time' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function getProofReceiptFullUrlAttribute(): ?string
    {
        if (! $this->proof_receipt_url) {
            return null;
        }

        if (str_starts_with($this->proof_receipt_url, 'http') || str_starts_with($this->proof_receipt_url, 'data:')) {
            return $this->proof_receipt_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->proof_receipt_url);
        }

        return asset('storage/'.$this->proof_receipt_url);
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
