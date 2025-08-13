<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscussionResource\Pages;
use App\Filament\Resources\DiscussionResource\RelationManagers;
use App\Models\Discussion;
use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;

class DiscussionResource extends Resource
{
    protected static ?string $model = Discussion::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Learning Management';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Discussion Details')
                    ->description('Basic information about the discussion')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Author')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(auth()->id()),

                                Forms\Components\Select::make('parent_id')
                                    ->label('Reply To')
                                    ->relationship('parent', 'title')
                                    ->searchable()
                                    ->placeholder('Select if this is a reply')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($state) {
                                            $set('is_question', false);
                                        }
                                    }),
                            ]),

                        Forms\Components\TextInput::make('title')
                            ->label('Discussion Title')
                            ->maxLength(255)
                            ->placeholder('Enter discussion title...')
                            ->required(fn (Get $get) => !$get('parent_id'))
                            ->visible(fn (Get $get) => !$get('parent_id'))
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_question')
                                    ->label('Mark as Question')
                                    ->helperText('This discussion is asking for help')
                                    ->disabled(fn (Get $get) => $get('parent_id')),

                                Forms\Components\Toggle::make('is_answered')
                                    ->label('Mark as Answered')
                                    ->helperText('Question has been resolved')
                                    ->visible(fn (Get $get) => $get('is_question')),
                            ]),

                        Forms\Components\RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->placeholder('Write your discussion content here...')
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'bulletList', 'orderedList',
                                'h2', 'h3', 'blockquote', 'codeBlock'
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Attach to Content')
                    ->description('Link this discussion to specific course content')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Forms\Components\Select::make('discussable_type')
                            ->label('Discussion Type')
                            ->options([
                                'App\\Models\\Course' => 'Course Discussion',
                                'App\\Models\\Lesson' => 'Lesson Discussion',
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('discussable_id', null)),

                        Forms\Components\Select::make('discussable_id')
                            ->label('Select Item')
                            ->options(function (Get $get) {
                                $type = $get('discussable_type');
                                if ($type === 'App\\Models\\Course') {
                                    return Course::pluck('title', 'id')->toArray();
                                } elseif ($type === 'App\\Models\\Lesson') {
                                    return Lesson::with('course')
                                        ->get()
                                        ->mapWithKeys(fn ($lesson) => [
                                            $lesson->id => "{$lesson->course->title} - {$lesson->title}"
                                        ])
                                        ->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->visible(fn (Get $get) => filled($get('discussable_type'))),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Engagement')
                    ->description('Discussion metrics and engagement')
                    ->icon('heroicon-o-heart')
                    ->schema([
                        Forms\Components\TextInput::make('upvotes')
                            ->label('Upvotes')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('title')
                        ->searchable()
                        ->sortable()
                        ->weight('bold')
                        ->placeholder('(Reply)')
                        ->limit(60),
                    
                    Tables\Columns\TextColumn::make('content')
                        ->html()
                        ->limit(100)
                        ->color('gray')
                        ->size('sm'),
                ])
                ->space(1),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('discussable_type')
                        ->label('Type')
                        ->formatStateUsing(fn (string $state): string => match($state) {
                            'App\\Models\\Course' => 'Course',
                            'App\\Models\\Lesson' => 'Lesson',
                            default => 'General'
                        })
                        ->badge()
                        ->color(fn (string $state): string => match($state) {
                            'App\\Models\\Course' => 'success',
                            'App\\Models\\Lesson' => 'warning',
                            default => 'gray'
                        }),

                    Tables\Columns\TextColumn::make('discussable.title')
                        ->label('Related Content')
                        ->limit(30)
                        ->placeholder('No attachment')
                        ->color('gray')
                        ->size('sm'),
                ])
                ->space(1),

                Tables\Columns\Layout\Grid::make([
                    Tables\Columns\IconColumn::make('is_question')
                        ->label('Question')
                        ->boolean()
                        ->trueIcon('heroicon-o-question-mark-circle')
                        ->trueColor('warning')
                        ->falseIcon('')
                        ->tooltip('This is a question'),

                    Tables\Columns\IconColumn::make('is_answered')
                        ->label('Answered')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->trueColor('success')
                        ->falseIcon('heroicon-o-clock')
                        ->falseColor('gray')
                        ->visible(fn ($record) => $record->is_question)
                        ->tooltip(fn ($record) => $record->is_answered ? 'Answered' : 'Awaiting answer'),

                    Tables\Columns\IconColumn::make('parent_id')
                        ->label('Reply')
                        ->boolean(fn ($record) => $record->parent_id !== null)
                        ->trueIcon('heroicon-o-arrow-uturn-right')
                        ->trueColor('info')
                        ->falseIcon('')
                        ->tooltip('This is a reply'),
                ])
                ->columns(3),

                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('upvotes')
                        ->label('Upvotes')
                        ->badge()
                        ->color('success')
                        ->icon('heroicon-o-heart')
                        ->getStateUsing(fn ($record) => $record ? ($record->upvotes ?? 0) : 0),
                    Tables\Columns\TextColumn::make('replies_count')
                        ->label('Replies')
                        ->counts('replies')
                        ->badge()
                        ->color('info')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->getStateUsing(fn ($record) => $record ? $record->replies_count : 0),
                ])
                ->space(1),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('discussable_type')
                    ->label('Content Type')
                    ->options([
                        'App\\Models\\Course' => 'Course Discussions',
                        'App\\Models\\Lesson' => 'Lesson Discussions',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_question')
                    ->label('Questions Only')
                    ->placeholder('All discussions')
                    ->trueLabel('Questions only')
                    ->falseLabel('Exclude questions'),

                Tables\Filters\TernaryFilter::make('is_answered')
                    ->label('Answered Questions')
                    ->placeholder('All questions')
                    ->trueLabel('Answered only')
                    ->falseLabel('Unanswered only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_question', true)->where('is_answered', true),
                        false: fn (Builder $query) => $query->where('is_question', true)->where('is_answered', false),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\Filter::make('has_replies')
                    ->query(fn (Builder $query): Builder => $query->whereHas('replies'))
                    ->label('Has Replies'),

                Tables\Filters\Filter::make('popular')
                    ->query(fn (Builder $query): Builder => $query->where('upvotes', '>=', 5))
                    ->label('Popular (5+ upvotes)'),

                Tables\Filters\Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->label('Last 7 days'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Start Discussion')
                    ->icon('heroicon-o-plus')
                    ->modalWidth(MaxWidth::FourExtraLarge),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->modalWidth(MaxWidth::FiveExtraLarge)
                        ->modalContent(function ($record) {
                            return view('filament.modals.discussion-view', ['discussion' => $record]);
                        }),

                    Tables\Actions\Action::make('reply')
                        ->label('Reply')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->color('info')
                        ->form([
                            Forms\Components\RichEditor::make('content')
                                ->label('Your Reply')
                                ->required()
                                ->placeholder('Write your reply...')
                                ->toolbarButtons([
                                    'bold', 'italic', 'link', 'bulletList', 'orderedList',
                                    'blockquote', 'codeBlock'
                                ]),
                        ])
                        ->action(function (array $data, $record) {
                            Discussion::create([
                                'user_id' => auth()->id(),
                                'parent_id' => $record->id,
                                'content' => $data['content'],
                                'discussable_type' => $record->discussable_type,
                                'discussable_id' => $record->discussable_id,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Reply posted')
                                ->send();
                        })
                        ->modalWidth(MaxWidth::ThreeExtraLarge),

                    Tables\Actions\Action::make('mark_answered')
                        ->label('Mark as Answered')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->is_question && !$record->is_answered)
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['is_answered' => true]);
                            
                            Notification::make()
                                ->success()
                                ->title('Question marked as answered')
                                ->send();
                        }),

                    Tables\Actions\Action::make('upvote')
                        ->label('Upvote')
                        ->icon('heroicon-o-heart')
                        ->color('warning')
                        ->action(function ($record) {
                            $record->increment('upvotes');
                            
                            Notification::make()
                                ->success()
                                ->title('Upvoted!')
                                ->send();
                        }),

                    Tables\Actions\EditAction::make()
                        ->modalWidth(MaxWidth::FourExtraLarge),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Discussion')
                        ->modalDescription('Are you sure you want to delete this discussion? This will also delete all replies.')
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_answered')
                        ->label('Mark as Answered')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->where('is_question', true)->count();
                            $records->where('is_question', true)->each->update(['is_answered' => true]);
                            
                            Notification::make()
                                ->success()
                                ->title("{$count} questions marked as answered")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulk_upvote')
                        ->label('Bulk Upvote')
                        ->icon('heroicon-o-heart')
                        ->color('warning')
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->increment('upvotes');
                            
                            Notification::make()
                                ->success()
                                ->title("{$count} discussions upvoted")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Discussions')
                        ->modalDescription('Are you sure you want to delete the selected discussions? This will also delete all replies.'),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Start First Discussion')
                    ->icon('heroicon-o-plus'),
            ])
            ->emptyStateHeading('No discussions yet')
            ->emptyStateDescription('Start the conversation by creating your first discussion.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->striped()
            ->paginated([10, 25, 50])
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RepliesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscussions::route('/'),
            'create' => Pages\CreateDiscussion::route('/create'),
            'edit' => Pages\EditDiscussion::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_question', true)->where('is_answered', false)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() > 0 ? 'warning' : 'success';
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user', 'discussable']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'content', 'user.name'];
    }
}