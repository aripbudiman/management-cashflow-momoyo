<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProgressRequest;
use App\Http\Requests\VerifyProgressRequest;
use App\Models\ProgressLog;
use App\Models\WorkItem;
use App\Services\ProgressService;
use Illuminate\Http\RedirectResponse;

class ProjectProgressController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function update(UpdateProgressRequest $request, WorkItem $workItem): RedirectResponse
    {
        $this->progress->update(
            $workItem,
            $request->user(),
            (int) $request->validated('persen'),
            $request->validated('catatan'),
            $request->file('photos', []),
        );

        return back()->with('success', 'Progress berhasil diperbarui.');
    }

    public function verify(VerifyProgressRequest $request, ProgressLog $progressLog): RedirectResponse
    {
        $this->progress->verify($progressLog, $request->user());

        return back()->with('success', 'Progress terverifikasi.');
    }
}
