<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STAGE_PLANNED = 'planned';
    public const STAGE_IN_PROGRESS = 'in_progress';
    public const STAGE_DEV_DONE = 'dev_done';
    public const STAGE_QA_DONE = 'qa_done';
    public const STAGE_PROD_DONE = 'prod_done';

    protected $fillable = [
        'project_id',
        'shipment_id',
        'title',
        'acceptance_criteria',
        'estimate_hours',
        'start_date',
        'due_date',
        'stage',
        'order',
        'stage_changed_at',
    ];

    protected $casts = [
        'estimate_hours' => 'float',
        'start_date' => 'date',
        'due_date' => 'date',
        'order' => 'int',
        'stage_changed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(TaskWorkLog::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(TaskReschedule::class);
    }
}
