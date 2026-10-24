<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlacementEmployee extends Model
{
    protected $fillable = ['placement_id', 'employee_id', 'peran'];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(ProjectPlacement::class, 'placement_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
