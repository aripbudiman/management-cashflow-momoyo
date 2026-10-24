<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateScheduleRequest;
use App\Http\Requests\UpdateItemScheduleRequest;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\TimelineService;
use Illuminate\Http\RedirectResponse;

class ProjectTimelineController extends Controller
{
    public function __construct(private readonly TimelineService $timeline) {}

    public function generate(GenerateScheduleRequest $request, Project $project): RedirectResponse
    {
        $count = $this->timeline->generate($project, $request->validated('alasan'), $request->user());

        return back()->with('success', "Jadwal rencana disusun: {$count} item diperbarui.");
    }

    public function updateItem(UpdateItemScheduleRequest $request, WorkItem $workItem): RedirectResponse
    {
        $this->timeline->setItemDates(
            $workItem,
            $request->validated('tgl_rencana_mulai'),
            $request->validated('tgl_rencana_selesai'),
            $request->validated('alasan'),
            $request->user(),
        );

        return back()->with('success', 'Jadwal item diperbarui.');
    }
}
