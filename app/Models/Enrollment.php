<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'batch_id', 'enrolled_at', 'status', 'progress_percentage','order_id'
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'progress_percentage' => 'decimal:2'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }



    // Helper Functions
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function updateProgress()
    {
        $course = $this->batch->course;
        $totalLessons = $course->getTotalLessons();

        if ($totalLessons == 0) {
            $this->update(['progress_percentage' => 100]);
            return;
        }

        $completedLessons = Submission::where('user_id', $this->user_id)
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->whereIn('type', ['completion', 'quiz', 'assignment'])
            ->count();

        $progress = round(($completedLessons / $totalLessons) * 100, 2);
        $this->update(['progress_percentage' => $progress]);

        if ($progress >= 100 && $this->status === 'active') {
            $this->update(['status' => 'completed']);
        }
    }

    public function course()
    {
        // Correct: go through batch to course
        return $this->hasOneThrough(
            Course::class,
            Batch::class,
            'id',        // Foreign key on Batch table...
            'id',        // Foreign key on Course table...
            'batch_id',  // Local key on Enrollment table...
            'course_id'  // Local key on Batch table...
        );
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
