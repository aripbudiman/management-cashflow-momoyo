<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectDocumentRequest;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;

class ProjectDocumentController extends Controller
{
    public function __construct(private readonly ProjectService $service) {}

    public function store(StoreProjectDocumentRequest $request, Project $project): RedirectResponse
    {
        $this->service->addDocument($project, $request->user(), $request->validated('kategori'), $request->file('file'));

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroy(ProjectDocument $document): RedirectResponse
    {
        $this->service->deleteDocument($document);

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }
}
