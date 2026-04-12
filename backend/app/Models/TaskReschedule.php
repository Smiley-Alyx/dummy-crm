<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReschedule extends Model
{
    protected $fillable = [
        'task_id',
        'from_start_date',
        'to_start_date',
        'from_due_date',
        'to_due_date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'from_start_date' => 'date',
        'to_start_date' => 'date',
        'from_due_date' => 'date',
        'to_due_date' => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
