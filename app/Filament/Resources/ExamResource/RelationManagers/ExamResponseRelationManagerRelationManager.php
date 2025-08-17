<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ExamResponseRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'Exam Responses';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(),

                Forms\Components\Select::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name')
                    ->required()
                    ->disabled(),

                Forms\Components\TextInput::make('score')
                    ->label('Score')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0),

                Forms\Components\TextInput::make('max_score')
                    ->label('Max Score')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0),

                Forms\Components\TextInput::make('percentage')
                    ->label('Percentage')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),

                Forms\Components\TextInput::make('total_time_taken')
                    ->label('Time Taken')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('minutes'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                        'auto_failed' => 'Auto Failed',
                    ])
                    ->required(),

                Forms\Components\DateTimePicker::make('started_at')
                    ->label('Started At')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('submitted_at')
                    ->label('Submitted At'),

                Forms\Components\DateTimePicker::make('graded_at')
                    ->label('Graded At'),

                Forms\Components\Section::make('Student Answers')
                    ->schema([
                        Forms\Components\Placeholder::make('answers_preview')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->exam || !$record->content) {
                                    return 'No answers available';
                                }

                                $exam = $record->exam;
                                $examQuestions = $exam->content ?? [];
                                $userAnswers = $record->content ?? [];

                                // Create a map of user answers by question_id
                                $userAnswerMap = [];
                                foreach ($userAnswers as $answer) {
                                    $userAnswerMap[$answer['question_id']] = $answer['selected'];
                                }

                                $html = '<div class="space-y-4">';
                                foreach ($examQuestions as $index => $question) {
                                    $userSelectedIndex = $userAnswerMap[$index] ?? null;
                                    $correctIndex = $question['answer'];
                                    $isCorrect = $userSelectedIndex === $correctIndex;

                                    $userSelectedText = $userSelectedIndex !== null && isset($question['options'][$userSelectedIndex]) 
                                        ? $question['options'][$userSelectedIndex] 
                                        : 'No answer selected';
                                    
                                    $correctAnswerText = isset($question['options'][$correctIndex]) 
                                        ? $question['options'][$correctIndex] 
                                        : 'N/A';

                                    $statusColor = $isCorrect ? 'text-green-600' : 'text-red-600';
                                    $statusIcon = $isCorrect ? '✅' : '❌';

                                    $html .= '
                                        <div class="p-4 border rounded-lg bg-gray-50">
                                            <div class="flex items-start justify-between mb-2">
                                                <strong>Q' . ($index + 1) . ':</strong>
                                                <span class="' . $statusColor . '">' . $statusIcon . '</span>
                                            </div>
                                            <p class="mb-3 text-gray-800">' . htmlspecialchars($question['question']) . '</p>
                                            <div class="grid grid-cols-1 gap-2 text-sm">
                                                <div><strong>Student Answer:</strong> <span class="' . ($isCorrect ? 'text-green-600' : 'text-red-600') . '">' . htmlspecialchars($userSelectedText) . '</span></div>
                                                <div><strong>Correct Answer:</strong> <span class="text-green-600">' . htmlspecialchars($correctAnswerText) . '</span></div>
                                            </div>
                                        </div>
                                    ';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch.name')
                    ->label('Batch')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->getStateUsing(fn ($record) => $record->score . '/' . $record->max_score)
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percentage')
                    ->label('Percentage')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->color(fn ($state) => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('total_time_taken')
                    ->label('Time Taken')
                    ->suffix(' min')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'in_progress',
                        'warning' => 'submitted',
                        'success' => 'graded',
                        'danger' => 'auto_failed',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('graded_at')
                    ->label('Graded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                        'auto_failed' => 'Auto Failed',
                    ]),

                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name'),

                Tables\Filters\Filter::make('percentage_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('percentage_from')
                                    ->label('Percentage From')
                                    ->numeric()
                                    ->placeholder('0'),
                                Forms\Components\TextInput::make('percentage_to')
                                    ->label('Percentage To')
                                    ->numeric()
                                    ->placeholder('100'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['percentage_from'],
                                fn (Builder $query, $percentage): Builder => $query->where('percentage', '>=', $percentage),
                            )
                            ->when(
                                $data['percentage_to'],
                                fn (Builder $query, $percentage): Builder => $query->where('percentage', '<=', $percentage),
                            );
                    }),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(), // Disable creating responses manually
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Exam Details - ' . $record->user->name)
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        if (!$record->exam || !$record->content) {
                            return new HtmlString('<p>No exam data available</p>');
                        }

                        $exam = $record->exam;
                        $examQuestions = $exam->content ?? [];
                        $userAnswers = $record->content ?? [];

                        // Create map of user answers
                        $userAnswerMap = [];
                        foreach ($userAnswers as $answer) {
                            $userAnswerMap[$answer['question_id']] = $answer['selected'];
                        }

                        $html = '
                        <div class="space-y-6">
                            <div class="grid grid-cols-4 gap-4 p-4 bg-blue-50 rounded-lg">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">' . $record->score . '/' . $record->max_score . '</div>
                                    <div class="text-sm text-gray-600">Score</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">' . number_format($record->percentage, 1) . '%</div>
                                    <div class="text-sm text-gray-600">Percentage</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">' . $record->total_time_taken . '</div>
                                    <div class="text-sm text-gray-600">Minutes</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">' . ucfirst($record->status) . '</div>
                                    <div class="text-sm text-gray-600">Status</div>
                                </div>
                            </div>

                            <div class="space-y-4">
                        ';

                        foreach ($examQuestions as $index => $question) {
                            $userSelectedIndex = $userAnswerMap[$index] ?? null;
                            $correctIndex = $question['answer'];
                            $isCorrect = $userSelectedIndex === $correctIndex;

                            $html .= '
                                <div class="border rounded-lg overflow-hidden">
                                    <div class="p-4 bg-gray-50 border-b flex justify-between items-start">
                                        <h3 class="font-semibold text-lg">Question ' . ($index + 1) . '</h3>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium ' . 
                                        ($isCorrect ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">
                                            ' . ($isCorrect ? '✅ Correct' : '❌ Incorrect') . '
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <p class="mb-4 text-gray-800 font-medium">' . htmlspecialchars($question['question']) . '</p>
                                        
                                        <div class="space-y-2">
                            ';

                            // Display all options
                            foreach ($question['options'] as $optionIndex => $option) {
                                $isUserChoice = $userSelectedIndex === $optionIndex;
                                $isCorrectChoice = $correctIndex === $optionIndex;
                                
                                $optionClass = 'p-3 border rounded-md';
                                $labelClass = '';
                                $icon = '';

                                if ($isCorrectChoice) {
                                    $optionClass .= ' bg-green-50 border-green-200';
                                    $labelClass = 'text-green-800 font-medium';
                                    $icon = '✅ ';
                                } elseif ($isUserChoice && !$isCorrectChoice) {
                                    $optionClass .= ' bg-red-50 border-red-200';
                                    $labelClass = 'text-red-800 font-medium';
                                    $icon = '❌ ';
                                } else {
                                    $optionClass .= ' bg-gray-50';
                                    $labelClass = 'text-gray-700';
                                }

                                $html .= '
                                    <div class="' . $optionClass . '">
                                        <span class="' . $labelClass . '">' . $icon . htmlspecialchars($option) . '</span>
                                ';

                                if ($isUserChoice) {
                                    $html .= '<span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Your Answer</span>';
                                }
                                if ($isCorrectChoice) {
                                    $html .= '<span class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Correct Answer</span>';
                                }

                                $html .= '</div>';
                            }

                            $html .= '
                                        </div>
                                    </div>
                                </div>
                            ';
                        }

                        $html .= '
                            </div>
                        </div>
                        ';

                        return new HtmlString($html);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}