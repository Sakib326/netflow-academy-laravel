<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'name', 'start_date', 'end_date', 'max_students', 'is_active',"zoom_link"
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean'
    ];

    // Relations
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->hasManyThrough(User::class, Enrollment::class, 'batch_id', 'id', 'id', 'user_id');
    }

    public function activeStudents()
    {
        return $this->hasManyThrough(User::class, Enrollment::class, 'batch_id', 'id', 'id', 'user_id')
            ->wherePivot('status', 'active');
    }

    public function discussions()
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    // Helper Functions
    public function isFull()
    {
        return $this->enrollments()->where('status', 'active')->count() >= $this->max_students;
    }

    public function getAvailableSlots()
    {
        return $this->max_students - $this->enrollments()->where('status', 'active')->count();
    }

    public function getAverageProgress()
    {
        return $this->enrollments()->where('status', 'active')->avg('progress_percentage') ?? 0;
    }

    public function hasStarted()
    {
        return $this->start_date <= now();
    }

    public function hasEnded()
    {
        return $this->end_date && $this->end_date <= now();
    }


    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'batch_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class, 'batch_id');
    }

    public function classRoutine()
    {
        return $this->hasOne(ClassRoutine::class);
    }
}
