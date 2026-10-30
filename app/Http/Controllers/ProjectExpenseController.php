<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectExpenseRequest;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;

class ProjectExpenseController extends Controller
{
    public function __construct(private readonly ProjectService $service) {}

    public function store(SaveProjectExpenseRequest $request, Project $project): RedirectResponse
    {
        $this->service->addExpense($project, $request->user(), $request->validated());

        return back()->with('success', 'Pengeluaran proyek dicatat.');
    }

    public function update(SaveProjectExpenseRequest $request, ProjectExpense $expense): RedirectResponse
    {
        $this->service->updateExpense($expense, $request->validated());

        return back()->with('success', 'Pengeluaran proyek diperbarui.');
    }

    public function destroy(ProjectExpense $expense): RedirectResponse
    {
        abort_unless(request()->user()->isAdminPusat(), 403);
        $this->service->deleteExpense($expense);

        return back()->with('success', 'Pengeluaran proyek dihapus.');
    }
}
