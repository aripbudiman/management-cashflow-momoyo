<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'shift_id',
        'date',
        'clock_in_time',
        'clock_in_photo_url',
        'clock_in_lat',
        'clock_in_long',
        'clock_in_distance_meters',
        'clock_in_face_match_percentage',
        'clock_out_time',
        'clock_out_photo_url',
        'clock_out_lat',
        'clock_out_long',
        'clock_out_distance_meters',
        'clock_out_face_match_percentage',
        'is_face_mismatch',
        'status',
        'location_mode',
        'notes',
    ];

    protected $appends = [
        'clock_in_photo_full_url',
        'clock_out_photo_full_url',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_lat' => 'float',
            'clock_in_long' => 'float',
            'clock_in_distance_meters' => 'float',
            'clock_in_face_match_percentage' => 'float',
            'clock_out_lat' => 'float',
            'clock_out_long' => 'float',
            'clock_out_distance_meters' => 'float',
            'clock_out_face_match_percentage' => 'float',
            'is_face_mismatch' => 'boolean',
        ];
    }

    public function getClockInPhotoFullUrlAttribute(): ?string
    {
        if (! $this->clock_in_photo_url) {
            return null;
        }

        if (str_starts_with($this->clock_in_photo_url, 'http') || str_starts_with($this->clock_in_photo_url, 'data:')) {
            return $this->clock_in_photo_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->clock_in_photo_url);
        }

        return asset('storage/'.$this->clock_in_photo_url);
    }

    public function getClockOutPhotoFullUrlAttribute(): ?string
    {
        if (! $this->clock_out_photo_url) {
            return null;
        }

        if (str_starts_with($this->clock_out_photo_url, 'http') || str_starts_with($this->clock_out_photo_url, 'data:')) {
            return $this->clock_out_photo_url;
        }

        $disk = config('filesystems.default', 's3');
        if ($disk === 's3') {
            return Storage::disk('s3')->url($this->clock_out_photo_url);
        }

        return asset('storage/'.$this->clock_out_photo_url);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
