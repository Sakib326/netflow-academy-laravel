<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id', 'title', 'content', 'type', 'files', 'slug', 'batch_id', 'questions',
        'order_index', 'status', 'is_free', 'available_from', 
        'available_until', 'max_score'
    ];

    protected $casts = [
        'content' => 'array',        // Added - used in getQuestions()
        'questions' => 'array',
        'files' => 'array',
        'is_free' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'max_score' => 'integer'     // Added - should be integer for calculations
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
        // Fixed: Check both content and questions fields
        if ($this->isQuiz()) {
            // If questions are stored in the questions field
            if (!empty($this->questions)) {
                return $this->questions;
            }
            // If questions are stored in content field
            if (!empty($this->content) && isset($this->content['questions'])) {
                return $this->content['questions'];
            }
        }
        
        return [];
    }

    public function getTotalMarks()
    {
        if (!$this->isQuiz()) {
            return $this->max_score ?? 0;
        }
        
        $questions = $this->getQuestions();
        $totalFromQuestions = collect($questions)->sum('marks');
        
        // Return the sum from questions, or fallback to max_score
        return $totalFromQuestions > 0 ? $totalFromQuestions : ($this->max_score ?? 0);
    }

    public function getAverageScore()
    {
        return $this->submissions()
            ->whereNotNull('score')
            ->avg('score') ?? 0;
    }

    public function getCompletionRate()
    {
        // Added null check for module and course
        if (!$this->module || !$this->module->course) {
            return 0;
        }
        
        $total = $this->module->course->getTotalStudents();
        if ($total == 0) return 0;
        
        $completed = $this->submissions()->count();
        return round(($completed / $total) * 100, 2);
    }
}