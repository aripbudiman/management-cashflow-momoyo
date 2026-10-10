<?php

namespace App\Services;

use App\Models\CashflowExpense;
use App\Repositories\CashflowRepository;
use Carbon\Carbon;

class ExpenseReportService
{
    public function __construct(private readonly CashflowRepository $cashflows) {}

    /**
     * Laporan pengeluaran kas per tanggal: tiap hari berisi baris pengeluaran dan total harian.
     *
     * @return array{days: array<int, array<string, mixed>>, grand_total: float, count: int}
     */
    public function report(Carbon $month, ?int $branchId): array
    {
        $expenses = $this->cashflows->expensesBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $branchId ?: null);

        $days = $expenses
            ->groupBy(fn (CashflowExpense $e) => $e->cashflow->report_date->toDateString())
            ->sortKeys()
            ->map(function ($items, string $date) {
                $rows = $items->sortBy(fn (CashflowExpense $e) => $e->cashflow->branch_id)->map(fn (CashflowExpense $e) => [
                    'id' => $e->id,
                    'description' => $e->description,
                    'nominal' => (float) $e->nominal,
                    'branch_name' => $e->cashflow->branch?->branch_name,
                    'photos' => $e->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->photo_full_url])->values(),
                ])->values();

                return [
                    'date' => $date,
                    'weekday' => Carbon::parse($date)->translatedFormat('l'),
                    'rows' => $rows,
                    'total' => (float) $rows->sum('nominal'),
                ];
            })->values()->all();

        return [
            'days' => $days,
            'grand_total' => (float) array_sum(array_column($days, 'total')),
            'count' => $expenses->count(),
        ];
    }
}
