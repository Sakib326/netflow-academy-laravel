<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'instructor_id', 'category_id', 
        'thumbnail', 'price', 'status', 'start_date', 'end_date'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    // Relations
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function enrollments()
    {
        return $this->hasManyThrough(Enrollment::class, Batch::class);
    }

    public function students()
    {
        return $this->hasManyThrough(User::class, Enrollment::class, 'batch_id', 'id', 'id', 'user_id')
            ->whereHas('enrollments', function($query) {
                $query->whereHas('batch', function($q) {
                    $q->where('course_id', $this->id);
                });
            });
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function discussions()
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    // Helper Functions
    public function isFree()
    {
        return $this->price == 0;
    }

    public function isPublished()
    {
        return $this->status === 'published';
    }

    public function getActiveBatchesCount()
    {
        return $this->batches()->where('is_active', true)->count();
    }

    public function getTotalStudents()
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function getTotalLessons()
    {
        return $this->lessons()->count();
    }

    public function getTotalQuizzes()
    {
        return $this->lessons()->where('type', 'quiz')->count();
    }

    public function getTotalRevenue()
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getCompletionRate()
    {
        $total = $this->enrollments()->count();
        if ($total == 0) return 0;
        
        $completed = $this->enrollments()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }
}