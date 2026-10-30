<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CashflowExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashflow_id',
        'stock_transaction_id',
        'category',
        'nominal',
        'description',
        'receipt_photo_url',
    ];

    protected $appends = [
        'receipt_photo_full_url',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function getReceiptPhotoFullUrlAttribute(): ?string
    {
        if (! $this->receipt_photo_url) {
            return null;
        }

        if (str_starts_with($this->receipt_photo_url, 'http') || str_starts_with($this->receipt_photo_url, 'data:')) {
            return $this->receipt_photo_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->receipt_photo_url);
        }

        return asset('storage/'.$this->receipt_photo_url);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CashflowExpensePhoto::class, 'expense_id');
    }

    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(DailyCashflow::class, 'cashflow_id');
    }
}
