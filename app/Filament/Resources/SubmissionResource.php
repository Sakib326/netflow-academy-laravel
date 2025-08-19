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
                // Basic Details - Compact Section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Student')
                                    ->relationship('user', 'name', fn ($query) => $query->where('role', 'student'))
                                    ->searchable()
                                    ->required()
                                    ->preload()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('lesson_id')
                                    ->label('Lesson')
                                    ->relationship('lesson', 'title', fn ($query) => $query->whereIn('type', ['assignment', 'quiz']))
                                    ->searchable()
                                    ->required()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $lesson = Lesson::find($state);
                                            if ($lesson) {
                                                $set('type', $lesson->type);
                                                $set('max_score', $lesson->getTotalMarks());
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('type')
                                    ->options([
                                        'assignment' => 'Assignment',
                                        'quiz' => 'Quiz',
                                    ])
                                    ->required()
                                    ->disabled()
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('submitted_at')
                                    ->label('Submitted At')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                        ]),
                    // ->compact(),

                // Assignment Content Section
                Forms\Components\Section::make('Assignment Content')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Submission')
                            ->placeholder('Student\'s assignment submission...')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('files')
                            ->label('Files')
                            ->multiple()
                            ->directory('submissions/assignments')
                            ->maxSize(51200)
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'assignment')
                    ->compact(),

                // Quiz Answers Section - Using Custom Component
                Forms\Components\Section::make('Quiz Results')
                    ->schema([
                        \App\Filament\Forms\Components\QuizAnswersView::make('quiz_results')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'quiz')
                    ->compact(),

                // Grading Section - Compact
                Forms\Components\Section::make('Grading')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('score')
                                    ->label('Score')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->suffix('pts'),

                                Forms\Components\TextInput::make('max_score')
                                    ->label('Max Score')
                                    ->numeric()
                                    ->default(100)
                                    ->disabled(fn (Forms\Get $get) => $get('type') === 'quiz'),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'graded' => 'Graded',
                                        'pending' => 'Pending',
                                        'late' => 'Late',
                                    ])
                                    ->default('pending')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('graded_at')
                                    ->label('Graded At')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'graded'),

                                Forms\Components\Select::make('graded_by')
                                    ->label('Graded By')
                                    ->relationship('grader', 'name', fn ($query) => $query->whereIn('role', ['admin', 'instructor']))
                                    ->searchable()
                                    ->default(auth()->id())
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'graded'),
                            ]),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->compact(),
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
                    ->size('sm'),

                Tables\Columns\TextColumn::make('lesson.title')
                    ->label('Lesson')
                    ->searchable()
                    ->limit(20)
                    ->size('sm'),

                Tables\Columns\TextColumn::make('lesson.module.course.title')
                    ->label('Course')
                    ->limit(15)
                    ->badge()
                    ->color('gray')
                    ->size('xs'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->size('xs')
                    ->color(fn ($state) => match ($state) {
                        'assignment' => 'blue',
                        'quiz' => 'yellow',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('score_display')
                    ->label('Score')
                    ->getStateUsing(function ($record) {
                        if ($record->score === null) {
                            return 'Not Graded';
                        }
                        return round($record->score, 1) . '/' . ($record->max_score ?? 100);
                    })
                    ->badge()
                    ->size('xs')
                    ->color(function ($record) {
                        if ($record->score === null) {
                            return 'gray';
                        }
                        $percentage = ($record->score / ($record->max_score ?? 100)) * 100;
                        return match (true) {
                            $percentage >= 90 => 'green',
                            $percentage >= 80 => 'blue',
                            $percentage >= 70 => 'yellow',
                            default => 'red',
                        };
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->size('xs')
                    ->color(fn ($state) => match ($state) {
                        'graded' => 'green',
                        'pending' => 'yellow',
                        'late' => 'red',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->size('xs'),

                Tables\Columns\TextColumn::make('graded_at')
                    ->label('Graded')
                    ->dateTime('M d, H:i')
                    ->placeholder('-')
                    ->size('xs'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->striped()
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name', fn ($query) => $query->where('role', 'student'))
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('lesson_id')
                    ->label('Lesson')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'assignment' => 'Assignment',
                        'quiz' => 'Quiz',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'graded' => 'Graded',
                        'pending' => 'Pending',
                        'late' => 'Late',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('grade_range')
                    ->form([
                        Forms\Components\Select::make('range')
                            ->options([
                                'excellent' => '90-100%',
                                'good' => '80-89%',
                                'average' => '70-79%',
                                'poor' => '<70%',
                                'ungraded' => 'Ungraded',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['range'] ?? null) {
                            'excellent' => $query->whereRaw('(score / max_score * 100) >= 90'),
                            'good' => $query->whereRaw('(score / max_score * 100) BETWEEN 80 AND 89'),
                            'average' => $query->whereRaw('(score / max_score * 100) BETWEEN 70 AND 79'),
                            'poor' => $query->whereRaw('(score / max_score * 100) < 70'),
                            'ungraded' => $query->whereNull('score'),
                            default => $query,
                        };
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('grade')
                    ->label('Grade')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->size('xs')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('score')
                                    ->label('Score')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),

                                Forms\Components\TextInput::make('max_score')
                                    ->label('Max Score')
                                    ->numeric()
                                    ->default(fn ($record) => $record->max_score ?? 100)
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'score' => $data['score'],
                            'max_score' => $data['max_score'],
                            'feedback' => $data['feedback'],
                            'status' => 'graded',
                            'graded_at' => now(),
                            'graded_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Submission graded')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status !== 'graded'),

                Tables\Actions\Action::make('auto_grade')
                    ->label('Auto Grade')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->size('xs')
                    ->action(function ($record) {
                        if ($record->type === 'quiz' && method_exists($record, 'autoGradeQuiz')) {
                            $record->autoGradeQuiz();

                            Notification::make()
                                ->success()
                                ->title('Quiz auto-graded')
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->type === 'quiz' && $record->status !== 'graded'),

                Tables\Actions\ViewAction::make()
                    ->size('xs')
                    ->modalWidth('4xl'),

                Tables\Actions\EditAction::make()
                    ->size('xs')
                    ->modalWidth('4xl'),

                Tables\Actions\DeleteAction::make()
                    ->size('xs'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_grade')
                        ->label('Bulk Grade')
                        ->icon('heroicon-o-star')
                        ->form([
                            Forms\Components\TextInput::make('score')
                                ->label('Score')
                                ->numeric()
                                ->required(),
                            Forms\Components\TextInput::make('max_score')
                                ->label('Max Score')
                                ->numeric()
                                ->default(100),
                            Forms\Components\Textarea::make('feedback')
                                ->label('Feedback'),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->update([
                                    'score' => $data['score'],
                                    'max_score' => $data['max_score'],
                                    'feedback' => $data['feedback'],
                                    'status' => 'graded',
                                    'graded_at' => now(),
                                    'graded_by' => auth()->id(),
                                ]);
                            }

                            Notification::make()
                                ->success()
                                ->title(count($records) . ' submissions graded')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
