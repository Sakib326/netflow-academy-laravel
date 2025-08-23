<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class QuizAnswersView extends Field
{
    protected string $view = 'filament.forms.components.quiz-answers-view';

    public static function make(string $name = 'quiz_answers'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function getAnswersData(): array
    {
        $record = $this->getRecord();

        if (!$record || !$record->content || !isset($record->content['answers'])) {
            return [
                'answers' => [],
                'summary' => [
                    'total_questions' => 0,
                    'correct_answers' => 0,
                    'total_score' => 0,
                    'max_score' => 0,
                    'percentage' => 0,
                ]
            ];
        }

        $lesson = $record->lesson;
        $questions = $lesson ? $lesson->getQuestions() : [];
        $answers = $record->content['answers'] ?? [];

        $processedAnswers = [];
        $totalScore = 0;
        $maxScore = 0;
        $correctCount = 0;

        foreach ($questions as $index => $question) {
            $questionId = $question['id'] ?? $index;
            $userAnswer = $answers[$questionId] ?? null;
            $correctAnswer = $question['correct_answer'] ?? $question['correct_option'] ?? null;
            $marks = $question['marks'] ?? 1;
            $isCorrect = $userAnswer === $correctAnswer;

            if ($isCorrect) {
                $totalScore += $marks;
                $correctCount++;
            }
            $maxScore += $marks;

            $processedAnswers[] = [
                'question' => $question['question'] ?? "Question " . ($index + 1),
                'user_answer' => $this->getOptionText($question, $userAnswer),
                'correct_answer' => $this->getOptionText($question, $correctAnswer),
                'is_correct' => $isCorrect,
                'marks' => $marks,
                'earned_marks' => $isCorrect ? $marks : 0,
                'options' => $this->extractOptions($question),
            ];
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;

        return [
            'answers' => $processedAnswers,
            'summary' => [
                'total_questions' => count($questions),
                'correct_answers' => $correctCount,
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $percentage,
            ]
        ];
    }

    protected function getOptionText($question, $optionKey): string
    {
        if (!$optionKey || !is_string($optionKey)) {
            return 'No answer';
        }

        // Handle options array format
        if (isset($question['options']) && is_array($question['options'])) {
            foreach ($question['options'] as $option) {
                if (($option['key'] ?? $option['id'] ?? '') === $optionKey) {
                    return $option['text'] ?? $option['value'] ?? $optionKey;
                }
            }
        }

        // Handle direct option fields (option_a, option_b, etc.)
        $optionField = 'option_' . strtolower($optionKey);
        if (isset($question[$optionField])) {
            return $question[$optionField];
        }

        return $optionKey;
    }

    protected function extractOptions($question): array
    {
        $options = [];

        if (isset($question['options']) && is_array($question['options'])) {
            return $question['options'];
        }

        foreach (['a', 'b', 'c', 'd'] as $key) {
            $optionKey = 'option_' . $key;
            if (isset($question[$optionKey]) && !empty($question[$optionKey])) {
                $options[] = [
                    'key' => $key,
                    'text' => $question[$optionKey],
                ];
            }
        }

        return $options;
    }
}
