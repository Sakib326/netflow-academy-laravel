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
    protected static ?string $title = 'Lessons';
    protected static ?string $icon = 'heroicon-o-academic-cap';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Basic Info Section
                Forms\Components\Section::make('Basic Info')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if (!$get('slug') && $state) {
                                            $set('slug', Str::slug($state));
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('order_index')
                                    ->label('Order')
                                    ->numeric()
                                    ->default(fn () => $this->ownerRecord->lessons()->max('lessons.order_index') + 1)
                                    ->required(),

                                Forms\Components\TextInput::make('slug')
                                    ->unique(ignoreRecord: true)
                                    ->required()
                                    ->alphaDash()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('type')
                                    ->options([
                                        'video' => 'Video',
                                        'text' => 'Text',
                                        'quiz' => 'Quiz',
                                        'assignment' => 'Assignment',
                                    ])
                                    ->default('video')
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('module_id')
                                    ->label('Module')
                                    ->relationship(
                                        'module',
                                        'title',
                                        fn (Builder $query) =>
                                        $query->where('course_id', $this->ownerRecord->id)
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'published' => 'Published',
                                        'locked' => 'Locked',
                                    ])
                                    ->default('draft')
                                    ->required(),

                                Forms\Components\Toggle::make('is_free')
                                    ->label('Free Lesson')
                                    ->inline(false)
                                    ->default(false),
                            ]),
                    ])
                    ->compact(),

                // Content Section
                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->visible(fn (Get $get) => in_array($get('type'), ['text', 'video']))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get) => in_array($get('type'), ['text', 'video']))
                    ->compact(),

                // Quiz Section
                Forms\Components\Section::make('Quiz Questions')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->schema([
                                Forms\Components\TextInput::make('question')
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('option_a')
                                            ->label('Option A')
                                            ->required(),
                                        Forms\Components\TextInput::make('option_b')
                                            ->label('Option B')
                                            ->required(),
                                        Forms\Components\TextInput::make('option_c')
                                            ->label('Option C'),
                                        Forms\Components\TextInput::make('option_d')
                                            ->label('Option D'),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('correct_answer')
                                            ->options([
                                                'a' => 'A',
                                                'b' => 'B',
                                                'c' => 'C',
                                                'd' => 'D',
                                            ])
                                            ->required(),
                                        Forms\Components\TextInput::make('marks')
                                            ->numeric()
                                            ->default(1)
                                            ->required(),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Question')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? 'New Question'),
                    ])
                    ->visible(fn (Get $get) => $get('type') === 'quiz')
                    ->compact(),

                // Assignment Section
                Forms\Components\Section::make('Assignment Details')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Instructions')
                            ->toolbarButtons(['bold', 'italic', 'bulletList'])
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('max_score')
                                    ->label('Max Score')
                                    ->numeric()
                                    ->default(100),
                                Forms\Components\DateTimePicker::make('available_until')
                                    ->label('Due Date'),
                            ]),
                    ])
                    ->visible(fn (Get $get) => $get('type') === 'assignment')
                    ->compact(),

                Forms\Components\Section::make('Files')
    ->schema([
        Forms\Components\Repeater::make('files')
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('File Name')
                            ->required(),
                        Forms\Components\Select::make('type')  // ← NEW: Choose upload or URL
                            ->label('Type')
                            ->options([
                                'upload' => 'Upload File',
                                'url' => 'External URL',
                            ])
                            ->default('upload')
                            ->live()
                            ->required(),
                    ]),
                Forms\Components\FileUpload::make('file')  // ← NEW: File upload field
                    ->label('Upload File')
                    ->directory('lessons/files')
                    ->preserveFilenames()
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file) => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension()
                    )
                    ->maxSize(10240) // 10MB
                    ->visible(fn (Get $get) => $get('type') === 'upload'),
                Forms\Components\TextInput::make('url')  // ← EXISTING: URL field
                    ->label('File URL')
                    ->url()
                    ->visible(fn (Get $get) => $get('type') === 'url')
                    ->required(fn (Get $get) => $get('type') === 'url'),
            ])
    ])
                    ->visible(fn (Get $get) => in_array($get('type'), ['text', 'assignment','video']))
                    ->compact(),
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
                    ->size('sm')
                    ->width(50),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('module.title')
                    ->badge()
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'video' => 'success',
                        'text' => 'info',
                        'quiz' => 'warning',
                        'assignment' => 'danger',
                        default => 'gray',
                    })
                    ->size('sm'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'locked' => 'danger',
                        default => 'gray',
                    })
                    ->size('sm'),

                Tables\Columns\IconColumn::make('is_free')
                    ->boolean()
                    ->size('sm'),
            ])
            ->defaultSort('order_index')
            ->reorderable('order_index')
            ->filters([
                Tables\Filters\SelectFilter::make('module_id')
                    ->label('Module')
                    ->relationship('module', 'title'),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'video' => 'Video',
                        'text' => 'Text',
                        'quiz' => 'Quiz',
                        'assignment' => 'Assignment'
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'locked' => 'Locked'
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Lesson')
                    ->icon('heroicon-o-plus')
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->size('sm'),

                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->size('sm')
                    ->action(function ($record) {
                        $newLesson = $record->replicate();
                        $newLesson->fill([
                            'title' => $record->title . ' (Copy)',
                            'slug' => $record->slug . '-copy-' . time(),
                            'order_index' => $this->ownerRecord->lessons()->max('order_index') + 1,
                            'status' => 'draft'
                        ]);
                        $newLesson->save();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No lessons yet')
            ->emptyStateDescription('Create your first lesson to get started.')
            ->emptyStateIcon('heroicon-o-academic-cap');
    }
}
