<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZoomResource\Pages;
use App\Models\Zoom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ZoomResource extends Resource
{
    protected static ?string $model = Zoom::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Meetings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Meeting Details')
                    ->schema([
                        Forms\Components\TextInput::make('link')
                            ->label('Zoom Meeting Link')
                            ->required()
                            ->url()
                            ->maxLength(2048)
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('link')
                    ->label('Meeting Link')
                    ->limit(60)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Link copied to clipboard!'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZooms::route('/'),
            'create' => Pages\CreateZoom::route('/create'),
            'edit' => Pages\EditZoom::route('/{record}/edit'),
        ];
    }
}
