<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyCashflowRequest;
use App\Repositories\CashflowRepository;
use App\Services\CashflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyCashflowController extends Controller
{
    public function __construct(
        private readonly CashflowService $service,
        private readonly CashflowRepository $cashflows,
    ) {}

    public function index(Request $request): Response
    {
        $employee = $request->user()->employee?->load('currentBranch');
        $branch = $employee?->currentBranch;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date')) ? $request->input('date') : today()->toDateString();

        return Inertia::render('cashflows/Index', [
            'branch' => $branch?->only(['id', 'branch_name', 'branch_code']),
            'date' => $date,
            'today' => today()->toDateString(),
            'wallets' => CashflowService::WALLETS,
            'cashflow' => $branch ? $this->cashflows->findByBranchDate($branch->id, $date) : null,
            'history' => $branch ? $this->cashflows->historyForBranch($branch->id) : [],
        ]);
    }

    public function store(StoreDailyCashflowRequest $request): RedirectResponse
    {
        $branch = $request->user()->employee->currentBranch;

        $this->service->save($request->user(), $branch, $request->validated());

        return redirect()->route('cashflows.index', ['date' => $request->input('report_date')])
            ->with('success', 'Closing harian berhasil dikirim ke Admin Pusat untuk diverifikasi.');
    }
}
