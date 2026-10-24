<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RAB_DIKIRIM = 'rab_dikirim';

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_TAGIHAN_DIKIRIM = 'tagihan_dikirim';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'kode',
        'nama',
        'client_name',
        'branch_id',
        'pic_id',
        'alamat',
        'luas_m2',
        'tgl_mulai_rencana',
        'tgl_selesai_rencana',
        'nilai_kontrak',
        'progress_mode',
        'progress',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'luas_m2' => 'float',
            'nilai_kontrak' => 'float',
            'progress' => 'float',
            'tgl_mulai_rencana' => 'date:Y-m-d',
            'tgl_selesai_rencana' => 'date:Y-m-d',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function rabVersions(): HasMany
    {
        return $this->hasMany(RabVersion::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(ProjectPlacement::class);
    }

    public function hasIssuedPlacement(): bool
    {
        return $this->placements()->where('status', ProjectPlacement::STATUS_DITERBITKAN)->exists();
    }

    /** RAB aktif = versi terbaru. */
    public function currentRab(): HasOne
    {
        return $this->hasOne(RabVersion::class)->latestOfMany('versi');
    }

    /**
     * Admin Pusat melihat semua proyek; Staff Cabang dan Kepala Toko melihat proyek di cabangnya
     * serta proyek tempat mereka ditempatkan lewat surat penempatan yang terbit.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdminPusat()) {
            return $query;
        }

        $employeeId = $user->employee?->id ?? 0;

        return $query->where(fn (Builder $q) => $q
            ->where('branch_id', $user->employee?->current_branch_id ?? 0)
            ->orWhereHas('placements', fn (Builder $p) => $p
                ->where('status', ProjectPlacement::STATUS_DITERBITKAN)
                ->whereHas('employees', fn (Builder $e) => $e->where('employee_id', $employeeId))));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdminPusat() || self::query()->whereKey($this->id)->visibleTo($user)->exists();
    }
}
