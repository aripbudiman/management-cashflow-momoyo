<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment_url',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected $appends = [
        'attachment_full_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function getAttachmentFullUrlAttribute(): ?string
    {
        if (! $this->attachment_url) {
            return null;
        }

        if (str_starts_with($this->attachment_url, 'http') || str_starts_with($this->attachment_url, 'data:')) {
            return $this->attachment_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->attachment_url);
        }

        return asset('storage/'.$this->attachment_url);
    }

    /**
     * Aturan persetujuan:
     * - Kepala Toko memutuskan pengajuan staff di cabangnya (bukan pengajuannya sendiri)
     * - Admin Pusat hanya memutuskan pengajuan Kepala Toko; selebihnya hanya memantau
     */
    public function isDecidableBy(User $user): bool
    {
        $requesterIsKepalaToko = $this->employee?->user?->isKepalaToko() ?? false;

        if ($user->isAdminPusat()) {
            return $requesterIsKepalaToko;
        }

        return $user->managesBranch((int) $this->branch_id)
            && ! $requesterIsKepalaToko
            && $this->employee_id !== $user->employee?->id;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
