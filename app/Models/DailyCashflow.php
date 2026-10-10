<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyCashflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'report_date',
        'closing_by',
        'total_omset',
        'total_expense',
        'total_net_cash',
        'status',
        'verified_by',
        'verified_at',
        'notes',
        'revision_notes',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'total_omset' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'total_net_cash' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function closingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CashflowDetail::class, 'cashflow_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CashflowExpense::class, 'cashflow_id');
    }

    public function setoranTransfers(): HasMany
    {
        return $this->hasMany(SetoranTransfer::class, 'cashflow_id');
    }

    /**
     * Hitung total omset (semua wallet), total pengeluaran, dan total setor.
     * Total Setor (total_net_cash) = Tunai di Kasir - Total Pengeluaran harian.
     */
    public function recalculateTotals(): void
    {
        $totalOmset = $this->details()->sum('nominal');
        $cashOmset = $this->details()->where('payment_method', 'tunai')->sum('nominal');
        $totalExpense = $this->expenses()->sum('nominal');
        $totalNetCash = max(0, $cashOmset - $totalExpense);

        $this->update([
            'total_omset' => $totalOmset,
            'total_expense' => $totalExpense,
            'total_net_cash' => $totalNetCash,
        ]);
    }
}
