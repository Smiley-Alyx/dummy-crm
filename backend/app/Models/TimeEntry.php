<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Project;

class TimeEntry extends Model
{
    protected $fillable = [
        'project_id',
        'entry_date',
        'minutes',
        'note',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'minutes' => 'int',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
