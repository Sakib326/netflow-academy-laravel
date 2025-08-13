<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';
    protected static ?string $title = 'Course Lessons';
    protected static ?string $icon = 'heroicon-o-academic-cap';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Basic Information
                Forms\Components\Section::make('Lesson Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (!$get('slug') && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->columnSpan(1),

                        Forms\Components\Select::make('module_id')
                            ->label('Module')
                            ->relationship('module', 'title', fn (Builder $query) => 
                                $query->where('course_id', $this->ownerRecord->id)
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('type')
                            ->options([
                                'video' => 'Video',
                                'text' => 'Text',
                                'quiz' => 'Quiz',
                                'assignment' => 'Assignment',
                            ])
                            ->required()
                            ->default('video')
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('order_index')
                            ->label('Order')
                            ->numeric()
                            ->default(function () {
                                try {
                                    return $this->ownerRecord->lessons()->max('order_index') + 1;
                                } catch (\Exception $e) {
                                    return 1;
                                }
                            })
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_free')
                            ->label('Free Lesson')
                            ->default(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'locked' => 'Locked',
                            ])
                            ->default('draft')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                // Content Section
                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Lesson Content')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => ($get('type') ?? '') !== 'quiz'),

                        Forms\Components\TextInput::make('video_url')
                            ->label('Video URL')
                            ->url()
                            ->placeholder('https://youtube.com/watch?v=...')
                            ->visible(fn (Get $get) => ($get('type') ?? '') === 'video'),

                        Forms\Components\FileUpload::make('video_file')
                            ->label('Video File')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->maxSize(102400) // 100MB
                            ->directory('lessons/videos')
                            ->visible(fn (Get $get) => ($get('type') ?? '') === 'video'),

                        Forms\Components\TextInput::make('duration')
                            ->label('Duration (minutes)')
                            ->numeric()
                            ->placeholder('15')
                            ->visible(fn (Get $get) => ($get('type') ?? '') === 'video'),

                        Forms\Components\FileUpload::make('attachments')
                            ->label('Lesson Files')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                            ->maxSize(51200) // 50MB
                            ->directory('lessons/attachments')
                            ->visible(fn (Get $get) => in_array($get('type') ?? '', ['text', 'assignment'])),
                    ]),

                // Quiz Section
                Forms\Components\Section::make('Quiz Questions')
                    ->schema([
                        Forms\Components\Repeater::make('quiz_questions')
                            ->label('Questions')
                            ->schema([
                                Forms\Components\Textarea::make('question')
                                    ->label('Question')
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('points')
                                    ->label('Points')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Repeater::make('options')
                                    ->label('Answer Options')
                                    ->schema([
                                        Forms\Components\TextInput::make('text')
                                            ->label('Option')
                                            ->required()
                                            ->columnSpan(2),

                                        Forms\Components\Toggle::make('is_correct')
                                            ->label('Correct')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(4)
                                    ->minItems(2)
                                    ->maxItems(6)
                                    ->itemLabel(function (array $state): string {
                                        static $index = 0;
                                        $label = chr(65 + $index) . '. ' . ($state['text'] ?? 'Option');
                                        $index++;
                                        return $label;
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): string {
                                static $questionIndex = 0;
                                $questionIndex++;
                                return 'Question ' . $questionIndex . ': ' . Str::limit($state['question'] ?? 'New Question', 50);
                            })
                            ->collapsible()
                            ->cloneable()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get) => ($get('type') ?? '') === 'quiz'),

                // Assignment Section
                Forms\Components\Section::make('Assignment Details')
                    ->schema([
                        Forms\Components\RichEditor::make('assignment_instructions')
                            ->label('Instructions')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('assignment_files')
                            ->label('Assignment Files')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(51200) // 50MB
                            ->directory('lessons/assignments'),

                        Forms\Components\TextInput::make('max_score')
                            ->label('Maximum Score')
                            ->numeric()
                            ->default(100),

                        Forms\Components\DateTimePicker::make('due_date')
                            ->label('Due Date'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => ($get('type') ?? '') === 'assignment'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order_index')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('module.title')
                    ->label('Module')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'video' => 'info',
                        'text' => 'success',
                        'quiz' => 'warning',
                        'assignment' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'locked' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->suffix(' min')
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('has_attachments')
                    ->label('Files')
                    ->boolean()
                    ->getStateUsing(fn ($record) => 
                        !empty($record->video_file) || 
                        !empty($record->attachments) || 
                        !empty($record->assignment_files)
                    )
                    ->trueIcon('heroicon-o-paper-clip')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_index')
            ->reorderable('order_index')
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->relationship('module', 'title'),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'video' => 'Video',
                        'text' => 'Text',
                        'quiz' => 'Quiz',
                        'assignment' => 'Assignment',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'locked' => 'Locked',
                    ]),

                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Lessons'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Lesson')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['course_id'] = $this->ownerRecord->id;

                        if (!isset($data['order_index'])) {
                            $data['order_index'] = $this->ownerRecord->lessons()->max('order_index') + 1;
                        }

                        // Handle quiz data
                        if ($data['type'] === 'quiz' && isset($data['quiz_questions'])) {
                            $data['content'] = json_encode($data['quiz_questions']);
                            unset($data['quiz_questions']);
                        }

                        // Handle assignment instructions
                        if ($data['type'] === 'assignment' && isset($data['assignment_instructions'])) {
                            if (empty($data['content'])) {
                                $data['content'] = $data['assignment_instructions'];
                            }
                            unset($data['assignment_instructions']);
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Convert quiz content back to form format
                        if ($data['type'] === 'quiz' && !empty($data['content'])) {
                            try {
                                $quizData = is_string($data['content']) ? 
                                    json_decode($data['content'], true) : $data['content'];
                                
                                if (is_array($quizData)) {
                                    $data['quiz_questions'] = $quizData;
                                }
                            } catch (\Exception $e) {
                                $data['quiz_questions'] = [];
                            }
                        }

                        // Handle assignment instructions
                        if ($data['type'] === 'assignment') {
                            $data['assignment_instructions'] = $data['content'] ?? '';
                        }

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        // Handle quiz data formatting on save
                        if ($data['type'] === 'quiz' && isset($data['quiz_questions'])) {
                            $data['content'] = json_encode($data['quiz_questions']);
                            unset($data['quiz_questions']);
                        }

                        // Handle assignment instructions
                        if ($data['type'] === 'assignment' && isset($data['assignment_instructions'])) {
                            if (empty($data['content'])) {
                                $data['content'] = $data['assignment_instructions'];
                            }
                            unset($data['assignment_instructions']);
                        }

                        return $data;
                    }),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newLesson = $record->replicate();
                        $newLesson->title = $record->title . ' (Copy)';
                        $newLesson->slug = $record->slug . '-copy-' . time();
                        $newLesson->order_index = $this->ownerRecord->lessons()->max('order_index') + 1;
                        $newLesson->status = 'draft';
                        $newLesson->save();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'published']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('lock')
                        ->label('Lock Selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'locked']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First Lesson'),
            ]);
    }
}