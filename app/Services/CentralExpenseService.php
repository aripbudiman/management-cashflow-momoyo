<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CentralExpense;
use App\Models\User;
use App\Repositories\CentralExpenseRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class CentralExpenseService
{
    public function __construct(
        private readonly CentralExpenseRepository $expenses,
        private readonly ExpenseProofService $proofs,
    ) {}

    /**
     * @param  array{expense_date: string, branch_id?: ?int, category: string, nominal: mixed, description?: ?string, proofs?: array<int, UploadedFile>, remove_proof_ids?: array<int, int>}  $data
     */
    public function create(User $user, array $data): CentralExpense
    {
        $expense = CentralExpense::create([...$this->payload($data), 'created_by' => $user->id]);
        $this->proofs->sync($expense, "central-expenses/{$expense->id}", $data['proofs'] ?? []);

        return $expense;
    }

    /**
     * @param  array{expense_date: string, branch_id?: ?int, category: string, nominal: mixed, description?: ?string, proofs?: array<int, UploadedFile>, remove_proof_ids?: array<int, int>}  $data
     */
    public function update(CentralExpense $expense, array $data): CentralExpense
    {
        $expense->update($this->payload($data));
        $this->proofs->sync($expense, "central-expenses/{$expense->id}", $data['proofs'] ?? [], $data['remove_proof_ids'] ?? []);

        return $expense;
    }

    public function delete(CentralExpense $expense): void
    {
        $this->proofs->purge($expense);
        $expense->delete();
    }

    public function setOpeningBalance(Branch $branch, float $amount): void
    {
        $branch->update(['opening_balance' => $amount]);
    }

    /**
     * Saldo kas tiap cabang = saldo awal + setoran terverifikasi − pengeluaran pusat yang dibebankan ke cabang.
     * Pengeluaran tanpa cabang (uang bos) tidak memengaruhi saldo cabang manapun.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, total_balance: float, owner_expense: float}
     */
    public function balances(): array
    {
        $in = $this->expenses->verifiedSetoranByBranch();
        $out = $this->expenses->totalsByBranch();

        $rows = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name', 'branch_code', 'opening_balance'])
            ->map(function (Branch $b) use ($in, $out): array {
                $opening = (float) $b->opening_balance;
                $setoran = (float) $in->get($b->id, 0);
                $expense = (float) $out->get($b->id, 0);

                return [
                    'branch_id' => $b->id,
                    'branch_name' => $b->branch_name,
                    'branch_code' => $b->branch_code,
                    'opening_balance' => $opening,
                    'setoran_in' => $setoran,
                    'central_expense' => $expense,
                    'balance' => $opening + $setoran - $expense,
                ];
            })->values();

        return [
            'rows' => $rows,
            'total_balance' => (float) $rows->sum('balance'),
            'owner_expense' => (float) $out->get(0, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'expense_date' => $data['expense_date'],
            'branch_id' => $data['branch_id'] ?? null,
            'category' => $data['category'],
            'nominal' => (float) $data['nominal'],
            'description' => $data['description'] ?? null,
        ];
    }
}
