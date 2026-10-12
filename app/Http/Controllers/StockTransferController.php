<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Repositories\StockRepository;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockService $service,
        private readonly StockRepository $stocks,
    ) {}

    public function index(Request $request): Response
    {
        $branch = $request->user()->employee?->currentBranch;
        $isAdmin = $request->user()->isAdminPusat();

        return Inertia::render('stock-transfers/Index', [
            'branchId' => $branch?->id,
            'canSend' => ! $isAdmin && $branch !== null,
            'transfers' => $this->stocks->transfers($isAdmin ? null : $branch?->id),
            'branches' => Branch::where('is_active', true)->when($branch, fn ($q) => $q->where('id', '!=', $branch->id))->orderBy('branch_name')->get(['id', 'branch_name']),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $this->service->sendTransfer($request->user(), $request->user()->employee->currentBranch, $request->validated());

        return back()->with('success', 'Transfer dikirim. Stok bertambah di cabang tujuan setelah mereka konfirmasi terima.');
    }

    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        abort_unless($request->user()->employee?->current_branch_id === $transfer->to_branch_id, 403);

        $this->service->receiveTransfer($transfer, $request->user());

        return back()->with('success', 'Barang diterima, stok cabang bertambah.');
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminPusat() || $user->employee?->current_branch_id === $transfer->from_branch_id, 403);

        $this->service->cancelTransfer($transfer, $user);

        return back()->with('success', 'Transfer dibatalkan, stok dikembalikan ke cabang asal.');
    }
}
