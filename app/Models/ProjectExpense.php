<?php

namespace App\Models;

use App\Concerns\HasExpenseProofs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    use HasExpenseProofs;

    public const CATEGORIES = ['Material', 'Upah Tukang', 'Alat', 'Transport', 'Lainnya'];

    protected $fillable = ['project_id', 'expense_date', 'category', 'description', 'nominal', 'created_by'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date:Y-m-d',
            'nominal' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
