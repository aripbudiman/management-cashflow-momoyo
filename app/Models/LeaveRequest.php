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
        'type',
        'start_date',
        'end_date',
        'reason',
        'attachment_url',
        'status',
        'approved_by',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
