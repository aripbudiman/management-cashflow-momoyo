<?php

namespace App\Models;

use App\Concerns\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    use ResolvesStorageUrl;

    protected $fillable = ['project_id', 'kategori', 'nama_file', 'file_path', 'uploaded_by'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return $this->storageUrl($this->file_path);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
