<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn; // Add this
use Filament\Tables\Filters\SelectFilter;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Course Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Course Information')->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Course::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\RichEditor::make('description')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3'
                            ]),

                        Forms\Components\Select::make('instructor_id')
                            ->label('Instructor')
                            ->relationship('instructor', 'name', fn (Builder $query) => $query->where('role', 'instructor'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('email')->email()->required(),
                                Forms\Components\TextInput::make('phone'),
                                Forms\Components\Hidden::make('role')->default('instructor'),
                            ]),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->unique(CourseCategory::class, 'slug'),
                            ]),
                            Select::make('course_type')
                                ->label('Course Type')
                                ->options([
                                    'single' => 'Single Course',
                                    'bundle' => 'Course Bundle',
                                ])
                                ->default('single')
                                ->reactive()
                                ->required(),

                            Select::make('bundle_courses')
                                ->label('Bundle Courses')
                                ->multiple()
                                ->options(fn () => Course::where('course_type', 'single')
                                    ->pluck('title', 'id'))
                                ->visible(fn (callable $get) => $get('course_type') === 'bundle')
                                ->helperText('Select courses to include in this bundle'),
                    ])->columns(2),

                    Forms\Components\Section::make('Media')->schema([
                        Forms\Components\FileUpload::make('thumbnail')
                            ->label('Course Thumbnail')
                            ->image()
                            ->directory('courses/thumbnails')
                            ->imageEditor()
                            ->imageCropAspectRatio('16:9')
                            ->maxSize(2048),

                        Forms\Components\TextInput::make('thumb_video_url')
                            ->label('Preview Video URL')
                            ->url()
                            ->placeholder('https://youtube.com/watch?v=...'),
                    ])->columns(2),

                    Forms\Components\Section::make('Pricing')->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->default(0.00)
                            ->prefix('$')
                            ->step(0.01)
                            ->live(),

                        Forms\Components\TextInput::make('discound_price')
                            ->label('Discount Price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->visible(fn (Get $get) => $get('price') > 0)
                            ->lt('price'),

                        Forms\Components\Toggle::make('is_free')
                            ->label('Free Course')
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?bool $state) {
                                if ($state) {
                                    $set('price', 0);
                                    $set('discound_price', null);
                                }
                            }),
                    ])->columns(3),
                ])->columnSpan(2),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Status')->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Course'),
                    ]),

                    Forms\Components\Section::make('Schedule')->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->after('start_date'),
                    ]),

                    Forms\Components\Section::make('SEO')->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->maxLength(60)
                            ->helperText('Recommended: 50-60 characters'),

                        Forms\Components\Textarea::make('meta_description')
                            ->maxLength(160)
                            ->helperText('Recommended: 150-160 characters'),

                        Forms\Components\TagsInput::make('meta_keywords')
                            ->separator(',')
                            ->placeholder('Enter keywords separated by commas'),
                    ])->collapsible(),
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->size(60)
                    ->defaultImageUrl('/images/course-placeholder.png'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => Str::limit($record->description, 50)),

                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->description(function ($record) {
                        if ($record->discound_price) {
                            return 'Was: $' . number_format($record->price, 2);
                        }
                        return null;
                    })
                    ->color(fn ($record) => $record->is_free ? 'success' : 'primary'),

                Tables\Columns\TextColumn::make('discound_price')
                    ->label('Discount')
                    ->money('USD')
                    ->placeholder('No discount')
                    ->toggleable(isToggledHiddenByDefault: true),
                BadgeColumn::make('course_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'single',
                        'success' => 'bundle',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('bundle_courses_count')
                    ->label('Bundle Courses')
                    ->getStateUsing(function ($record) {
                        if ($record->course_type === 'bundle' && $record->bundle_courses) {
                            // Remove json_decode since bundle_courses is already an array
                            return count($record->bundle_courses ?? []);
                        }
                        return null;
                    })
                    ->placeholder('N/A'),

                TextColumn::make('effective_price')
                    ->label('Effective Price')
                    ->money('USD')
                    ->getStateUsing(fn ($record) => $record->getEffectivePrice()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'archived' => 'danger',
                    }),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('instructor')
                    ->relationship('instructor', 'name'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Course'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('course_type')
                    ->label('Course Type')
                    ->options([
                        'single' => 'Single Course',
                        'bundle' => 'Course Bundle',
                    ]),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->numeric()
                            ->placeholder('Min price'),
                        Forms\Components\TextInput::make('price_to')
                            ->numeric()
                            ->placeholder('Max price'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('price', '<=', $price),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(function ($record) {
                            $newCourse = $record->replicate();
                            $newCourse->title = $record->title . ' (Copy)';
                            $newCourse->slug = $record->slug . '-copy-' . time();
                            $newCourse->status = 'draft';
                            $newCourse->save();
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['status' => 'published']))
                        ->deselectRecordsAfterCompletion()
                        ->color('success'),

                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->action(fn ($records) => $records->each->update(['status' => 'archived']))
                        ->deselectRecordsAfterCompletion()
                        ->color('danger'),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create First Course')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ModulesRelationManager::class,
            RelationManagers\BatchesRelationManager::class,
            //add from moduleresourse
            RelationManagers\LessonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
