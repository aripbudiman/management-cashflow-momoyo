<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\RabVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $data['nilai_kontrak'] = $data['nilai_kontrak'] ?? 0;
            $project = Project::create($data + ['status' => Project::STATUS_DRAFT]);
            $project->rabVersions()->create(['versi' => 1, 'status' => RabVersion::STATUS_DRAFT]);

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project
    {
        if (array_key_exists('nilai_kontrak', $data)) {
            $data['nilai_kontrak'] ??= 0;
        }
        $project->update($data);

        // Mode progress bisa berubah, jadi hitung ulang progress proyek.
        app(ProgressService::class)->recalculate($project);

        return $project;
    }

    public function cancel(Project $project): void
    {
        $project->update(['status' => Project::STATUS_DIBATALKAN]);
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function addDocument(Project $project, User $user, string $kategori, UploadedFile $file): ProjectDocument
    {
        $path = Storage::disk(config('filesystems.default'))->putFile("projects/{$project->id}/documents", $file);

        return $project->documents()->create([
            'kategori' => $kategori,
            'nama_file' => $file->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $user->id,
        ]);
    }

    public function deleteDocument(ProjectDocument $document): void
    {
        Storage::disk(config('filesystems.default'))->delete($document->file_path);
        $document->delete();
    }
}
