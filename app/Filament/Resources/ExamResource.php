<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers;
use App\Models\Exam;
use App\Models\Course;
use App\Models\Batch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Learning Management';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('batch_id', null)),

                        Forms\Components\Select::make('batch_id')
                            ->label('Batch')
                            ->options(function (callable $get) {
                                $courseId = $get('course_id');
                                if (!$courseId) {
                                    return [];
                                }
                                return Batch::where('course_id', $courseId)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('PHP Basics MCQ'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('total_time')
                                    ->label('Duration (min)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->default(30),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('draft')
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Brief exam description...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Repeater::make('content')
                    ->label('Questions')
                    ->schema([
                        Forms\Components\Textarea::make('question')
                            ->label('Question')
                            ->required()
                            ->rows(2)
                            ->placeholder('What does PHP stand for?')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TagsInput::make('options')
                                    ->label('Options (Press Enter after each option)')
                                    ->placeholder('Type option and press Enter')
                                    ->required(),

                                Forms\Components\Select::make('answer')
                                    ->label('Correct Answer Index (0-based)')
                                    ->options([
                                        0 => '1st Option (0)',
                                        1 => '2nd Option (1)', 
                                        2 => '3rd Option (2)',
                                        3 => '4th Option (3)',
                                        4 => '5th Option (4)',
                                        5 => '6th Option (5)',
                                    ])
                                    ->required(),
                            ]),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('+ Question')
                    ->collapsible()
                    ->cloneable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('course.title')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('batch.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_time')
                    ->label('Duration')
                    ->sortable()
                    ->suffix(' min')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Questions')
                    ->getStateUsing(fn ($record) => count($record->content ?? []))
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'active', 
                        'danger' => 'inactive',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'title')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make() // <-- Add this line for the Clone button
                    ->label('Clone')
                    ->color('info')
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->status = 'draft';
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ExamResponseRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}