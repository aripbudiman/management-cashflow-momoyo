<?php

namespace App\Models;

use App\Concerns\HasExpenseProofs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralExpense extends Model
{
    use HasExpenseProofs;

    public const CATEGORIES = ['Gaji', 'Operasional', 'Pembelian Besar', 'Lainnya'];

    protected $fillable = ['expense_date', 'branch_id', 'category', 'nominal', 'description', 'created_by'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'nominal' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
