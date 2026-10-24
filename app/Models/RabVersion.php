<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class RabVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    protected $fillable = ['project_id', 'versi', 'status', 'sent_at'];

    protected function casts(): array
    {
        return [
            'versi' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WorkSection::class)->orderBy('urutan')->orderBy('id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(WorkItem::class, WorkSection::class, 'rab_version_id', 'section_id');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
