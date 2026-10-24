<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPlacement;
use App\Models\TimelineChange;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimelineService
{
    /**
     * Atur tanggal rencana satu item secara manual. Mengubah tanggal yang sudah ada wajib beralasan.
     */
    public function setItemDates(WorkItem $item, ?string $start, ?string $end, ?string $reason, User $user): WorkItem
    {
        DB::transaction(fn () => $this->apply($item, $start, $end, $reason, $user));

        return $item;
    }

    /**
     * Susun tanggal rencana dari durasi hari: item dalam satu bidang berurutan mulai dari tanggal mulai proyek,
     * antar bidang berjalan paralel. Item tanpa durasi dihitung 1 hari.
     */
    public function generate(Project $project, ?string $reason, User $user): int
    {
        if (! $project->tgl_mulai_rencana) {
            throw ValidationException::withMessages(['alasan' => 'Isi tanggal mulai rencana proyek dulu di tab Ringkasan (Edit).']);
        }

        $rab = $project->currentRab()->with('sections.items')->first();
        $changed = 0;

        DB::transaction(function () use ($rab, $project, $reason, $user, &$changed) {
            foreach ($rab?->sections ?? [] as $section) {
                $cursor = Carbon::parse($project->tgl_mulai_rencana);
                foreach ($section->items as $item) {
                    $days = max(1, (int) $item->durasi_hari);
                    $start = $cursor->copy();
                    $end = $cursor->copy()->addDays($days - 1);
                    $cursor = $end->copy()->addDay();

                    if ($this->apply($item, $start->toDateString(), $end->toDateString(), $reason, $user)) {
                        $changed++;
                    }
                }
            }
        });

        return $changed;
    }

    /**
     * Milestone proyek untuk garis waktu.
     *
     * @return array<int, array{label: string, date: ?string}>
     */
    public function milestones(Project $project): array
    {
        $rab = $project->rabVersions()->whereNotNull('sent_at')->orderBy('sent_at')->first();
        $placement = $project->placements()->where('status', '!=', ProjectPlacement::STATUS_DRAFT)->whereNotNull('issued_at')->orderBy('issued_at')->first();
        $firstProgress = $project->currentRab()->first()?->items()->min('tgl_aktual_mulai');

        return [
            ['label' => 'RAB Dikirim', 'date' => $rab?->sent_at?->toDateString()],
            ['label' => 'Penempatan Terbit', 'date' => $placement?->issued_at?->toDateString()],
            ['label' => 'Progress Berjalan', 'date' => $firstProgress ? Carbon::parse($firstProgress)->toDateString() : null],
            ['label' => 'Selesai', 'date' => $project->status === Project::STATUS_SELESAI ? $project->updated_at?->toDateString() : null],
        ];
    }

    private function apply(WorkItem $item, ?string $start, ?string $end, ?string $reason, User $user): bool
    {
        $new = ['tgl_rencana_mulai' => $start, 'tgl_rencana_selesai' => $end];
        $changes = [];
        foreach ($new as $field => $value) {
            $old = $item->{$field}?->toDateString();
            if ($old !== $value) {
                $changes[$field] = [$old, $value];
            }
        }
        if (! $changes) {
            return false;
        }

        $hadDates = $item->tgl_rencana_mulai !== null || $item->tgl_rencana_selesai !== null;
        if ($hadDates && blank($reason)) {
            throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi saat mengubah jadwal yang sudah ada.']);
        }

        $item->update($new);
        foreach ($changes as $field => [$old, $value]) {
            if ($hadDates) {
                TimelineChange::create([
                    'work_item_id' => $item->id,
                    'user_id' => $user->id,
                    'field' => $field,
                    'nilai_lama' => $old,
                    'nilai_baru' => $value,
                    'alasan' => $reason,
                ]);
            }
        }

        return true;
    }
}
