<?php

namespace App\Repositories;

use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectRepository
{
    /**
     * @param  array{search?: mixed, status?: mixed, branch_id?: mixed}  $filters
     */
    public function paginate(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Project::query()
            ->visibleTo($user)
            ->with(['branch:id,branch_name', 'pic:id,full_name'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nama', 'ilike', "%{$v}%")
                ->orWhere('kode', 'ilike', "%{$v}%")
                ->orWhere('client_name', 'ilike', "%{$v}%")))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{total: int, by_status: array<string, int>, avg_progress: float, terlambat: int, item_terlambat: int}
     */
    public function stats(User $user): array
    {
        $projects = Project::query()->visibleTo($user)->get(['status', 'progress', 'tgl_selesai_rencana']);
        $active = $projects->whereNotIn('status', [Project::STATUS_SELESAI, Project::STATUS_DIBATALKAN]);

        return [
            'total' => $projects->count(),
            'by_status' => $projects->countBy('status')->all(),
            'avg_progress' => round((float) $active->avg('progress'), 2),
            'terlambat' => $active->filter(fn (Project $p) => $p->tgl_selesai_rencana?->lt(today()))->count(),
            'item_terlambat' => $this->lateItems($user),
        ];
    }

    /** Jumlah item (pada RAB aktif proyek aktif) yang lewat tanggal rencana selesai tapi belum selesai. */
    private function lateItems(User $user): int
    {
        $projectIds = Project::query()->visibleTo($user)
            ->whereNotIn('status', [Project::STATUS_SELESAI, Project::STATUS_DIBATALKAN])
            ->pluck('id');

        return WorkItem::query()
            ->where('status', '!=', WorkItem::STATUS_SELESAI)
            ->whereDate('tgl_rencana_selesai', '<', today())
            ->whereHas('section.rabVersion', fn ($q) => $q
                ->whereIn('project_id', $projectIds)
                ->whereRaw('versi = (select max(rv.versi) from rab_versions rv where rv.project_id = rab_versions.project_id)'))
            ->count();
    }

    public function loadDetail(Project $project): Project
    {
        return $project->load([
            'branch:id,branch_name',
            'pic:id,full_name',
            'documents' => fn ($q) => $q->with('uploader:id,name')->latest(),
            'rabVersions:id,project_id,versi,status,sent_at',
            'currentRab.sections.items',
        ]);
    }

    /**
     * Riwayat update progress terbaru pada RAB aktif proyek.
     *
     * @return Collection<int, ProgressLog>
     */
    public function recentLogs(Project $project, int $limit = 40): Collection
    {
        return ProgressLog::query()
            ->whereHas('workItem.section.rabVersion', fn ($q) => $q->where('project_id', $project->id))
            ->with(['user:id,name', 'verifier:id,name', 'photos', 'workItem:id,uraian'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
