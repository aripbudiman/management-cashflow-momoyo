<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\ProjectPlacement;
use App\Models\ProjectPlacementEmployee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlacementService
{
    public function __construct(private readonly PlacementTemplateService $template) {}

    /**
     * Simpan draft penempatan. Konflik jadwal (penempatan proyek lain / cuti-izin yang disetujui)
     * menggagalkan simpan kecuali Admin mengisi alasan override.
     *
     * @param  array{tgl_mulai: string, tgl_selesai: string, lokasi?: ?string, catatan?: ?string, override_reason?: ?string, revision_of?: ?int, employees: array<int, array{employee_id: int, peran: string}>}  $data
     */
    public function save(Project $project, ?ProjectPlacement $placement, array $data, User $user): ProjectPlacement
    {
        if ($placement && $placement->status !== ProjectPlacement::STATUS_DRAFT) {
            throw ValidationException::withMessages(['placement' => 'Hanya surat berstatus draft yang bisa diubah. Buat revisi untuk surat yang sudah terbit.']);
        }

        $employees = collect($data['employees'])->unique('employee_id')->values();
        $conflicts = $this->conflicts($project, $employees->pluck('employee_id')->all(), $data['tgl_mulai'], $data['tgl_selesai']);
        if ($conflicts && blank($data['override_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'override_reason' => 'Ada konflik jadwal: '.implode('; ', $conflicts).'. Isi alasan override untuk tetap melanjutkan.',
            ]);
        }

        return DB::transaction(function () use ($project, $placement, $data, $employees, $user, $conflicts) {
            $attrs = [
                'tgl_mulai' => $data['tgl_mulai'],
                'tgl_selesai' => $data['tgl_selesai'],
                'lokasi' => $data['lokasi'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'override_reason' => $conflicts ? $data['override_reason'] : null,
            ];

            if ($placement) {
                $placement->update($attrs);
                $placement->employees()->delete();
            } else {
                $placement = $project->placements()->create($attrs + [
                    'revision_of' => $data['revision_of'] ?? null,
                    'status' => ProjectPlacement::STATUS_DRAFT,
                    'created_by' => $user->id,
                ]);
            }

            foreach ($employees as $row) {
                $placement->employees()->create(['employee_id' => $row['employee_id'], 'peran' => $row['peran']]);
            }

            return $placement;
        });
    }

    /** Terbitkan surat: nomor dibuat, template dibekukan, surat revisi menggantikan surat sebelumnya. */
    public function issue(ProjectPlacement $placement, User $user, string $signerName, string $signerTitle): ProjectPlacement
    {
        if ($placement->status !== ProjectPlacement::STATUS_DRAFT) {
            throw ValidationException::withMessages(['placement' => 'Surat ini sudah diterbitkan atau dibatalkan.']);
        }

        $project = $placement->project;
        $ids = $placement->employees()->pluck('employee_id')->all();
        if (! $ids) {
            throw ValidationException::withMessages(['placement' => 'Pilih minimal satu karyawan sebelum menerbitkan surat.']);
        }

        $conflicts = $this->conflicts($project, $ids, $placement->tgl_mulai->toDateString(), $placement->tgl_selesai->toDateString());
        if ($conflicts && blank($placement->override_reason)) {
            throw ValidationException::withMessages(['placement' => 'Ada konflik jadwal: '.implode('; ', $conflicts).'. Ubah draft dan isi alasan override.']);
        }

        return DB::transaction(function () use ($placement, $project, $user, $signerName, $signerTitle) {
            Project::whereKey($project->id)->lockForUpdate()->first();

            $settings = $this->template->get();
            $sequence = $project->placements()->whereNotNull('nomor_surat')->count() + 1;
            do {
                $number = $this->template->number($settings['number_format'], $project->kode, today(), $sequence++);
            } while (ProjectPlacement::where('nomor_surat', $number)->exists());

            $placement->update([
                'nomor_surat' => $number,
                'tgl_surat' => today(),
                'penandatangan_nama' => $signerName,
                'penandatangan_jabatan' => $signerTitle,
                'template_snapshot' => $settings['html'],
                'status' => ProjectPlacement::STATUS_DITERBITKAN,
                'issued_at' => now(),
                'issued_by' => $user->id,
            ]);

            if ($placement->revision_of) {
                ProjectPlacement::whereKey($placement->revision_of)
                    ->where('status', ProjectPlacement::STATUS_DITERBITKAN)
                    ->update(['status' => ProjectPlacement::STATUS_DIBATALKAN]);
            }

            return $placement;
        });
    }

    public function cancel(ProjectPlacement $placement): void
    {
        if (! in_array($placement->status, [ProjectPlacement::STATUS_DRAFT, ProjectPlacement::STATUS_DITERBITKAN], true)) {
            throw ValidationException::withMessages(['placement' => 'Surat ini tidak bisa dibatalkan.']);
        }

        $placement->update(['status' => ProjectPlacement::STATUS_DIBATALKAN]);
    }

    public function complete(ProjectPlacement $placement): void
    {
        if ($placement->status !== ProjectPlacement::STATUS_DITERBITKAN) {
            throw ValidationException::withMessages(['placement' => 'Hanya surat yang sudah terbit yang bisa diselesaikan.']);
        }

        $placement->update(['status' => ProjectPlacement::STATUS_SELESAI]);
    }

    public function delete(ProjectPlacement $placement): void
    {
        if ($placement->status !== ProjectPlacement::STATUS_DRAFT) {
            throw ValidationException::withMessages(['placement' => 'Hanya draft yang bisa dihapus.']);
        }

        $placement->delete();
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, string>
     */
    public function conflicts(Project $project, array $employeeIds, string $start, string $end): array
    {
        $messages = [];

        $others = ProjectPlacementEmployee::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('placement', fn ($q) => $q
                ->where('status', ProjectPlacement::STATUS_DITERBITKAN)
                ->where('project_id', '!=', $project->id)
                ->where('tgl_mulai', '<=', $end)
                ->where('tgl_selesai', '>=', $start))
            ->with(['employee:id,full_name', 'placement.project:id,kode'])
            ->get();
        foreach ($others as $row) {
            $messages[] = "{$row->employee->full_name} sudah ditempatkan di proyek {$row->placement->project->kode} ({$row->placement->tgl_mulai->format('d/m/Y')}-{$row->placement->tgl_selesai->format('d/m/Y')})";
        }

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->with('employee:id,full_name')
            ->get();
        foreach ($leaves as $leave) {
            $messages[] = "{$leave->employee->full_name} punya {$leave->type} yang disetujui ({$leave->start_date->format('d/m/Y')}-{$leave->end_date->format('d/m/Y')})";
        }

        return $messages;
    }

    /**
     * Surat yang boleh dilihat user: Admin semua, Kepala Toko semua surat proyek cabangnya, Staff hanya yang memuat dirinya.
     *
     * @return Collection<int, ProjectPlacement>
     */
    public function visibleTo(Project $project, User $user): Collection
    {
        $query = $project->placements()
            ->with(['employees.employee:id,full_name,nik', 'revisions:id,revision_of'])
            ->latest('id');

        if (! $user->isAdminPusat() && ! ($user->isKepalaToko() && $user->employee?->current_branch_id === $project->branch_id)) {
            $query->where('status', '!=', ProjectPlacement::STATUS_DRAFT)
                ->whereHas('employees', fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0));
        }

        return $query->get();
    }

    /** Boleh melihat/unduh surat ini? */
    public function canView(ProjectPlacement $placement, User $user): bool
    {
        return $this->visibleTo($placement->project, $user)->contains('id', $placement->id);
    }

    /**
     * Rekap kehadiran selama periode penempatan (dari modul Absen), per karyawan.
     *
     * @return array<int, array{hadir: int, terlambat: int}>
     */
    public function attendanceRecap(ProjectPlacement $placement): array
    {
        if ($placement->status === ProjectPlacement::STATUS_DRAFT) {
            return [];
        }

        $end = min($placement->tgl_selesai->toDateString(), today()->toDateString());
        $rows = Attendance::query()
            ->whereIn('employee_id', $placement->employees->pluck('employee_id'))
            ->whereBetween('date', [$placement->tgl_mulai->toDateString(), $end])
            ->get(['employee_id', 'status'])
            ->groupBy('employee_id');

        return $placement->employees->mapWithKeys(fn ($e) => [
            $e->employee_id => [
                'hadir' => $rows->get($e->employee_id, collect())->count(),
                'terlambat' => $rows->get($e->employee_id, collect())->where('status', 'terlambat')->count(),
            ],
        ])->all();
    }
}
