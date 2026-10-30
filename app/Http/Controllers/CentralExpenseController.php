<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCentralExpenseRequest;
use App\Http\Requests\UpdateOpeningBalanceRequest;
use App\Models\Branch;
use App\Models\CentralExpense;
use App\Repositories\CentralExpenseRepository;
use App\Services\CentralExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CentralExpenseController extends Controller
{
    public function __construct(
        private readonly CentralExpenseService $service,
        private readonly CentralExpenseRepository $expenses,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'month' => preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m'),
            'branch_id' => $request->input('branch_id'),
            'category' => in_array($request->input('category'), CentralExpense::CATEGORIES, true) ? $request->input('category') : null,
        ];

        return Inertia::render('central-expenses/Index', [
            'filters' => $filters,
            'branches' => Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']),
            'categories' => CentralExpense::CATEGORIES,
            'balances' => $this->service->balances(),
            'expenses' => $this->expenses->paginate($filters),
            'filteredTotal' => $this->expenses->totalFor($filters),
        ]);
    }

    public function store(StoreCentralExpenseRequest $request): RedirectResponse
    {
        $this->service->create($request->user(), $request->validated());

        return back()->with('success', 'Pengeluaran pusat berhasil dicatat.');
    }

    public function update(StoreCentralExpenseRequest $request, CentralExpense $centralExpense): RedirectResponse
    {
        $this->service->update($centralExpense, $request->validated());

        return back()->with('success', 'Pengeluaran pusat diperbarui.');
    }

    public function destroy(Request $request, CentralExpense $centralExpense): RedirectResponse
    {
        abort_unless($request->user()->isAdminPusat(), 403);
        $this->service->delete($centralExpense);

        return back()->with('success', 'Pengeluaran pusat dihapus.');
    }

    public function updateOpeningBalance(UpdateOpeningBalanceRequest $request, Branch $branch): RedirectResponse
    {
        $this->service->setOpeningBalance($branch, (float) $request->validated('opening_balance'));

        return back()->with('success', "Saldo awal {$branch->branch_name} diperbarui.");
    }
}
