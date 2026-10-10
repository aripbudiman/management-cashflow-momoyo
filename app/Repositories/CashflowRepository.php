<?php

namespace App\Repositories;

use App\Models\CashflowExpense;
use App\Models\DailyCashflow;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CashflowRepository
{
    private const FULL_RELATIONS = ['branch:id,branch_name,branch_code', 'closingBy:id,name', 'verifiedBy:id,name', 'details', 'expenses.photos', 'setoranTransfers.proofs'];

    public function findByBranchDate(int $branchId, string $date): ?DailyCashflow
    {
        return DailyCashflow::with(self::FULL_RELATIONS)
            ->where('branch_id', $branchId)
            ->where('report_date', $date)
            ->first();
    }

    /**
     * @return Collection<int, DailyCashflow>
     */
    public function historyForBranch(int $branchId, int $limit = 14): Collection
    {
        return DailyCashflow::query()
            ->where('branch_id', $branchId)
            ->latest('report_date')
            ->limit($limit)
            ->get(['id', 'report_date', 'total_omset', 'total_expense', 'total_net_cash', 'status', 'revision_notes']);
    }

    /**
     * @param  array{branch_id?: mixed, status?: mixed, month?: mixed}  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return DailyCashflow::with(self::FULL_RELATIONS)
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->whereBetween('report_date', [$v.'-01', date('Y-m-t', strtotime($v.'-01'))]))
            ->orderByRaw("case when status = 'submitted' then 0 else 1 end")
            ->latest('report_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, DailyCashflow>
     */
    public function betweenWithDetails(CarbonInterface $start, CarbonInterface $end, ?int $branchId = null): Collection
    {
        return DailyCashflow::query()
            ->with(['details', 'closingBy:id,name', 'branch:id,branch_name'])
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderBy('report_date')
            ->orderBy('branch_id')
            ->get();
    }

    /**
     * @return Collection<int, CashflowExpense>
     */
    public function expensesBetween(CarbonInterface $start, CarbonInterface $end, ?int $branchId = null): Collection
    {
        return CashflowExpense::query()
            ->with(['photos', 'cashflow:id,branch_id,report_date', 'cashflow.branch:id,branch_name'])
            ->whereHas('cashflow', fn ($q) => $q
                ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
                ->when($branchId, fn ($w, $v) => $w->where('branch_id', $v)))
            ->orderBy('id')
            ->get();
    }
}
