<?php

namespace App\Services;

use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProgressService
{
    /**
     * Catat update progress satu item. Update oleh Kepala Toko / Admin Pusat otomatis terverifikasi.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    public function update(WorkItem $item, User $user, int $progress, ?string $catatan, array $photos = []): ProgressLog
    {
        return DB::transaction(function () use ($item, $user, $progress, $catatan, $photos) {
            $from = $item->progress;
            $isVerifier = $user->isAdminPusat() || $user->isKepalaToko();
            $disk = Storage::disk(config('filesystems.default'));

            $log = $item->logs()->create([
                'user_id' => $user->id,
                'progress_dari' => $from,
                'progress_ke' => $progress,
                'catatan' => $catatan,
                'verified_by' => $isVerifier ? $user->id : null,
                'verified_at' => $isVerifier ? now() : null,
            ]);

            $project = $item->resolveProject();
            foreach ($photos as $photo) {
                $log->photos()->create(['file_path' => $disk->putFile("projects/{$project->id}/progress", $photo)]);
            }

            $item->update([
                'progress' => $progress,
                'status' => WorkItem::statusFor($progress),
                'tgl_aktual_mulai' => $item->tgl_aktual_mulai ?? ($progress > 0 ? today() : null),
                'tgl_aktual_selesai' => $progress >= 100 ? today() : null,
            ]);

            if ($progress > 0 && $project->status === Project::STATUS_RAB_DIKIRIM) {
                $project->update(['status' => Project::STATUS_BERJALAN]);
            }

            $this->recalculate($project);

            return $log;
        });
    }

    public function verify(ProgressLog $log, User $user): void
    {
        $log->update(['verified_by' => $user->id, 'verified_at' => now()]);
    }

    /** Hitung ulang progress proyek dari item pada RAB aktif. */
    public function recalculate(Project $project): void
    {
        $items = $this->items($project);
        $project->update(['progress' => $this->average($items, $project->progress_mode)]);
    }

    /**
     * Rata-rata progress: 'weighted' berbobot nilai RAB (qty × harga), 'simple' rata-rata sederhana.
     * Kalau seluruh bobot 0, otomatis jatuh ke rata-rata sederhana.
     *
     * @param  Collection<int, WorkItem>  $items
     */
    public function average(Collection $items, string $mode): float
    {
        if ($items->isEmpty()) {
            return 0.0;
        }

        $weight = $mode === 'weighted' ? $items->sum('total') : 0;
        if ($weight > 0) {
            return round($items->sum(fn (WorkItem $i) => $i->progress * $i->total) / $weight, 2);
        }

        return round((float) $items->avg('progress'), 2);
    }

    /**
     * @return array{total: int, selesai: int, belum_selesai: int, avg_progress: float}
     */
    public function summary(Project $project): array
    {
        $items = $this->items($project);

        return [
            'total' => $items->count(),
            'selesai' => $items->where('status', WorkItem::STATUS_SELESAI)->count(),
            'belum_selesai' => $items->where('status', '!=', WorkItem::STATUS_SELESAI)->count(),
            'avg_progress' => (float) $project->progress,
        ];
    }

    /**
     * @return Collection<int, WorkItem>
     */
    private function items(Project $project): Collection
    {
        $rab = $project->currentRab()->first();

        return $rab ? $rab->items()->get() : collect();
    }
}
