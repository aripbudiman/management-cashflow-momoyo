<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nik',
        'full_name',
        'phone_number',
        'current_branch_id',
        'status',
        'face_id',
        'face_photo_path',
        'face_registered_at',
        'face_data',
    ];

    protected $appends = [
        'face_photo_url',
        'is_face_registered',
    ];

    protected function casts(): array
    {
        return [
            'face_registered_at' => 'datetime',
            'face_data' => 'array',
        ];
    }

    public function hasFaceRegistered(): bool
    {
        return ! empty($this->face_photo_path) || ! empty($this->face_id);
    }

    public function getFacePhotoUrlAttribute(): ?string
    {
        if (! $this->face_photo_path) {
            return null;
        }

        if (str_starts_with($this->face_photo_path, 'http') || str_starts_with($this->face_photo_path, 'data:')) {
            return $this->face_photo_path;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->face_photo_path);
        }

        return asset('storage/'.$this->face_photo_path);
    }

    public function getIsFaceRegisteredAttribute(): bool
    {
        return $this->hasFaceRegistered();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(EmployeePlacement::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
