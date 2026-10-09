<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeePlacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'from_branch_id',
        'to_branch_id',
        'effective_date',
        'surat_tugas_number',
        'surat_tugas_file_url',
        'created_by',
    ];

    protected $appends = [
        'surat_tugas_file_full_url',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function getSuratTugasFileFullUrlAttribute(): ?string
    {
        if (! $this->surat_tugas_file_url) {
            return null;
        }

        if (str_starts_with($this->surat_tugas_file_url, 'http') || str_starts_with($this->surat_tugas_file_url, 'data:')) {
            return $this->surat_tugas_file_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->surat_tugas_file_url);
        }

        return asset('storage/'.$this->surat_tugas_file_url);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
