<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSection extends Model
{
    protected $fillable = ['rab_version_id', 'kode_bidang', 'nama', 'urutan'];

    public function rabVersion(): BelongsTo
    {
        return $this->belongsTo(RabVersion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'section_id')->orderBy('urutan')->orderBy('id');
    }
}
