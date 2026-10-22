<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransactionRequest;
use App\Models\Branch;
use App\Models\StockTransaction;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockTransactionController extends Controller
{
    public function __construct(private readonly StockService $service) {}

    public function store(StoreStockTransactionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $branch = $user->isAdminPusat() ? Branch::findOrFail($request->validated('branch_id')) : $user->employee->currentBranch;

        $this->service->recordTransaction($user, $branch, $request->validated());

        return back()->with('success', $request->input('type') === 'in' ? 'Barang masuk tercatat, stok bertambah.' : 'Barang keluar tercatat, stok berkurang.');
    }

    public function cancel(Request $request, StockTransaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminPusat() || $user->employee?->current_branch_id === $transaction->branch_id, 403);

        $this->service->cancelTransaction($transaction, $user);

        return back()->with('success', 'Transaksi dibatalkan, stok dikembalikan.');
    }
}
