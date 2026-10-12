<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Simpan (atau perbarui) laporan stok harian satu cabang. Mutasi laporan lama dibalik lalu dibuat ulang.
     *
     * @param  array{report_date: string, notes?: ?string, items: array<int, array{item_id: int, in_qty?: mixed, out_qty?: mixed, waste_qty?: mixed, physical_qty?: mixed, note?: ?string}>}  $data
     */
    public function saveReport(User $user, Branch $branch, array $data): StockReport
    {
        return DB::transaction(function () use ($user, $branch, $data) {
            $existing = StockReport::where('branch_id', $branch->id)
                ->where('report_date', $data['report_date'])
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'verified') {
                throw ValidationException::withMessages(['report_date' => 'Laporan stok tanggal ini sudah diverifikasi Admin Pusat dan tidak bisa diubah.']);
            }

            $latestDate = StockReport::where('branch_id', $branch->id)->max('report_date');
            if ($latestDate && $data['report_date'] < substr((string) $latestDate, 0, 10)) {
                throw ValidationException::withMessages(['report_date' => 'Laporan hanya bisa diisi untuk tanggal terbaru (setelah '.substr((string) $latestDate, 0, 10).').']);
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

            $items = Item::whereIn('id', collect($data['items'])->pluck('item_id'))->get()->keyBy('id');

            foreach ($data['items'] as $line) {
                $item = $items->get((int) $line['item_id']);
                $in = round((float) ($line['in_qty'] ?? 0), 2);
                $out = round((float) ($line['out_qty'] ?? 0), 2);
                $waste = round((float) ($line['waste_qty'] ?? 0), 2);
                $physical = isset($line['physical_qty']) && $line['physical_qty'] !== '' ? round((float) $line['physical_qty'], 2) : null;

                if ($in == 0.0 && $out == 0.0 && $waste == 0.0 && $physical === null) {
                    continue;
                }

                $stock = $this->lockStock($branch->id, $item->id);
                $opening = (float) $stock->qty;
                $expected = round($opening + $in - $out - $waste, 2);

                if ($expected < 0) {
                    throw ValidationException::withMessages(['items' => "Stok {$item->name} tidak cukup (stok awal {$opening}, keluar+rusak melebihi stok)."]);
                }

                $diff = $physical !== null ? round($physical - $expected, 2) : 0.0;

                $report->items()->create([
                    'item_id' => $item->id,
                    'unit_price' => $item->price,
                    'opening_qty' => $opening,
                    'in_qty' => $in,
                    'out_qty' => $out,
                    'waste_qty' => $waste,
                    'expected_qty' => $expected,
                    'physical_qty' => $physical,
                    'diff_qty' => $diff,
                    'note' => $line['note'] ?? null,
                ]);

                $movements = [['in', $in], ['out', -$out], ['waste', -$waste], ['adjustment', $diff]];
                foreach ($movements as [$type, $qty]) {
                    if ($qty != 0.0) {
                        $this->record($stock, $type, $qty, $user, $data['report_date'], $item->price, $report->id);
                    }
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
            $item = Item::findOrFail($data['item_id']);
            $qty = round((float) $data['qty'], 2);

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

            $this->record($stock, 'transfer_out', -$qty, $user, today()->toDateString(), $item->price, null, $transfer->id, $data['note'] ?? null);

            return $transfer;
        });
    }

    public function receiveTransfer(StockTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            $stock = $this->lockStock($transfer->to_branch_id, $transfer->item_id);
            $this->record($stock, 'transfer_in', (float) $transfer->qty, $user, today()->toDateString(), $transfer->item->price, null, $transfer->id, $transfer->note);

            $transfer->update(['status' => 'received', 'received_by' => $user->id, 'received_at' => now()]);
        });
    }

    public function cancelTransfer(StockTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->ensureSent($transfer);

            $stock = $this->lockStock($transfer->from_branch_id, $transfer->item_id);
            $this->record($stock, 'transfer_in', (float) $transfer->qty, $user, today()->toDateString(), $transfer->item->price, null, $transfer->id, 'Transfer dibatalkan, stok dikembalikan');

            $transfer->update(['status' => 'cancelled']);
        });
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

    private function record(BranchStock $stock, string $type, float $qty, User $user, string $date, mixed $price, ?int $reportId = null, ?int $transferId = null, ?string $note = null): void
    {
        StockMovement::create([
            'branch_id' => $stock->branch_id,
            'item_id' => $stock->item_id,
            'type' => $type,
            'qty' => $qty,
            'unit_price' => $price,
            'report_id' => $reportId,
            'transfer_id' => $transferId,
            'user_id' => $user->id,
            'moved_at' => $date,
            'note' => $note,
        ]);

        $stock->increment('qty', $qty);
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
