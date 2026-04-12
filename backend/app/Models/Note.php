<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Project;

class Note extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'body',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'bool',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
