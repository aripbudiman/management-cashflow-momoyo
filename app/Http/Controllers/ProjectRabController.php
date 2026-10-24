<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRabRequest;
use App\Http\Requests\SaveWorkItemRequest;
use App\Http\Requests\SaveWorkSectionRequest;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkSection;
use App\Services\RabImportService;
use App\Services\RabService;
use Illuminate\Http\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectRabController extends Controller
{
    public function __construct(
        private readonly RabService $rab,
        private readonly RabImportService $import,
    ) {}

    public function storeSection(SaveWorkSectionRequest $request, Project $project): RedirectResponse
    {
        $this->rab->saveSection($project, null, $request->validated());

        return back()->with('success', 'Bidang berhasil ditambahkan.');
    }

    public function updateSection(SaveWorkSectionRequest $request, WorkSection $section): RedirectResponse
    {
        $this->rab->saveSection($section->rabVersion->project, $section, $request->validated());

        return back()->with('success', 'Bidang berhasil diperbarui.');
    }

    public function destroySection(WorkSection $section): RedirectResponse
    {
        $this->rab->deleteSection($section);

        return back()->with('success', 'Bidang beserta item di dalamnya berhasil dihapus.');
    }

    public function storeItem(SaveWorkItemRequest $request, Project $project): RedirectResponse
    {
        $this->rab->saveItem(null, $request->validated());

        return back()->with('success', 'Item pekerjaan berhasil ditambahkan.');
    }

    public function updateItem(SaveWorkItemRequest $request, WorkItem $workItem): RedirectResponse
    {
        $this->rab->saveItem($workItem, $request->validated());

        return back()->with('success', 'Item pekerjaan berhasil diperbarui.');
    }

    public function destroyItem(WorkItem $workItem): RedirectResponse
    {
        $this->rab->deleteItem($workItem);

        return back()->with('success', 'Item pekerjaan berhasil dihapus.');
    }

    public function import(ImportRabRequest $request, Project $project): RedirectResponse
    {
        $result = $this->import->import($project, $request->file('file'));

        return back()->with('success', "Impor selesai: {$result['items']} item, {$result['sections']} bidang baru.");
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = $this->import->template();

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-rab.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function send(Project $project): RedirectResponse
    {
        $this->rab->send($project);

        return back()->with('success', 'RAB ditandai terkirim dan dikunci.');
    }

    public function revise(Project $project): RedirectResponse
    {
        $version = $this->rab->revise($project);

        return back()->with('success', "Revisi RAB v{$version->versi} dibuat sebagai draft.");
    }
}
