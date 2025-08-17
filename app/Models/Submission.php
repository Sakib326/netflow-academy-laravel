<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'lesson_id', 'type', 'content', 'files',
        'score', 'max_score', 'status', 'submitted_at', 
        'graded_at', 'graded_by', 'feedback'
    ];

    protected $casts = [
        'content' => 'array',        // Added - used in autoGradeQuiz() to access answers
        'files' => 'array',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'user_id' => 'integer',      // Added - foreign key should be integer
        'lesson_id' => 'integer',    // Added - foreign key should be integer  
        'graded_by' => 'integer'     // Added - foreign key should be integer
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // Helper Functions
    public function isGraded()
    {
        return $this->status === 'graded';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function getScorePercentage()
    {
        if (!$this->max_score || $this->max_score == 0) return 0;
        return round(($this->score / $this->max_score) * 100, 2);
    }

    public function autoGradeQuiz()
    {
        if (!$this->lesson->isQuiz() || $this->isGraded()) return;
        
        $questions = $this->lesson->getQuestions();
        $answers = $this->content['answers'] ?? [];
        
        $totalScore = 0;
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $userAnswer = $answers[$questionId] ?? null;
            $correctAnswer = $question['correct_option'] ?? null;
            
            if ($userAnswer === $correctAnswer) {
                $totalScore += $question['marks'] ?? 0;
            }
        }
        
        $this->update([
            'score' => $totalScore,
            'max_score' => $this->lesson->getTotalMarks(),
            'status' => 'graded',
            'graded_at' => now()
        ]);
        
        // Update enrollment progress with better error handling
        $this->updateEnrollmentProgress();
    }

    /**
     * Update enrollment progress for this submission
     */
    protected function updateEnrollmentProgress()
    {
        // Added safety checks for relationships
        if (!$this->lesson || !$this->lesson->module || !$this->lesson->module->course) {
            return;
        }
        
        $enrollment = Enrollment::where('user_id', $this->user_id)
            ->whereHas('batch', function($query) {
                $query->where('course_id', $this->lesson->module->course_id);
            })->first();
            
        if ($enrollment && method_exists($enrollment, 'updateProgress')) {
            $enrollment->updateProgress();
        }
    }

    /**
     * Check if this submission passed (score >= passing threshold)
     */
    public function hasPassed($passingPercentage = 60)
    {
        $percentage = $this->getScorePercentage();
        return $percentage >= $passingPercentage;
    }

    /**
     * Get the grade letter based on score percentage
     */
    public function getGradeLetter()
    {
        $percentage = $this->getScorePercentage();
        
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 85) return 'A';
        if ($percentage >= 80) return 'A-';
        if ($percentage >= 75) return 'B+';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 65) return 'B-';
        if ($percentage >= 60) return 'C+';
        if ($percentage >= 55) return 'C';
        if ($percentage >= 50) return 'C-';
        if ($percentage >= 40) return 'D';
        
        return 'F';
    }
}