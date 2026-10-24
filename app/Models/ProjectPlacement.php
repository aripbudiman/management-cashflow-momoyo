<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectPlacement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_DITERBITKAN = 'diterbitkan';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'project_id',
        'revision_of',
        'nomor_surat',
        'tgl_surat',
        'tgl_mulai',
        'tgl_selesai',
        'lokasi',
        'catatan',
        'penandatangan_nama',
        'penandatangan_jabatan',
        'status',
        'override_reason',
        'template_snapshot',
        'issued_at',
        'issued_by',
        'created_by',
    ];

    protected $hidden = ['template_snapshot'];

    protected function casts(): array
    {
        return [
            'tgl_surat' => 'date:Y-m-d',
            'tgl_mulai' => 'date:Y-m-d',
            'tgl_selesai' => 'date:Y-m-d',
            'issued_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(ProjectPlacementEmployee::class, 'placement_id');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of');
    }
}
