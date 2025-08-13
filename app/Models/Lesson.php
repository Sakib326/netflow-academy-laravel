<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id', 'title', 'content', 'type', 'content_url', 'slug',
        'order_index', 'status', 'is_free', 'available_from', 
        'available_until', 'max_score'
    ];

    protected $casts = [
        'content' => 'array',
        'is_free' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime'
    ];

    // Relations
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function discussions()
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    // Helper Functions
    public function isQuiz()
    {
        return $this->type === 'quiz';
    }

    public function isAssignment()
    {
        return $this->type === 'assignment';
    }

    public function isAvailable()
    {
        $now = now();
        $availableFrom = !$this->available_from || $this->available_from <= $now;
        $availableUntil = !$this->available_until || $this->available_until >= $now;
        
        return $this->status === 'published' && $availableFrom && $availableUntil;
    }

    public function hasUserCompleted($userId)
    {
        return $this->submissions()
            ->where('user_id', $userId)
            ->whereIn('type', ['completion', 'quiz', 'assignment'])
            ->exists();
    }

    public function getUserScore($userId)
    {
        $submission = $this->submissions()
            ->where('user_id', $userId)
            ->first();
            
        return $submission ? $submission->score : null;
    }

    public function getQuestions()
    {
        return $this->isQuiz() && $this->content ? $this->content['questions'] ?? [] : [];
    }

    public function getTotalMarks()
    {
        if (!$this->isQuiz()) return $this->max_score ?? 0;
        
        $questions = $this->getQuestions();
        return collect($questions)->sum('marks');
    }

    public function getAverageScore()
    {
        return $this->submissions()
            ->whereNotNull('score')
            ->avg('score') ?? 0;
    }

    public function getCompletionRate()
    {
        $total = $this->module->course->getTotalStudents();
        if ($total == 0) return 0;
        
        $completed = $this->submissions()->count();
        return round(($completed / $total) * 100, 2);
    }
}