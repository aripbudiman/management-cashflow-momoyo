<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdminPusat();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $item = $this->route('workItem');
        $rabId = $project instanceof Project
            ? $project->currentRab()->value('id')
            : ($item instanceof WorkItem ? $item->section->rab_version_id : null);

        return [
            'section_id' => ['required', 'integer', Rule::exists('work_sections', 'id')->where('rab_version_id', $rabId)],
            'uraian' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'satuan' => ['required', 'string', 'max:30'],
            'harga_satuan' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'durasi_hari' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }
}
