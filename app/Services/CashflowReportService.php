<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DailyCashflow;
use App\Repositories\CashflowRepository;
use Carbon\Carbon;

class CashflowReportService
{
    public function __construct(private readonly CashflowRepository $cashflows) {}

    /**
     * Laporan harian dalam satu bulan (format seperti sheet bulanan). $branchId kosong/0 = semua cabang.
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function daily(?int $branchId, Carbon $month): array
    {
        $items = $this->cashflows->betweenWithDetails($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $branchId ?: null);

        $rows = $items->map(fn (DailyCashflow $c) => [
            'date' => $c->report_date->toDateString(),
            'branch_name' => $c->branch?->branch_name,
            'closing_by' => $c->closingBy?->name,
            'status' => $c->status,
            'wallets' => $this->walletMap($c),
            'total_omset' => (float) $c->total_omset,
            'total_expense' => (float) $c->total_expense,
            'total_setor' => (float) $c->total_net_cash,
        ])->values()->all();

        return ['rows' => $rows, 'totals' => $this->sum($rows)];
    }

    /**
     * Rekap bulanan semua cabang.
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function monthly(Carbon $month): array
    {
        $items = $this->cashflows->betweenWithDetails($month->copy()->startOfMonth(), $month->copy()->endOfMonth())->groupBy('branch_id');

        $rows = Branch::orderBy('branch_name')->get(['id', 'branch_name'])->map(function (Branch $b) use ($items) {
            $list = $items->get($b->id, collect());
            $rows = $list->map(fn (DailyCashflow $c) => [
                'wallets' => $this->walletMap($c),
                'total_omset' => (float) $c->total_omset,
                'total_expense' => (float) $c->total_expense,
                'total_setor' => (float) $c->total_net_cash,
            ])->all();

            return [
                'branch_id' => $b->id,
                'branch_name' => $b->branch_name,
                'days' => $list->count(),
                'verified_days' => $list->where('status', 'verified_by_finance')->count(),
                ...$this->sum($rows),
            ];
        })->values()->all();

        return ['rows' => $rows, 'totals' => $this->sum($rows)];
    }

    /**
     * @return array<string, float>
     */
    private function walletMap(DailyCashflow $c): array
    {
        $map = array_fill_keys(array_keys(CashflowService::WALLETS), 0.0);
        foreach ($c->details as $d) {
            $map[$d->payment_method] = (float) $d->nominal;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sum(array $rows): array
    {
        $wallets = array_fill_keys(array_keys(CashflowService::WALLETS), 0.0);
        foreach ($rows as $r) {
            foreach ($r['wallets'] as $k => $v) {
                $wallets[$k] += $v;
            }
        }

        return [
            'wallets' => $wallets,
            'total_omset' => array_sum(array_column($rows, 'total_omset')),
            'total_expense' => array_sum(array_column($rows, 'total_expense')),
            'total_setor' => array_sum(array_column($rows, 'total_setor')),
        ];
    }
}
