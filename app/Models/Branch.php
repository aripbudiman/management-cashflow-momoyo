<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_code',
        'branch_name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'current_branch_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function dailyCashflows(): HasMany
    {
        return $this->hasMany(DailyCashflow::class);
    }

    /**
     * Hitung jarak user terhadap koordinat cabang menggunakan Haversine Formula (dalam meter).
     */
    public function calculateDistanceTo(float $userLat, float $userLong): float
    {
        $earthRadius = 6371000; // dalam meter

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($userLat);
        $lonTo = deg2rad($userLong);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return round($angle * $earthRadius, 2);
    }

    /**
     * Cek apakah koordinat user berada di dalam radius cabang.
     */
    public function isWithinRadius(float $userLat, float $userLong): bool
    {
        return $this->calculateDistanceTo($userLat, $userLong) <= $this->radius_meters;
    }
}
