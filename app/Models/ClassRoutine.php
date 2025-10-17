<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoutine extends Model
{
    protected $fillable = [
        'course_id',
        'batch_id',
        'days',
        'off_dates',
    ];

    protected $casts = [
        'days' => 'array',
        'off_dates' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
