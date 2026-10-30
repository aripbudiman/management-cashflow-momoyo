<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectPlacement;
use App\Repositories\ProjectRepository;
use App\Services\PlacementService;
use App\Services\ProgressService;
use App\Services\ProjectExportService;
use App\Services\ProjectService;
use App\Services\TimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectRepository $projects,
        private readonly ProgressService $progress,
        private readonly PlacementService $placements,
        private readonly TimelineService $timeline,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('projects/Index', [
            'projects' => $this->projects->paginate($user, $request->only(['search', 'status', 'branch_id'])),
            'stats' => $this->projects->stats($user),
            'branches' => $user->isAdminPusat() ? Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']) : [],
            'filters' => $request->only(['search', 'status', 'branch_id']),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        abort_unless($project->isVisibleTo($request->user()), 403);

        $this->projects->loadDetail($project);
        $user = $request->user();
        $placements = $this->placements->visibleTo($project, $user);

        return Inertia::render('projects/Show', [
            'project' => $project,
            'summary' => $this->progress->summary($project),
            'logs' => $this->projects->recentLogs($project),
            'placements' => $placements,
            'recaps' => $user->isAdminPusat() || $user->isKepalaToko()
                ? $placements->mapWithKeys(fn ($p) => [$p->id => $this->placements->attendanceRecap($p)])
                : (object) [],
            'hasPlacement' => $project->hasIssuedPlacement(),
            'milestones' => $this->timeline->milestones($project),
            'employees' => $user->isAdminPusat()
                ? Employee::where('status', 'active')->with('currentBranch:id,branch_name')->orderBy('full_name')->get(['id', 'full_name', 'nik', 'current_branch_id'])
                : [],
            'signerDefaults' => $user->isAdminPusat() ? $this->lastSigner($project) : null,
            'pics' => $request->user()->isAdminPusat()
                ? Employee::where('status', 'active')->where('current_branch_id', $project->branch_id)->orderBy('full_name')->get(['id', 'full_name'])
                : [],
            'expenses' => $user->isAdminPusat() ? $project->expenses()->with(['creator:id,name', 'proofs'])->latest('expense_date')->latest('id')->get() : [],
            'profit' => $user->isAdminPusat() ? $this->service->profitSummary($project) : null,
            'expenseCategories' => ProjectExpense::CATEGORIES,
            'canManage' => $request->user()->isAdminPusat(),
            'canVerify' => $request->user()->isAdminPusat() || $request->user()->isKepalaToko(),
        ]);
    }

    /**
     * @return array{nama: ?string, jabatan: ?string}
     */
    private function lastSigner(Project $project): array
    {
        $last = $project->placements()->whereNotNull('penandatangan_nama')->latest('id')->first()
            ?? ProjectPlacement::whereNotNull('penandatangan_nama')->latest('id')->first();

        return ['nama' => $last?->penandatangan_nama, 'jabatan' => $last?->penandatangan_jabatan];
    }

    public function store(SaveProjectRequest $request): RedirectResponse
    {
        $project = $this->service->create($request->validated());

        return redirect("/projects/{$project->id}")->with('success', 'Proyek berhasil dibuat.');
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        $this->service->update($project, $request->validated());

        return back()->with('success', 'Proyek berhasil diperbarui.');
    }

    public function cancel(Project $project): RedirectResponse
    {
        $this->service->cancel($project);

        return back()->with('success', 'Proyek dibatalkan.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->service->delete($project);

        return redirect('/projects')->with('success', 'Proyek berhasil dihapus.');
    }

    public function export(Request $request, Project $project, ProjectExportService $export): StreamedResponse
    {
        abort_unless($project->isVisibleTo($request->user()), 403);

        $spreadsheet = $export->progressReport($project);

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            "progress-{$project->kode}-".today()->toDateString().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
