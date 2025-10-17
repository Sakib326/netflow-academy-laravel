<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassRoutineResource\Pages;
use App\Models\ClassRoutine;
use App\Models\Course;
use App\Models\Batch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;

class ClassRoutineResource extends Resource
{
    protected static ?string $model = ClassRoutine::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Academics';
    protected static ?string $navigationLabel = 'Class Routines';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('course_id')
                                    ->label('Course')
                                    ->options(Course::query()->pluck('title', 'id'))
                                    ->searchable()
                                    ->required(),

                                Forms\Components\Select::make('batch_id')
                                    ->label('Batch')
                                    ->options(
                                        fn (Get $get) =>
                                        $get('course_id')
                                            ? Batch::where('course_id', $get('course_id'))->pluck('name', 'id')
                                            : Batch::pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->required()
                                    ->reactive(),
                            ]),

                        Forms\Components\Repeater::make('days')
                            ->label('Weekly Schedule')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('day')
                                            ->label('Day')
                                            ->options([
                                                'Monday' => 'Monday',
                                                'Tuesday' => 'Tuesday',
                                                'Wednesday' => 'Wednesday',
                                                'Thursday' => 'Thursday',
                                                'Friday' => 'Friday',
                                                'Saturday' => 'Saturday',
                                                'Sunday' => 'Sunday',
                                            ])
                                            ->required(),
                                        Forms\Components\TimePicker::make('start_time')
                                            ->label('Start')
                                            ->required(),
                                        Forms\Components\TimePicker::make('end_time')
                                            ->label('End')
                                            ->required(),
                                    ]),
                            ])
                            ->addActionLabel('Add')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('off_dates')
                            ->label('Special Off Dates')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('date')
                                            ->label('Date')
                                            ->required(),
                                        Forms\Components\TextInput::make('note')
                                            ->label('Note')
                                            ->maxLength(100)
                                            ->placeholder('e.g. Eid'),
                                    ]),
                            ])
                            ->addActionLabel('Add')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('batch.name')
                    ->label('Batch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('days')
                    ->label('Weekly')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state) && count($state)) {
                            return collect($state)
                                ->map(
                                    fn ($item) =>
                                    ($item['day'] ?? '-') . ' (' . ($item['start_time'] ?? '-') . '-' . ($item['end_time'] ?? '-') . ')'
                                )
                                ->implode(', ');
                        }
                        return '-';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('off_dates')
                    ->label('Off Dates')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state) && count($state)) {
                            return collect($state)
                                ->map(
                                    fn ($item) =>
                                    ($item['date'] ?? '-') . (isset($item['note']) ? ' [' . $item['note'] . ']' : '')
                                )
                                ->implode(', ');
                        }
                        return '-';
                    })
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(Course::pluck('title', 'id')),

                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(Batch::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassRoutines::route('/'),
            'create' => Pages\CreateClassRoutine::route('/create'),
            'view' => Pages\ViewClassRoutine::route('/{record}'),
            'edit' => Pages\EditClassRoutine::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
