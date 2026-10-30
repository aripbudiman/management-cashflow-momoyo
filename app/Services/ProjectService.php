<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectExpense;
use App\Models\RabVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    public function __construct(private readonly ExpenseProofService $proofs) {}

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

    /**
     * @param  array{expense_date: string, category: string, description: string, nominal: mixed, proofs?: array<int, UploadedFile>, remove_proof_ids?: array<int, int>}  $data
     */
    public function addExpense(Project $project, User $user, array $data): ProjectExpense
    {
        $expense = $project->expenses()->create([...$this->expensePayload($data), 'created_by' => $user->id]);
        $this->proofs->sync($expense, "projects/{$project->id}/expenses", $data['proofs'] ?? []);

        return $expense;
    }

    /**
     * @param  array{expense_date: string, category: string, description: string, nominal: mixed, proofs?: array<int, UploadedFile>, remove_proof_ids?: array<int, int>}  $data
     */
    public function updateExpense(ProjectExpense $expense, array $data): ProjectExpense
    {
        $expense->update($this->expensePayload($data));
        $this->proofs->sync($expense, "projects/{$expense->project_id}/expenses", $data['proofs'] ?? [], $data['remove_proof_ids'] ?? []);

        return $expense;
    }

    public function deleteExpense(ProjectExpense $expense): void
    {
        $this->proofs->purge($expense);
        $expense->delete();
    }

    /**
     * Keuntungan bersih = nilai kontrak − total pengeluaran. Nilai kontrak (dasar invoice) tidak diubah.
     *
     * @return array{nilai_kontrak: float, total_expense: float, net_profit: float, margin: float, by_category: array<string, float>}
     */
    public function profitSummary(Project $project): array
    {
        $total = (float) $project->expenses()->sum('nominal');
        $contract = (float) $project->nilai_kontrak;

        return [
            'nilai_kontrak' => $contract,
            'total_expense' => $total,
            'net_profit' => $contract - $total,
            'margin' => $contract > 0 ? round(($contract - $total) / $contract * 100, 1) : 0.0,
            'by_category' => $project->expenses()->selectRaw('category, sum(nominal) as total')->groupBy('category')->pluck('total', 'category')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function expensePayload(array $data): array
    {
        return [
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'description' => $data['description'],
            'nominal' => (float) $data['nominal'],
        ];
    }

    public function deleteDocument(ProjectDocument $document): void
    {
        Storage::disk(config('filesystems.default'))->delete($document->file_path);
        $document->delete();
    }
}
