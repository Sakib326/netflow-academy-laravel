<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'title', 'description', 'order_index', 
        'status', 'available_from', 'available_until'
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'available_until' => 'datetime'
    ];

    // Relations
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order_index');
    }

    public function discussions()
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    // Helper Functions
    public function isAvailable()
    {
        $now = now();
        $availableFrom = !$this->available_from || $this->available_from <= $now;
        $availableUntil = !$this->available_until || $this->available_until >= $now;
        
        return $this->status === 'published' && $availableFrom && $availableUntil;
    }

    public function getTotalLessons()
    {
        return $this->lessons()->count();
    }

    public function getCompletedLessons($userId)
    {
        return $this->lessons()->whereHas('submissions', function($query) use ($userId) {
            $query->where('user_id', $userId)->where('type', 'completion');
        })->count();
    }

    public function getProgressPercentage($userId)
    {
        $total = $this->getTotalLessons();
        if ($total == 0) return 100;
        
        $completed = $this->getCompletedLessons($userId);
        return round(($completed / $total) * 100, 2);
    }
}