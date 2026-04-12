<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];
}
