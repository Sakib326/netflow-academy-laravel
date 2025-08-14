<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubmissionResource\Pages;
use App\Models\Submission;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Batch;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;

class SubmissionResource extends Resource
{
    protected static ?string $model = Submission::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Submissions';
    protected static ?string $pluralLabel = 'Submissions';
    protected static ?string $navigationGroup = 'Learning Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Submission Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Student')
                            ->relationship('user', 'name', fn($query) => $query->where('role', 'student'))
                            ->searchable()
                            ->required()
                            ->preload(),

                        Forms\Components\Select::make('lesson_id')
                            ->label('Lesson')
                            ->relationship('lesson', 'title', fn($query) => $query->whereIn('type', ['assignment', 'quiz']))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $lesson = Lesson::find($state);
                                    if ($lesson) {
                                        $set('type', $lesson->type);
                                        if ($lesson->type === 'assignment') {
                                            $set('max_score', $lesson->max_score ?? 100);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Select::make('type')
                            ->options([
                                'assignment' => 'Assignment',
                                'quiz' => 'Quiz',
                            ])
                            ->required()
                            ->live()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('submitted_at')
                            ->label('Submitted At')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2),

                // Assignment Content Section
                Forms\Components\Section::make('Assignment Submission')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Assignment Content')
                            ->placeholder('Student\'s assignment submission...')
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Assignment Files')
                            ->multiple()
                            ->directory('submissions/assignments')
                            ->maxSize(51200) // 50MB
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'assignment'),

                // Quiz Answers Section
                Forms\Components\Section::make('Quiz Answers')
                    ->schema([
                        Forms\Components\Repeater::make('quiz_answers')
                            ->label('Quiz Responses')
                            ->schema([
                                Forms\Components\Textarea::make('question')
                                    ->label('Question')
                                    ->disabled()
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('selected_answer')
                                    ->label('Student Answer')
                                    ->disabled()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('correct_answer')
                                    ->label('Correct Answer')
                                    ->disabled()
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_correct')
                                    ->label('Is Correct')
                                    ->disabled()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('points_earned')
                                    ->label('Points Earned')
                                    ->numeric()
                                    ->disabled()
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsed()
                            ->itemLabel(function (array $state): string {
                                $status = $state['is_correct'] ? '✅' : '❌';
                                return $status . ' Question: ' . substr($state['question'] ?? 'Unknown', 0, 50) . '...';
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'quiz'),

                // Grading Section
                Forms\Components\Section::make('Grading')
                    ->schema([
                        Forms\Components\TextInput::make('score')
                            ->label('Score')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('%')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $maxScore = $get('max_score') ?? 100;
                                $actualScore = ($state / 100) * $maxScore;
                                $set('actual_score', round($actualScore, 2));
                            }),

                        Forms\Components\TextInput::make('max_score')
                            ->label('Max Score')
                            ->numeric()
                            ->default(100)
                            ->disabled(fn (Forms\Get $get) => $get('type') === 'quiz'),

                        Forms\Components\TextInput::make('actual_score')
                            ->label('Actual Score')
                            ->numeric()
                            ->disabled()
                            ->helperText('Calculated based on percentage'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'submitted' => 'Submitted',
                                'graded' => 'Graded',
                                'pending' => 'Pending Review',
                                'late' => 'Late Submission',
                                'resubmit' => 'Needs Resubmission',
                            ])
                            ->default('submitted')
                            ->required()
                            ->live(),

                        Forms\Components\DateTimePicker::make('graded_at')
                            ->label('Graded At')
                            ->default(fn (Forms\Get $get) => $get('status') === 'graded' ? now() : null)
                            ->visible(fn (Forms\Get $get) => $get('status') === 'graded'),

                        Forms\Components\Select::make('graded_by')
                            ->label('Graded By')
                            ->relationship('grader', 'name', fn($query) => $query->whereIn('role', ['admin', 'instructor']))
                            ->searchable()
                            ->default(auth()->id())
                            ->visible(fn (Forms\Get $get) => $get('status') === 'graded'),

                        Forms\Components\RichEditor::make('feedback')
                            ->label('Feedback')
                            ->placeholder('Provide detailed feedback for the student...')
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('lesson.title')
                    ->label('Lesson')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->lesson?->title)
                    ->icon('heroicon-o-academic-cap'),

                Tables\Columns\TextColumn::make('lesson.module.course.title')
                    ->label('Course')
                    ->sortable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->lesson?->module?->course?->title)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'assignment' => 'primary',
                        'quiz' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'assignment' => 'heroicon-o-document-text',
                        'quiz' => 'heroicon-o-question-mark-circle',
                        default => 'heroicon-o-document',
                    }),

                Tables\Columns\TextColumn::make('score_display')
                    ->label('Score')
                    ->getStateUsing(function ($record) {
                        if ($record->score === null) return 'Not Graded';
                        $actualScore = $record->actual_score ?? (($record->score / 100) * ($record->max_score ?? 100));
                        return round($record->score, 1) . '% (' . round($actualScore, 1) . '/' . ($record->max_score ?? 100) . ')';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->score === null) return 'gray';
                        return match (true) {
                            $record->score >= 90 => 'success',
                            $record->score >= 80 => 'info',
                            $record->score >= 70 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->sortable('score'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'graded' => 'success',
                        'pending' => 'warning',
                        'late' => 'danger',
                        'resubmit' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'graded' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'late' => 'heroicon-o-exclamation-triangle',
                        'resubmit' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-document',
                    }),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('graded_at')
                    ->label('Graded')
                    ->dateTime('M d, H:i')
                    ->placeholder('Not graded')
                    ->sortable(),

                Tables\Columns\IconColumn::make('has_files')
                    ->label('Files')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->file_path))
                    ->trueIcon('heroicon-o-paper-clip')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                // Filter by Student
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name', fn($query) => $query->where('role', 'student'))
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Filter by Lesson
                Tables\Filters\SelectFilter::make('lesson_id')
                    ->label('Lesson')
                    ->relationship('lesson', 'title', fn($query) => $query->whereIn('type', ['assignment', 'quiz']))
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Filter by Course
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(
                        Course::query()->pluck('title', 'id')->toArray()
                    )
                    ->searchable()
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('lesson.module.course', function ($q) use ($data) {
                                $q->whereIn('courses.id', $data['values']);
                            });
                        }
                    }),

                // Filter by Batch
                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(
                        Batch::query()->pluck('name', 'id')->toArray()
                    )
                    ->searchable()
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('lesson.module.batch', function ($q) use ($data) {
                                $q->whereIn('batches.id', $data['values']);
                            });
                        }
                    }),

                // Filter by Type
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'assignment' => 'Assignment',
                        'quiz' => 'Quiz',
                    ]),

                // Filter by Status
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                        'pending' => 'Pending Review',
                        'late' => 'Late Submission',
                        'resubmit' => 'Needs Resubmission',
                    ])
                    ->multiple(),

                // Filter by Grade Range
                Tables\Filters\Filter::make('grade_range')
                    ->form([
                        Forms\Components\Select::make('grade_filter')
                            ->label('Grade Range')
                            ->options([
                                'excellent' => 'Excellent (90-100%)',
                                'good' => 'Good (80-89%)',
                                'average' => 'Average (70-79%)',
                                'below_average' => 'Below Average (<70%)',
                                'not_graded' => 'Not Graded',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['grade_filter'] ?? null) {
                            'excellent' => $query->whereBetween('score', [90, 100]),
                            'good' => $query->whereBetween('score', [80, 89]),
                            'average' => $query->whereBetween('score', [70, 79]),
                            'below_average' => $query->where('score', '<', 70),
                            'not_graded' => $query->whereNull('score'),
                            default => $query,
                        };
                    }),

                // Filter by Submission Date
                Tables\Filters\Filter::make('submitted_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Submitted From'),
                        Forms\Components\DatePicker::make('until')->label('Submitted Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->modalWidth(MaxWidth::FiveExtraLarge),

                    Tables\Actions\Action::make('grade')
                        ->label('Grade Submission')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->modalWidth(MaxWidth::ThreeExtraLarge)
                        ->form([
                            Forms\Components\TextInput::make('score')
                                ->label('Score (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),

                            Forms\Components\RichEditor::make('feedback')
                                ->label('Feedback')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'score' => $data['score'],
                                'feedback' => $data['feedback'],
                                'status' => 'graded',
                                'graded_at' => now(),
                                'graded_by' => auth()->id(),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Submission graded successfully')
                                ->send();
                        })
                        ->visible(fn ($record) => $record->status !== 'graded'),

                    Tables\Actions\Action::make('auto_grade_quiz')
                        ->label('Auto Grade Quiz')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->action(function ($record) {
                            if ($record->type === 'quiz') {
                                // Auto-grade quiz logic here
                                $this->autoGradeQuiz($record);
                                
                                Notification::make()
                                    ->success()
                                    ->title('Quiz auto-graded successfully')
                                    ->send();
                            }
                        })
                        ->visible(fn ($record) => $record->type === 'quiz' && $record->status !== 'graded'),

                    Tables\Actions\EditAction::make()
                        ->modalWidth(MaxWidth::FiveExtraLarge),

                    Tables\Actions\DeleteAction::make(),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_grade')
                        ->label('Bulk Grade')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('score')
                                ->label('Score (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),

                            Forms\Components\Textarea::make('feedback')
                                ->label('Feedback')
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'score' => $data['score'],
                                    'feedback' => $data['feedback'],
                                    'status' => 'graded',
                                    'graded_at' => now(),
                                    'graded_by' => auth()->id(),
                                ]);
                            });

                            Notification::make()
                                ->success()
                                ->title(count($records) . ' submissions graded successfully')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Submission'),
            ]);
    }

    protected function autoGradeQuiz($submission)
    {
        if (!$submission->quiz_answers || $submission->type !== 'quiz') {
            return;
        }

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($submission->quiz_answers as $answer) {
            $totalPoints += $answer['points'] ?? 1;
            if ($answer['is_correct'] ?? false) {
                $earnedPoints += $answer['points'] ?? 1;
            }
        }

        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

        $submission->update([
            'score' => round($percentage, 2),
            'max_score' => $totalPoints,
            'actual_score' => $earnedPoints,
            'status' => 'graded',
            'graded_at' => now(),
            'graded_by' => auth()->id(),
            'feedback' => "Auto-graded quiz result: {$earnedPoints}/{$totalPoints} points ({$percentage}%)",
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubmissions::route('/'),
            'create' => Pages\CreateSubmission::route('/create'),
            'edit' => Pages\EditSubmission::route('/{record}/edit'),
        ];
    }
}