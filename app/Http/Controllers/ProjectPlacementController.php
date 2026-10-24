<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssuePlacementRequest;
use App\Http\Requests\SavePlacementRequest;
use App\Models\Project;
use App\Models\ProjectPlacement;
use App\Services\PlacementService;
use App\Services\PlacementTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectPlacementController extends Controller
{
    public function __construct(
        private readonly PlacementService $placements,
        private readonly PlacementTemplateService $template,
    ) {}

    public function store(SavePlacementRequest $request, Project $project): RedirectResponse
    {
        $this->placements->save($project, null, $request->validated(), $request->user());

        return back()->with('success', 'Draft penempatan dibuat.');
    }

    public function update(SavePlacementRequest $request, Project $project, ProjectPlacement $placement): RedirectResponse
    {
        abort_unless($placement->project_id === $project->id, 404);
        $this->placements->save($project, $placement, $request->validated(), $request->user());

        return back()->with('success', 'Draft penempatan diperbarui.');
    }

    public function issue(IssuePlacementRequest $request, ProjectPlacement $placement): RedirectResponse
    {
        $placement->load('project');
        $placement = $this->placements->issue($placement, $request->user(), $request->validated('penandatangan_nama'), $request->validated('penandatangan_jabatan'));

        return back()->with('success', "Surat {$placement->nomor_surat} diterbitkan.");
    }

    public function cancel(ProjectPlacement $placement): RedirectResponse
    {
        $this->placements->cancel($placement);

        return back()->with('success', 'Surat penempatan dibatalkan.');
    }

    public function complete(ProjectPlacement $placement): RedirectResponse
    {
        $this->placements->complete($placement);

        return back()->with('success', 'Penempatan ditandai selesai.');
    }

    public function destroy(ProjectPlacement $placement): RedirectResponse
    {
        $this->placements->delete($placement);

        return back()->with('success', 'Draft penempatan dihapus.');
    }

    public function pdf(Request $request, ProjectPlacement $placement): Response
    {
        $placement->load('project');
        abort_unless($this->placements->canView($placement, $request->user()), 403);

        $name = 'surat-penempatan-'.str_replace('/', '-', $placement->nomor_surat ?? "draft-{$placement->id}").'.pdf';

        return $this->template->pdf($placement)->stream($name);
    }
}
