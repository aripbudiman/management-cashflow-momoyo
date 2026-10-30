<?php

namespace App\Repositories;

use App\Models\CentralExpense;
use App\Models\DailyCashflow;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CentralExpenseRepository
{
    /**
     * @param  array{month?: mixed, branch_id?: mixed, category?: mixed}  $filters  branch_id 'pusat' = hanya yang dibayar uang bos
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['branch:id,branch_name', 'creator:id,name', 'proofs'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{month?: mixed, branch_id?: mixed, category?: mixed}  $filters
     */
    public function totalFor(array $filters): float
    {
        return (float) $this->filtered($filters)->sum('nominal');
    }

    /**
     * Total pengeluaran pusat per cabang sepanjang waktu (key 0 = uang bos / tanpa cabang).
     *
     * @return Collection<int, float>
     */
    public function totalsByBranch(): Collection
    {
        return CentralExpense::query()
            ->selectRaw('coalesce(branch_id, 0) as bid, sum(nominal) as total')
            ->groupBy('bid')
            ->pluck('total', 'bid')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * Setoran cabang yang sudah terverifikasi Admin Pusat sepanjang waktu.
     *
     * @return Collection<int, float>
     */
    public function verifiedSetoranByBranch(): Collection
    {
        return DailyCashflow::query()
            ->where('status', 'verified_by_finance')
            ->selectRaw('branch_id, sum(total_net_cash) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * Pengeluaran pusat dalam rentang tanggal per cabang (key 0 = uang bos).
     *
     * @return Collection<int, float>
     */
    public function totalsByBranchBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CentralExpense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('coalesce(branch_id, 0) as bid, sum(nominal) as total')
            ->groupBy('bid')
            ->pluck('total', 'bid')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * @param  array{month?: mixed, branch_id?: mixed, category?: mixed}  $filters
     * @return Builder<CentralExpense>
     */
    private function filtered(array $filters)
    {
        $branch = $filters['branch_id'] ?? null;

        return CentralExpense::query()
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->whereBetween('expense_date', [$v.'-01', date('Y-m-t', strtotime($v.'-01'))]))
            ->when($branch === 'pusat', fn ($q) => $q->whereNull('branch_id'))
            ->when($branch && $branch !== 'pusat', fn ($q) => $q->where('branch_id', (int) $branch))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v));
    }
}
