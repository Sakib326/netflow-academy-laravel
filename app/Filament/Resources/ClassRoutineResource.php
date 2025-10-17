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
use Filament\Forms\Set;

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
                Section::make('Routine Details')
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
                                    ->reactive()
                                    ->helperText('Select course first for filtered batches'),
                            ]),

                        Forms\Components\CheckboxList::make('days')
                            ->label('Days of the Week')
                            ->options([
                                'Monday' => 'Monday',
                                'Tuesday' => 'Tuesday',
                                'Wednesday' => 'Wednesday',
                                'Thursday' => 'Thursday',
                                'Friday' => 'Friday',
                                'Saturday' => 'Saturday',
                                'Sunday' => 'Sunday',
                            ])
                            ->columns(4)
                            ->required()
                            ->helperText('Select all days when this class occurs.'),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->required(),
                            ]),

                        Forms\Components\Repeater::make('off_dates')
                            ->label('Special Off Dates')
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->label('Off Date')
                                    ->required(),
                            ])
                            ->addActionLabel('Add Off Date')
                            ->helperText('Add any special dates when class will not be held.')
                            ->columnSpanFull(),
                    ]),
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
                    ->label('Days')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->sortable(),

                Tables\Columns\TextColumn::make('off_dates')
                    ->label('Off Dates')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state) && count($state)) {
                            // Support both array of dates or array of ['date' => ...]
                            $dates = [];
                            foreach ($state as $item) {
                                $dates[] = is_array($item) && isset($item['date']) ? $item['date'] : $item;
                            }
                            return implode(', ', $dates);
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

                Tables\Filters\SelectFilter::make('days')
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
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereJsonContains('days', $data['value']);
                        }
                    }),
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
        return [
            // Add relation managers if needed
        ];
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
