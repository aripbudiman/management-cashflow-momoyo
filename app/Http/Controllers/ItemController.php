<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportItemsRequest;
use App\Http\Requests\SaveItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('items/Index', [
            'items' => Item::query()
                ->when($request->input('search'), fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(SaveItemRequest $request): RedirectResponse
    {
        Item::create($request->validated());

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(SaveItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->validated());

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
        foreach ($request->validated('rows') as $row) {
            Item::updateOrCreate(['name' => $row['name'], 'unit' => $row['unit']], ['price' => $row['price']]);
        }

        return back()->with('success', count($request->validated('rows')).' barang berhasil diimpor.');
    }
}
