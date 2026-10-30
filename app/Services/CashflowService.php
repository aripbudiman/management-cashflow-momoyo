<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashflowExpensePhoto;
use App\Models\DailyCashflow;
use App\Models\SetoranTransferProof;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashflowService
{
    public const WALLETS = [
        'tunai' => 'Tunai di Kasir',
        'qris' => 'QRIS',
        'debit' => 'Debit',
        'qpon' => 'QPON',
        'tiktok' => 'TikTok',
        'grab' => 'Grab',
        'gofood' => 'GoFood',
        'shopeefood' => 'ShopeeFood',
    ];

    /**
     * Barang masuk (pembelian) aktif pada hari itu, siap dijadikan baris pengeluaran.
     * Nominal = estimasi dari harga master × qty; staff bisa mengubah/menghapusnya di form.
     *
     * @return Collection<int, array{id: int, label: string, nominal: float, items: array<int, string>}>
     */
    public function purchasesFor(int $branchId, string $date): Collection
    {
        return StockTransaction::query()
            ->with(['items.item:id,name', 'items.unit:id,name'])
            ->where('branch_id', $branchId)
            ->where('type', 'in')
            ->where('status', 'active')
            ->whereDate('moved_at', $date)
            ->orderBy('id')
            ->get()
            ->map(function (StockTransaction $t): array {
                $items = $t->items->where('type', 'in');
                $names = $items->map(fn ($i) => ($i->item?->name ?? 'Barang').' '.rtrim(rtrim(number_format((float) $i->input_qty, 2, '.', ''), '0'), '.').' '.($i->unit?->name ?? ''))->map(fn ($s) => trim($s))->values()->all();

                return [
                    'id' => $t->id,
                    'label' => 'Barang masuk: '.Str::limit(implode(', ', $names), 200),
                    'nominal' => round((float) $items->sum(fn ($i) => (float) $i->base_qty * (float) $i->unit_price), 2),
                    'items' => $names,
                ];
            })
            ->values();
    }

    /**
     * Simpan (atau perbarui) closing harian satu cabang. Detail, pengeluaran, dan bukti transfer diganti utuh.
     *
     * @param  array{report_date: string, wallets: array<string, mixed>, expenses?: array<int, array{description: string, nominal: mixed, photos?: array<int, UploadedFile>, keep_photo_ids?: array<int, int>}>, transfer_amount: mixed, proofs?: array<int, UploadedFile>, keep_proof_ids?: array<int, int>, notes?: ?string}  $data
     */
    public function save(User $user, Branch $branch, array $data): DailyCashflow
    {
        $existing = DailyCashflow::where('branch_id', $branch->id)->where('report_date', $data['report_date'])->first();

        if ($existing?->status === 'verified_by_finance') {
            throw ValidationException::withMessages(['report_date' => 'Closing tanggal ini sudah diverifikasi Admin Pusat dan tidak bisa diubah.']);
        }

        $disk = Storage::disk(config('filesystems.default'));
        $newPaths = [];

        try {
            $cashflow = DB::transaction(function () use ($user, $branch, $data, $existing, $disk, &$newPaths) {
                $cashflow = $existing ?? new DailyCashflow(['branch_id' => $branch->id, 'report_date' => $data['report_date']]);
                $cashflow->fill([
                    'closing_by' => $user->id,
                    'status' => 'submitted',
                    'notes' => $data['notes'] ?? null,
                    'revision_notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ])->save();

                $cashflow->details()->delete();
                foreach (self::WALLETS as $key => $label) {
                    $cashflow->details()->create(['payment_method' => $key, 'nominal' => (float) ($data['wallets'][$key] ?? 0)]);
                }

                // Foto pengeluaran lama: dipertahankan hanya jika id-nya dikirim ulang (keep_photo_ids)
                $oldPhotos = CashflowExpensePhoto::whereIn('expense_id', $cashflow->expenses()->select('id'))->get()->keyBy('id');
                $keptPaths = [];

                $validStockIds = StockTransaction::where('branch_id', $branch->id)->where('type', 'in')
                    ->whereIn('id', collect($data['expenses'] ?? [])->pluck('stock_transaction_id')->filter()->all())
                    ->pluck('id')->all();

                $cashflow->expenses()->delete();
                foreach ($data['expenses'] ?? [] as $expenseData) {
                    $stockId = in_array((int) ($expenseData['stock_transaction_id'] ?? 0), $validStockIds, true) ? (int) $expenseData['stock_transaction_id'] : null;
                    $expense = $cashflow->expenses()->create([
                        'stock_transaction_id' => $stockId,
                        'category' => $stockId ? 'Barang Masuk' : 'Operasional Harian',
                        'description' => $expenseData['description'],
                        'nominal' => (float) $expenseData['nominal'],
                    ]);

                    foreach ($expenseData['keep_photo_ids'] ?? [] as $photoId) {
                        if ($old = $oldPhotos->get((int) $photoId)) {
                            $expense->photos()->create(['photo_url' => $old->photo_url]);
                            $keptPaths[] = $old->photo_url;
                        }
                    }

                    foreach ($expenseData['photos'] ?? [] as $photo) {
                        $path = $disk->putFile("cashflows/{$branch->id}/{$data['report_date']}/expenses", $photo);
                        $newPaths[] = $path;
                        $expense->photos()->create(['photo_url' => $path]);
                    }
                }

                $removedPhotoPaths = $oldPhotos->pluck('photo_url')->diff($keptPaths)->all();

                // Bukti transfer lama dipertahankan hanya jika id-nya dikirim ulang (keep_proof_ids)
                $oldProofs = SetoranTransferProof::whereIn('transfer_id', $cashflow->setoranTransfers()->select('id'))->get()->keyBy('id');
                $cashflow->setoranTransfers()->delete();

                $transfer = $cashflow->setoranTransfers()->create([
                    'transfer_amount' => (float) $data['transfer_amount'],
                    'transfer_time' => now(),
                    'status' => 'pending_verification',
                ]);

                $keptPaths = [];
                foreach ($data['keep_proof_ids'] ?? [] as $proofId) {
                    if ($old = $oldProofs->get((int) $proofId)) {
                        $transfer->proofs()->create(['photo_url' => $old->photo_url]);
                        $keptPaths[] = $old->photo_url;
                    }
                }
                foreach ($data['proofs'] ?? [] as $file) {
                    $path = $disk->putFile("cashflows/{$branch->id}/{$data['report_date']}", $file);
                    $newPaths[] = $path;
                    $transfer->proofs()->create(['photo_url' => $path]);
                }
                $transfer->update(['proof_receipt_url' => $transfer->proofs()->value('photo_url')]);

                $removedProofPaths = $oldProofs->pluck('photo_url')->diff($keptPaths)->all();

                $cashflow->recalculateTotals();

                foreach (array_merge($removedProofPaths, $removedPhotoPaths) as $old) {
                    $disk->delete($old);
                }

                return $cashflow;
            });
        } catch (\Throwable $e) {
            foreach ($newPaths as $path) {
                $disk->delete($path);
            }
            throw $e;
        }

        return $cashflow;
    }

    /**
     * Setujui setoran: total transfer harus sama dengan total setor.
     */
    public function verify(DailyCashflow $cashflow, User $admin): void
    {
        $this->ensureOpen($cashflow);

        $transferred = (float) $cashflow->setoranTransfers()->sum('transfer_amount');
        if (round($transferred, 2) !== round((float) $cashflow->total_net_cash, 2)) {
            throw ValidationException::withMessages([
                'status' => 'Rekonsiliasi tidak sesuai: total transfer Rp '.number_format($transferred, 0, ',', '.')
                    .' ≠ total setor Rp '.number_format((float) $cashflow->total_net_cash, 0, ',', '.').'. Minta revisi ke cabang.',
            ]);
        }

        DB::transaction(function () use ($cashflow, $admin) {
            $cashflow->update(['status' => 'verified_by_finance', 'verified_by' => $admin->id, 'verified_at' => now(), 'revision_notes' => null]);
            $cashflow->setoranTransfers()->update(['status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now()]);
        });
    }

    public function requestRevision(DailyCashflow $cashflow, User $admin, string $notes): void
    {
        $this->ensureOpen($cashflow);

        DB::transaction(function () use ($cashflow, $admin, $notes) {
            $cashflow->update(['status' => 'revision_needed', 'verified_by' => $admin->id, 'verified_at' => now(), 'revision_notes' => $notes]);
            $cashflow->setoranTransfers()->update(['status' => 'mismatch']);
        });
    }

    private function ensureOpen(DailyCashflow $cashflow): void
    {
        if ($cashflow->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Closing ini sudah diproses.']);
        }
    }
}
