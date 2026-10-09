<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CashflowDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_id',
        'payment_method',
        'nominal',
        'settlement_batch_proof_url',
    ];

    protected $appends = [
        'settlement_batch_proof_full_url',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function getSettlementBatchProofFullUrlAttribute(): ?string
    {
        if (! $this->settlement_batch_proof_url) {
            return null;
        }

        if (str_starts_with($this->settlement_batch_proof_url, 'http') || str_starts_with($this->settlement_batch_proof_url, 'data:')) {
            return $this->settlement_batch_proof_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->settlement_batch_proof_url);
        }

        return asset('storage/'.$this->settlement_batch_proof_url);
    }

    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(DailyCashflow::class, 'cashflow_id');
    }
}
