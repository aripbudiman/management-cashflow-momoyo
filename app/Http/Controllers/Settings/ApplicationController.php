<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApplicationSettingRequest;
use App\Services\AppSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(private readonly AppSettingService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('settings/Application', [
            'appName' => $this->settings->name(),
            'iconUrl' => $this->settings->iconUrl(),
        ]);
    }

    public function update(UpdateApplicationSettingRequest $request): RedirectResponse
    {
        $this->settings->update(
            $request->validated('app_name'),
            $request->file('icon'),
            $request->boolean('remove_icon'),
        );

        return back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }
}
