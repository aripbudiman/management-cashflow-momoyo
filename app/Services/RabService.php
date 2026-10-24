<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RabVersion;
use App\Models\WorkItem;
use App\Models\WorkSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RabService
{
    public function __construct(private readonly ProgressService $progress) {}

    public function currentVersion(Project $project): RabVersion
    {
        return $project->currentRab()->first()
            ?? $project->rabVersions()->create(['versi' => 1, 'status' => RabVersion::STATUS_DRAFT]);
    }

    /**
     * Gagalkan perubahan jika RAB sudah dikirim ke klien (perubahan harus lewat revisi).
     */
    public function assertEditable(RabVersion $version): void
    {
        if ($version->isLocked()) {
            throw ValidationException::withMessages([
                'rab' => 'RAB sudah dikirim dan terkunci. Buat revisi baru untuk mengubahnya.',
            ]);
        }
    }

    /**
     * @param  array{kode_bidang: string, nama: string}  $data
     */
    public function saveSection(Project $project, ?WorkSection $section, array $data): WorkSection
    {
        if ($section) {
            $this->assertEditable($section->rabVersion);
            $section->update($data);

            return $section;
        }

        $version = $this->currentVersion($project);
        $this->assertEditable($version);

        return $version->sections()->create($data + ['urutan' => (int) $version->sections()->max('urutan') + 1]);
    }

    public function deleteSection(WorkSection $section): void
    {
        $this->assertEditable($section->rabVersion);
        $project = $section->rabVersion->project;

        $section->delete();
        $this->progress->recalculate($project);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(?WorkItem $item, array $data): WorkItem
    {
        $section = WorkSection::with('rabVersion.project')->findOrFail($data['section_id']);
        $this->assertEditable($section->rabVersion);

        if ($item) {
            $item->update($data);
        } else {
            $item = $section->items()->create($data + [
                'urutan' => (int) $section->items()->max('urutan') + 1,
            ]);
        }

        $this->progress->recalculate($section->rabVersion->project);

        return $item;
    }

    public function deleteItem(WorkItem $item): void
    {
        $project = $item->resolveProject();
        $this->assertEditable($item->section->rabVersion);

        $item->delete();
        $this->progress->recalculate($project);
    }

    /** Tandai RAB aktif sebagai terkirim ke klien: RAB terkunci dan status proyek menjadi RAB Dikirim. */
    public function send(Project $project): void
    {
        $version = $this->currentVersion($project);
        $this->assertEditable($version);

        if (! $version->items()->exists()) {
            throw ValidationException::withMessages(['rab' => 'RAB masih kosong, tambahkan item pekerjaan dulu.']);
        }

        DB::transaction(function () use ($project, $version) {
            $version->update(['status' => RabVersion::STATUS_SENT, 'sent_at' => now()]);

            if ($project->status === Project::STATUS_DRAFT) {
                $project->update(['status' => Project::STATUS_RAB_DIKIRIM]);
            }
        });
    }

    /** Buat versi baru (draft) dari RAB terkirim, progress tiap item ikut disalin. */
    public function revise(Project $project): RabVersion
    {
        $current = $this->currentVersion($project);

        if (! $current->isLocked()) {
            throw ValidationException::withMessages(['rab' => 'RAB aktif masih draft, langsung ubah saja tanpa revisi.']);
        }

        return DB::transaction(function () use ($project, $current) {
            $new = $project->rabVersions()->create(['versi' => $current->versi + 1, 'status' => RabVersion::STATUS_DRAFT]);

            foreach ($current->sections()->with('items')->get() as $section) {
                $copy = $new->sections()->create($section->only(['kode_bidang', 'nama', 'urutan']));
                foreach ($section->items as $item) {
                    $copy->items()->create($item->only([
                        'uraian', 'deskripsi', 'qty', 'satuan', 'harga_satuan', 'durasi_hari', 'progress', 'status',
                        'tgl_rencana_mulai', 'tgl_rencana_selesai', 'tgl_aktual_mulai', 'tgl_aktual_selesai', 'is_addendum', 'urutan',
                    ]));
                }
            }

            return $new;
        });
    }

    public function total(RabVersion $version): float
    {
        return round((float) $version->items()->get()->sum('total'), 2);
    }
}
