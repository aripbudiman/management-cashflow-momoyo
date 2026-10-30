<?php

namespace App\Concerns;

use App\Models\ExpenseProof;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasExpenseProofs
{
    public function proofs(): MorphMany
    {
        return $this->morphMany(ExpenseProof::class, 'proofable');
    }
}
