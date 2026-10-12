<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReport extends Model
{
    protected $fillable = [
        'branch_id', 'report_date', 'submitted_by', 'status', 'verified_by',
        'verified_at', 'notes', 'revision_notes',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReportItem::class, 'report_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'report_id');
    }
}
