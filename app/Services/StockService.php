<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\User;
use App\Repositories\StockRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(private readonly StockRepository $stocks) {}

    /**
     * Catat transaksi barang masuk/keluar. Berlaku langsung (tanpa verifikasi) dan mengubah stok saat itu juga.
     *
     * @param  array{moved_at: string, type: string, note?: ?string, items: array<int, array{item_id: int, kind: string, qty: mixed, unit_id?: mixed, note?: ?string}>}  $data
     */
    public function recordTransaction(User $user, Branch $branch, array $data): StockTransaction
    {
        return DB::transaction(function () use ($user, $branch, $data) {
            $this->assertDateOpen($branch->id, $data['moved_at']);

            $transaction = StockTransaction::create([
                'branch_id' => $branch->id,
                'type' => $data['type'],
                'moved_at' => $data['moved_at'],
                'user_id' => $user->id,
                'note' => $data['note'] ?? null,
                'status' => 'active',
            ]);

            $items = Item::with('itemUnits.unit')->whereIn('id', collect($data['items'])->pluck('item_id'))->get()->keyBy('id');

            foreach ($data['items'] as $line) {
                $item = $items->get((int) $line['item_id']);
                $inputs = [];
                $base = $this->toBase($item, ['x_qty' => $line['qty'], 'x_unit_id' => $line['unit_id'] ?? null], 'x', $inputs);

                if (! $base || $base <= 0) {
                    continue;
                }

                $kind = $line['kind'];
                $stock = $this->lockStock($branch->id, $item->id);

                if ($kind !== 'in' && (float) $stock->qty < $base) {
                    throw ValidationException::withMessages(['items' => "Stok {$item->name} tidak cukup (tersisa {$stock->qty} {$item->unit})."]);
                }

                $transaction->items()->create([
                    'item_id' => $item->id,
                    'type' => $kind,
                    'input_qty' => $inputs['x']['qty'],
                    'unit_id' => $inputs['x']['unit_id'],
                    'base_qty' => $base,
                    'unit_price' => $item->basePrice(),
                    'note' => $line['note'] ?? null,
                ]);

                $this->record($stock, $kind, $kind === 'in' ? $base : -$base, $user, $data['moved_at'], $item->basePrice(), null, null, $line['note'] ?? null, $transaction->id);
            }

            if (! $transaction->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Isi minimal satu barang dengan jumlah lebih dari 0.']);
            }

            return $transaction;
        });
    }

    /**
     * Batalkan transaksi: mutasi dihapus dan stok dikembalikan. Ditolak jika opname tanggal itu sudah dikirim
     * atau pembatalan membuat stok minus (barang masuk sudah terpakai).
     */
    public function cancelTransaction(StockTransaction $transaction, User $user): void
    {
        DB::transaction(function () use ($transaction, $user) {
            $transaction = StockTransaction::lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Transaksi ini sudah dibatalkan.']);
            }

            $this->assertDateOpen($transaction->branch_id, $transaction->moved_at->toDateString());

            foreach ($transaction->movements()->get()->groupBy('item_id') as $itemId => $rows) {
                $stock = $this->lockStock($transaction->branch_id, (int) $itemId);
                $delta = round((float) $rows->sum(fn (StockMovement $m) => (float) $m->qty), 2);

                if (round((float) $stock->qty - $delta, 2) < 0) {
                    $name = Item::whereKey($itemId)->value('name');
                    throw ValidationException::withMessages(['status' => "Tidak bisa dibatalkan: stok {$name} akan minus karena sebagian sudah terpakai."]);
                }

                $stock->decrement('qty', $delta);
            }

            $transaction->movements()->delete();
            $transaction->update(['status' => 'cancelled', 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
        });
    }

    /**
     * Tanggal masih terbuka untuk transaksi/opname: belum ada opname terkirim pada tanggal itu atau setelahnya
     * (opname yang diminta revisi dibuka kembali), dan tidak di masa depan.
     */
    public function dateIsOpen(int $branchId, string $date): bool
    {
        if ($date > today()->toDateString()) {
            return false;
        }

        $latest = StockReport::where('branch_id', $branchId)->orderByDesc('report_date')->first(['report_date', 'status']);

        if (! $latest) {
            return true;
        }

        $latestDate = $latest->report_date->toDateString();

        return $date > $latestDate || ($date === $latestDate && $latest->status === 'revision_needed');
    }

    /**
     * Kirim rekap opname harian ke pusat. Stok awal, masuk, keluar, rusak, dan transfer dihitung dari transaksi
     * tanggal itu; staff hanya mengisi hitungan fisik (opsional). Selisih fisik dibuat sebagai penyesuaian stok.
     *
     * @param  array{report_date: string, notes?: ?string, items?: array<int, array{item_id: int, physical_qty?: mixed, physical_unit_id?: mixed, note?: ?string}>}  $data
     */
    public function submitOpname(User $user, Branch $branch, array $data): StockReport
    {
        return DB::transaction(function () use ($user, $branch, $data) {
            $existing = StockReport::where('branch_id', $branch->id)
                ->where('report_date', $data['report_date'])
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'verified') {
                throw ValidationException::withMessages(['report_date' => 'Opname tanggal ini sudah diverifikasi Admin Pusat dan tidak bisa diubah.']);
            }

            if ($existing === null && ! $this->dateIsOpen($branch->id, $data['report_date'])) {
                throw ValidationException::withMessages(['report_date' => 'Opname hanya bisa dikirim untuk tanggal setelah opname terakhir dan tidak di masa depan.']);
            }

            if ($existing) {
                $this->reverseReportMovements($existing);
                $existing->items()->delete();
            }

            $report = $existing ?? new StockReport(['branch_id' => $branch->id, 'report_date' => $data['report_date']]);
            $report->fill([
                'submitted_by' => $user->id,
                'status' => 'submitted',
                'notes' => $data['notes'] ?? null,
                'revision_notes' => null,
                'verified_by' => null,
                'verified_at' => null,
            ])->save();

            $moved = $this->stocks->dayMovements($branch->id, $data['report_date']);
            $physicalLines = collect($data['items'] ?? [])->keyBy('item_id');
            $itemIds = $moved->keys()->merge($physicalLines->filter(fn ($l) => isset($l['physical_qty']) && $l['physical_qty'] !== '')->keys())->unique();
            $items = Item::with('itemUnits.unit')->whereIn('id', $itemIds)->get()->keyBy('id');

            foreach ($itemIds as $itemId) {
                $item = $items->get((int) $itemId);
                $m = $moved->get($itemId);
                $in = round((float) ($m->in_qty ?? 0), 2);
                $out = round((float) ($m->out_qty ?? 0), 2);
                $waste = round((float) ($m->waste_qty ?? 0), 2);
                $transfer = round((float) ($m->transfer_qty ?? 0), 2);

                $stock = $this->lockStock($branch->id, $item->id);
                $expected = round((float) $stock->qty, 2);
                $opening = round($expected - $in + $out + $waste - $transfer, 2);

                $line = $physicalLines->get($itemId, []);
                $inputs = [];
                $physical = $this->toBase($item, $line, 'physical', $inputs);
                $diff = $physical !== null ? round($physical - $expected, 2) : 0.0;

                $report->items()->create([
                    'item_id' => $item->id,
                    'unit_price' => $item->basePrice(),
                    'opening_qty' => $opening,
                    'in_qty' => $in,
                    'out_qty' => $out,
                    'waste_qty' => $waste,
                    'transfer_qty' => $transfer,
                    'expected_qty' => $expected,
                    'physical_qty' => $physical,
                    'diff_qty' => $diff,
                    'inputs' => $inputs ?: null,
                    'note' => $line['note'] ?? null,
                ]);

                if ($diff != 0.0) {
                    $this->record($stock, 'adjustment', $diff, $user, $data['report_date'], $item->basePrice(), $report->id);
                }
            }

            return $report;
        });
    }

    public function verify(StockReport $report, User $admin): void
    {
        $this->ensureOpen($report);
        $report->update(['status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now(), 'revision_notes' => null]);
    }

    public function requestRevision(StockReport $report, User $admin, string $notes): void
    {
        $this->ensureOpen($report);
        $report->update(['status' => 'revision_needed', 'verified_by' => $admin->id, 'verified_at' => now(), 'revision_notes' => $notes]);
    }

    /**
     * Kirim barang ke cabang lain: stok cabang asal langsung berkurang, cabang tujuan bertambah setelah konfirmasi terima.
     *
     * @param  array{to_branch_id: int, item_id: int, qty: mixed, note?: ?string}  $data
     */
    public function sendTransfer(User $user, Branch $from, array $data): StockTransfer
    {
        return DB::transaction(function () use ($user, $from, $data) {
            $item = Item::with('itemUnits.unit')->findOrFail($data['item_id']);
            $qty = $this->toBase($item, ['qty' => $data['qty'], 'unit_id' => $data['unit_id'] ?? null], null) ?? 0.0;

            $stock = $this->lockStock($from->id, $item->id);
            if ((float) $stock->qty < $qty) {
                throw ValidationException::withMessages(['qty' => "Stok {$item->name} di cabang ini hanya {$stock->qty}."]);
            }

            $transfer = StockTransfer::create([
                'from_branch_id' => $from->id,
                'to_branch_id' => $data['to_branch_id'],
                'item_id' => $item->id,
                'qty' => $qty,
                'status' => 'sent',
                'sent_by' => $user->id,
                'sent_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $this->record($stock, 'transfer_out', -$qty, $user, today()->toDateString(), $item->basePrice(), null, $transfer->id, $data['note'] ?? null);

            return $transfer;
        });
    }

    public function receiveTransfer(StockTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            $stock = $this->lockStock($transfer->to_branch_id, $transfer->item_id);
            $this->record($stock, 'transfer_in', (float) $transfer->qty, $user, today()->toDateString(), $transfer->item->basePrice(), null, $transfer->id, $transfer->note);

            $transfer->update(['status' => 'received', 'received_by' => $user->id, 'received_at' => now()]);
        });
    }

    public function cancelTransfer(StockTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            $stock = $this->lockStock($transfer->from_branch_id, $transfer->item_id);
            $this->record($stock, 'transfer_in', (float) $transfer->qty, $user, today()->toDateString(), $transfer->item->basePrice(), null, $transfer->id, 'Transfer dibatalkan, stok dikembalikan');

            $transfer->update(['status' => 'cancelled']);
        });
    }

    /**
     * Konversi input (qty + satuan) ke satuan terkecil. Kolom laporan memakai pasangan "{kolom}_qty"/"{kolom}_unit_id";
     * transfer memakai "qty"/"unit_id" (kolom = null). Input asli dicatat ke $inputs untuk ditampilkan ulang.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, array{qty: float, unit_id: ?int}>  $inputs
     */
    private function toBase(Item $item, array $line, ?string $column, array &$inputs = []): ?float
    {
        $qtyKey = $column ? "{$column}_qty" : 'qty';
        $unitKey = $column ? "{$column}_unit_id" : 'unit_id';

        if (! isset($line[$qtyKey]) || $line[$qtyKey] === '') {
            return null;
        }

        $unitId = isset($line[$unitKey]) && $line[$unitKey] !== '' ? (int) $line[$unitKey] : null;
        $factor = $item->factorFor($unitId);

        if ($factor === null) {
            throw ValidationException::withMessages(['items' => "Satuan tidak valid untuk {$item->name}."]);
        }

        $qty = round((float) $line[$qtyKey], 2);
        if ($column) {
            $inputs[$column] = ['qty' => $qty, 'unit_id' => $unitId ?? $item->baseUnitId()];
        }

        return round($qty * $factor, 2);
    }

    private function reverseReportMovements(StockReport $report): void
    {
        foreach ($report->movements()->get() as $movement) {
            $this->lockStock($movement->branch_id, $movement->item_id)->decrement('qty', (float) $movement->qty);
        }
        $report->movements()->delete();
    }

    private function lockStock(int $branchId, int $itemId): BranchStock
    {
        BranchStock::firstOrCreate(['branch_id' => $branchId, 'item_id' => $itemId], ['qty' => 0]);

        return BranchStock::where('branch_id', $branchId)->where('item_id', $itemId)->lockForUpdate()->firstOrFail();
    }

    private function record(BranchStock $stock, string $type, float $qty, User $user, string $date, mixed $price, ?int $reportId = null, ?int $transferId = null, ?string $note = null, ?int $transactionId = null): void
    {
        StockMovement::create([
            'branch_id' => $stock->branch_id,
            'item_id' => $stock->item_id,
            'type' => $type,
            'qty' => $qty,
            'unit_price' => $price,
            'report_id' => $reportId,
            'transfer_id' => $transferId,
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'moved_at' => $date,
            'note' => $note,
        ]);

        $stock->increment('qty', $qty);
    }

    private function assertDateOpen(int $branchId, string $date): void
    {
        if (! $this->dateIsOpen($branchId, $date)) {
            throw ValidationException::withMessages(['moved_at' => "Stok tanggal {$date} sudah ditutup (opname sudah dikirim) atau di masa depan. Minta revisi ke Admin Pusat bila ada koreksi."]);
        }
    }

    private function ensureOpen(StockReport $report): void
    {
        if ($report->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Laporan stok ini sudah diproses.']);
        }
    }

    private function ensureSent(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'sent') {
            throw ValidationException::withMessages(['status' => 'Transfer ini sudah diproses.']);
        }
    }
}
