<?php

namespace App\Traits;

use App\Models\Submission;
use App\Models\Enrollment;

trait AutoGradingTrait
{
    /**
     * Auto grade a quiz submission
     */
    public function autoGradeQuizSubmission(Submission $submission): bool
    {
        if ($submission->type !== 'quiz' || $submission->status === 'graded') {
            return false;
        }

        $lesson = $submission->lesson;
        if (!$lesson || !$lesson->isQuiz()) {
            return false;
        }

        $questions = $lesson->getQuestions();
        $answers = $submission->content['answers'] ?? [];
        
        if (empty($questions) || empty($answers)) {
            return false;
        }

        $totalScore = 0;
        $maxScore = 0;
        $processedAnswers = [];

        foreach ($questions as $index => $question) {
            $questionId = $question['id'] ?? $index;
            $userAnswer = $answers[$questionId] ?? null;
            $correctAnswer = $question['correct_answer'] ?? $question['correct_option'] ?? null;
            $marks = $question['marks'] ?? 1;
            $maxScore += $marks;

            $isCorrect = $userAnswer === $correctAnswer;
            $earnedMarks = $isCorrect ? $marks : 0;
            $totalScore += $earnedMarks;

            $processedAnswers[] = [
                'question_id' => $questionId,
                'question' => $question['question'] ?? "Question " . ($index + 1),
                'user_answer' => $userAnswer,
                'correct_answer' => $correctAnswer,
                'is_correct' => $isCorrect,
                'marks' => $marks,
                'earned_marks' => $earnedMarks,
                'options' => $question['options'] ?? $this->getOptionsFromQuestion($question),
            ];
        }

        // Update submission with grading results
        $submission->update([
            'score' => $totalScore,
            'max_score' => $maxScore,
            'status' => 'graded',
            'graded_at' => now(),
            'graded_by' => auth()->id(),
            'content' => array_merge($submission->content ?? [], [
                'answers' => $answers,
                'graded_answers' => $processedAnswers,
                'auto_graded' => true,
            ]),
            'feedback' => $this->generateAutoGradeFeedback($totalScore, $maxScore, $processedAnswers),
        ]);

        // Update enrollment progress
        $this->updateEnrollmentProgress($submission);

        return true;
    }

    /**
     * Generate automatic feedback for quiz
     */
    protected function generateAutoGradeFeedback(int $totalScore, int $maxScore, array $processedAnswers): string
    {
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
        $correctCount = collect($processedAnswers)->where('is_correct', true)->count();
        $totalQuestions = count($processedAnswers);

        $feedback = "**Auto-Graded Quiz Results**\n\n";
        $feedback .= "- **Score**: {$totalScore}/{$maxScore} points ({$percentage}%)\n";
        $feedback .= "- **Correct Answers**: {$correctCount}/{$totalQuestions}\n\n";

        if ($percentage >= 90) {
            $feedback .= "**Excellent work!** You've demonstrated strong understanding of the material.\n\n";
        } elseif ($percentage >= 80) {
            $feedback .= "**Good job!** You have a solid grasp of most concepts.\n\n";
        } elseif ($percentage >= 70) {
            $feedback .= "**Fair performance.** Review the questions you missed to improve understanding.\n\n";
        } elseif ($percentage >= 60) {
            $feedback .= "**Needs improvement.** Consider reviewing the lesson material and retaking if allowed.\n\n";
        } else {
            $feedback .= "**Requires significant review.** Please revisit the lesson materials before proceeding.\n\n";
        }

        // Add details for incorrect answers
        $incorrectAnswers = collect($processedAnswers)->where('is_correct', false);
        if ($incorrectAnswers->count() > 0) {
            $feedback .= "**Areas for Review:**\n";
            foreach ($incorrectAnswers as $answer) {
                $feedback .= "- {$answer['question']}\n";
            }
        }

        return $feedback;
    }

    /**
     * Extract options from question in different formats
     */
    protected function getOptionsFromQuestion(array $question): array
    {
        $options = [];

        // Check for options array format
        if (isset($question['options']) && is_array($question['options'])) {
            return $question['options'];
        }

        // Check for individual option fields (option_a, option_b, etc.)
        foreach (['a', 'b', 'c', 'd'] as $key) {
            $optionKey = 'option_' . $key;
            if (isset($question[$optionKey]) && !empty($question[$optionKey])) {
                $options[] = [
                    'id' => $key,
                    'key' => $key,
                    'text' => $question[$optionKey],
                    'value' => $question[$optionKey],
                ];
            }
        }

        return $options;
    }

    /**
     * Update enrollment progress for submission
     */
    protected function updateEnrollmentProgress(Submission $submission): void
    {
        if (!$submission->lesson || !$submission->lesson->module || !$submission->lesson->module->course) {
            return;
        }

        $enrollment = Enrollment::where('user_id', $submission->user_id)
            ->whereHas('batch', function($query) use ($submission) {
                $query->where('course_id', $submission->lesson->module->course->id);
            })
            ->first();

        if ($enrollment && method_exists($enrollment, 'updateProgress')) {
            $enrollment->updateProgress();
        }
    }

    /**
     * Bulk auto-grade quiz submissions
     */
    public function bulkAutoGradeQuizzes(array $submissionIds): int
    {
        $gradedCount = 0;
        
        $submissions = Submission::whereIn('id', $submissionIds)
            ->where('type', 'quiz')
            ->where('status', '!=', 'graded')
            ->get();

        foreach ($submissions as $submission) {
            if ($this->autoGradeQuizSubmission($submission)) {
                $gradedCount++;
            }
        }

        return $gradedCount;
    }

    /**
     * Calculate grade statistics for a lesson
     */
    public function calculateGradeStatistics(int $lessonId): array
    {
        $submissions = Submission::where('lesson_id', $lessonId)
            ->where('status', 'graded')
            ->whereNotNull('score')
            ->get();

        if ($submissions->isEmpty()) {
            return [
                'total_submissions' => 0,
                'average_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'pass_rate' => 0,
                'grade_distribution' => [],
            ];
        }

        $scores = $submissions->pluck('score');
        $maxScores = $submissions->pluck('max_score');
        $percentages = $submissions->map(function ($submission) {
            return $submission->max_score > 0 ? ($submission->score / $submission->max_score) * 100 : 0;
        });

        $passRate = $percentages->filter(fn($p) => $p >= 60)->count() / $percentages->count() * 100;

        $gradeDistribution = [
            'A (90-100%)' => $percentages->filter(fn($p) => $p >= 90)->count(),
            'B (80-89%)' => $percentages->filter(fn($p) => $p >= 80 && $p < 90)->count(),
            'C (70-79%)' => $percentages->filter(fn($p) => $p >= 70 && $p < 80)->count(),
            'D (60-69%)' => $percentages->filter(fn($p) => $p >= 60 && $p < 70)->count(),
            'F (<60%)' => $percentages->filter(fn($p) => $p < 60)->count(),
        ];

        return [
            'total_submissions' => $submissions->count(),
            'average_score' => round($percentages->average(), 1),
            'highest_score' => round($percentages->max(), 1),
            'lowest_score' => round($percentages->min(), 1),
            'pass_rate' => round($passRate, 1),
            'grade_distribution' => $gradeDistribution,
        ];
    }

    /**
     * Get submission summary for dashboard
     */
    public function getSubmissionSummary(): array
    {
        $totalSubmissions = Submission::count();
        $pendingGrading = Submission::where('status', 'submitted')->count();
        $gradedToday = Submission::where('status', 'graded')
            ->whereDate('graded_at', today())
            ->count();
        $quizSubmissions = Submission::where('type', 'quiz')->count();
        $assignmentSubmissions = Submission::where('type', 'assignment')->count();

        return [
            'total_submissions' => $totalSubmissions,
            'pending_grading' => $pendingGrading,
            'graded_today' => $gradedToday,
            'quiz_submissions' => $quizSubmissions,
            'assignment_submissions' => $assignmentSubmissions,
            'average_score' => round(
                Submission::where('status', 'graded')
                    ->whereNotNull('score')
                    ->avg('score') ?? 0,
                1
            ),
        ];
    }
}