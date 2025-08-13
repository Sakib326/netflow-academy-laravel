<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'lesson_id', 'type', 'content', 'file_path',
        'score', 'max_score', 'status', 'submitted_at', 
        'graded_at', 'graded_by', 'feedback'
    ];

    protected $casts = [
        'content' => 'array',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime'
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
        
        // Update enrollment progress
        $enrollment = Enrollment::where('user_id', $this->user_id)
            ->whereHas('batch', function($query) {
                $query->where('course_id', $this->lesson->module->course_id);
            })->first();
            
        if ($enrollment) {
            $enrollment->updateProgress();
        }
    }
}