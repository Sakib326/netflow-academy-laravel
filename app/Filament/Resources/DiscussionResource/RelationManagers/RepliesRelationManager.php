<?php

namespace App\Filament\Resources\DiscussionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;

class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';
    protected static ?string $title = 'Replies';
    protected static ?string $icon = 'heroicon-o-chat-bubble-left';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reply Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Author')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(auth()->id()),

                        Forms\Components\RichEditor::make('content')
                            ->label('Reply Content')
                            ->required()
                            ->placeholder('Write your reply here...')
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'bulletList', 'orderedList',
                                'blockquote', 'codeBlock'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('upvotes')
                            ->label('Upvotes')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('Author')
                        ->badge()
                        ->color('info')
                        ->size('sm'),
                    
                    Tables\Columns\TextColumn::make('content')
                        ->html()
                        ->limit(150)
                        ->wrap(),
                ])
                ->space(2),

                Tables\Columns\TextColumn::make('upvotes')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-heart')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->defaultSort('created_at')
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->label('Last 7 days'),

                Tables\Filters\Filter::make('popular')
                    ->query(fn (Builder $query): Builder => $query->where('upvotes', '>=', 3))
                    ->label('Popular replies'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Reply')
                    ->icon('heroicon-o-plus')
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->mutateFormDataUsing(function (array $data): array {
                        // Inherit parent discussion properties
                        $data['parent_id'] = $this->ownerRecord->id;
                        $data['discussable_type'] = $this->ownerRecord->discussable_type;
                        $data['discussable_id'] = $this->ownerRecord->discussable_id;
                        $data['is_question'] = false; // Replies are never questions
                        $data['is_answered'] = false;
                        
                        return $data;
                    })
                    ->after(function () {
                        Notification::make()
                            ->success()
                            ->title('Reply added successfully')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->modalWidth(MaxWidth::ThreeExtraLarge),

                    Tables\Actions\Action::make('upvote')
                        ->label('Upvote')
                        ->icon('heroicon-o-heart')
                        ->color('warning')
                        ->action(function ($record) {
                            $record->increment('upvotes');
                            
                            Notification::make()
                                ->success()
                                ->title('Reply upvoted!')
                                ->send();
                        }),

                    Tables\Actions\EditAction::make()
                        ->modalWidth(MaxWidth::ThreeExtraLarge),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Reply')
                        ->modalDescription('Are you sure you want to delete this reply?'),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_upvote')
                        ->label('Upvote Selected')
                        ->icon('heroicon-o-heart')
                        ->color('warning')
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each->increment('upvotes');
                            
                            Notification::make()
                                ->success()
                                ->title("{$count} replies upvoted")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First Reply')
                    ->icon('heroicon-o-plus'),
            ])
            ->emptyStateHeading('No replies yet')
            ->emptyStateDescription('Be the first to reply to this discussion.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left');
    }
}