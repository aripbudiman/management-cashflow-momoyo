<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewPlacementTemplateRequest;
use App\Http\Requests\SavePlacementTemplateRequest;
use App\Services\PlacementTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlacementTemplateController extends Controller
{
    public function __construct(private readonly PlacementTemplateService $template) {}

    public function edit(): Response
    {
        return Inertia::render('letter-templates/Placement', [
            'template' => $this->template->get(),
            'variables' => PlacementTemplateService::VARIABLES,
        ]);
    }

    public function update(SavePlacementTemplateRequest $request): RedirectResponse
    {
        $this->template->save($request->validated('html'), $request->validated('number_format'), (float) $request->validated('margin_cm'));

        return back()->with('success', 'Template surat disimpan. Surat yang sudah terbit tidak berubah.');
    }

    public function reset(): RedirectResponse
    {
        $this->template->resetHtml();

        return back()->with('success', 'Template dikembalikan ke bawaan.');
    }

    /** Preview HTML dengan data contoh, dari isi editor yang belum disimpan. */
    public function preview(PreviewPlacementTemplateRequest $request): JsonResponse
    {
        return response()->json([
            'html' => $this->template->document($request->validated('html'), $this->template->sampleValues(), (float) $request->validated('margin_cm')),
        ]);
    }
}
