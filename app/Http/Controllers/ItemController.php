<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportItemsRequest;
use App\Http\Requests\SaveItemRequest;
use App\Models\Item;
use App\Models\Unit;
use App\Services\ItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('items/Index', [
            'items' => Item::query()
                ->with('itemUnits.unit')
                ->when($request->input('search'), fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(SaveItemRequest $request): RedirectResponse
    {
        $this->service->save(null, $request->validated());

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(SaveItemRequest $request, Item $item): RedirectResponse
    {
        $this->service->save($item, $request->validated());

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->movements()->exists()) {
            $item->update(['is_active' => false]);

            return back()->with('success', 'Barang sudah punya riwayat stok, jadi dinonaktifkan (tidak dihapus).');
        }

        $item->delete();

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    public function import(ImportItemsRequest $request): RedirectResponse
    {
        $result = $this->service->import($request->validated('rows'));

        return back()->with('success', "Impor selesai: {$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati (satuan harga berbeda dengan master).");
    }
}
