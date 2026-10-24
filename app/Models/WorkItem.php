<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    public const STATUS_BELUM_MULAI = 'belum_mulai';

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'section_id',
        'uraian',
        'deskripsi',
        'qty',
        'satuan',
        'harga_satuan',
        'durasi_hari',
        'progress',
        'status',
        'tgl_rencana_mulai',
        'tgl_rencana_selesai',
        'tgl_aktual_mulai',
        'tgl_aktual_selesai',
        'is_addendum',
        'urutan',
    ];

    protected $appends = ['total', 'is_terlambat'];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'harga_satuan' => 'float',
            'progress' => 'integer',
            'is_addendum' => 'boolean',
            'tgl_rencana_mulai' => 'date:Y-m-d',
            'tgl_rencana_selesai' => 'date:Y-m-d',
            'tgl_aktual_mulai' => 'date:Y-m-d',
            'tgl_aktual_selesai' => 'date:Y-m-d',
        ];
    }

    public function getTotalAttribute(): float
    {
        return round($this->qty * $this->harga_satuan, 2);
    }

    /** Lewat tanggal selesai rencana tapi belum selesai. */
    public function getIsTerlambatAttribute(): bool
    {
        return $this->status !== self::STATUS_SELESAI
            && $this->tgl_rencana_selesai !== null
            && $this->tgl_rencana_selesai->lt(today());
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkSection::class, 'section_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProgressLog::class)->latest();
    }

    public function resolveProject(): Project
    {
        return $this->section->rabVersion->project;
    }

    public static function statusFor(int $progress): string
    {
        return match (true) {
            $progress >= 100 => self::STATUS_SELESAI,
            $progress > 0 => self::STATUS_BERJALAN,
            default => self::STATUS_BELUM_MULAI,
        };
    }
}
