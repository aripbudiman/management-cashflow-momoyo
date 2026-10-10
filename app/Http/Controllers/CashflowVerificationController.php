<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideCashflowRequest;
use App\Models\Branch;
use App\Models\DailyCashflow;
use App\Repositories\CashflowRepository;
use App\Services\CashflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashflowVerificationController extends Controller
{
    public function __construct(
        private readonly CashflowService $service,
        private readonly CashflowRepository $cashflows,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('cashflow-verifications/Index', [
            'cashflows' => $this->cashflows->paginateForAdmin($request->only(['branch_id', 'status', 'month'])),
            'branches' => Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']),
            'wallets' => CashflowService::WALLETS,
            'filters' => $request->only(['branch_id', 'status', 'month']),
        ]);
    }

    public function verify(DecideCashflowRequest $request, DailyCashflow $cashflow): RedirectResponse
    {
        $this->service->verify($cashflow, $request->user());

        return back()->with('success', 'Setoran diverifikasi dan disetujui.');
    }

    public function revise(DecideCashflowRequest $request, DailyCashflow $cashflow): RedirectResponse
    {
        $this->service->requestRevision($cashflow, $request->user(), $request->validated('revision_notes'));

        return back()->with('success', 'Permintaan revisi dikirim ke cabang.');
    }
}
