<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Resources\CouponResource\RelationManagers;
use App\Models\Coupon;
use App\Models\Course;
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
use Filament\Support\Colors\Color;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Sales & Marketing';

    protected static ?string $navigationLabel = 'Coupons';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Coupon Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., SAVE20, WELCOME50')
                                    ->helperText('Unique code that customers will enter')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', strtoupper($state ?? ''))),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Toggle to enable/disable this coupon'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Brief description of this coupon...')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Discount Configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Discount Type')
                                    ->required()
                                    ->options([
                                        'fixed' => 'Fixed Amount',
                                        'percentage' => 'Percentage',
                                    ])
                                    ->default('percentage')
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('value', null)),

                                Forms\Components\TextInput::make('value')
                                    ->label(fn (Get $get): string => match ($get('type')) {
                                        'fixed' => 'Discount Amount (৳)',
                                        'percentage' => 'Discount Percentage (%)',
                                        default => 'Discount Value',
                                    })
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get): int => $get('type') === 'percentage' ? 100 : 999999)
                                    ->suffix(fn (Get $get): string => match ($get('type')) {
                                        'fixed' => '৳',
                                        'percentage' => '%',
                                        default => '',
                                    })
                                    ->placeholder(fn (Get $get): string => match ($get('type')) {
                                        'fixed' => 'e.g., 100',
                                        'percentage' => 'e.g., 20',
                                        default => 'Enter value',
                                    }),

                                Forms\Components\TextInput::make('minimum_amount')
                                    ->label('Minimum Order Amount (৳)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('৳')
                                    ->placeholder('e.g., 500')
                                    ->helperText('Minimum cart value required to use this coupon'),
                            ]),
                    ]),

                Section::make('Usage Limits')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('usage_limit')
                                    ->label('Total Usage Limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('e.g., 100')
                                    ->helperText('Maximum number of times this coupon can be used (leave empty for unlimited)'),

                                Forms\Components\TextInput::make('used_count')
                                    ->label('Times Used')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Number of times this coupon has been used'),
                            ]),
                    ]),

                Section::make('Validity Period')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('expires_at')
                                    ->label('Expiry Date & Time')
                                    ->placeholder('Select expiry date and time')
                                    ->helperText('Leave empty for no expiry')
                                    ->minDate(now())
                                    ->displayFormat('d/m/Y H:i')
                                    ->timezone('Asia/Kolkata'),

                                Forms\Components\Placeholder::make('validity_status')
                                    ->label('Current Status')
                                    ->content(
                                        fn (?Coupon $record): string =>
                                        $record
                                            ? ($record->isNotExpired() ? '✅ Valid' : '❌ Expired')
                                            : '⏳ Not saved yet'
                                    ),
                            ]),
                    ]),

                Section::make('Course Restrictions')
                    ->schema([
                        Forms\Components\Select::make('course_ids')
                            ->label('Applicable Courses')
                            ->multiple()
                            ->options(Course::where('status', 'published')->pluck('title', 'id'))
                            ->searchable()
                            ->placeholder('Select courses (leave empty for all courses)')
                            ->helperText('Select specific courses this coupon applies to. Leave empty to apply to all courses.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('course_scope')
                            ->label('Coupon Scope')
                            ->content(
                                fn (Get $get): string =>
                                empty($get('course_ids'))
                                    ? '🌐 Applies to ALL courses'
                                    : '🎯 Applies to ' . count($get('course_ids')) . ' selected course(s)'
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color(fn (Coupon $record): string => $record->is_active ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed' => 'info',
                        'percentage' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fixed' => 'Fixed Amount',
                        'percentage' => 'Percentage',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->label('Discount')
                    ->formatStateUsing(
                        fn (Coupon $record): string =>
                        $record->type === 'fixed'
                            ? '৳' . number_format($record->value, 2)
                            : $record->value . '%'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_amount')
                    ->label('Min. Amount')
                    ->formatStateUsing(
                        fn (?float $state): string =>
                        $state ? '৳' . number_format($state, 2) : 'No minimum'
                    )
                    ->sortable(),

               Tables\Columns\TextColumn::make('usage_stats')
                    ->label('Usage')
                    ->formatStateUsing(
                        fn (Coupon $record): string =>
                        $record->used_count .
                        ($record->usage_limit ? ' / ' . $record->usage_limit : ' / ∞')
                    )
                    ->badge()
                    ->color(function (Coupon $record): string {
                        if (!$record->usage_limit) {
                            return 'info';
                        }
                        $percentage = ($record->used_count / $record->usage_limit) * 100;
                        return match (true) {
                            $percentage >= 100 => 'danger',
                            $percentage >= 80 => 'warning',
                            default => 'success',
                        };
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->badge()
                    ->color(function (?string $state, Coupon $record): string {
                        if (!$state) {
                            return 'info';
                        }
                        return $record->isNotExpired() ? 'success' : 'danger';
                    })
                    ->formatStateUsing(function (?string $state, Coupon $record): string {
                        if (!$state) {
                            return 'Never expires';
                        }
                        return $record->isNotExpired()
                            ? $record->expires_at->format('d M Y, H:i')
                            : 'Expired';
                    }),

                Tables\Columns\TextColumn::make('course_scope')
                    ->label('Scope')
                    ->formatStateUsing(
                        fn (Coupon $record): string =>
                        $record->appliesToAllCourses()
                            ? 'All Courses'
                            : count($record->course_ids) . ' Course(s)'
                    )
                    ->badge()
                    ->color(
                        fn (Coupon $record): string =>
                        $record->appliesToAllCourses() ? 'info' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All coupons')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('type')
                    ->label('Discount Type')
                    ->options([
                        'fixed' => 'Fixed Amount',
                        'percentage' => 'Percentage',
                    ]),

                Filter::make('expires_at')
                    ->form([
                        Forms\Components\Select::make('expiry_status')
                            ->options([
                                'valid' => 'Valid (Not Expired)',
                                'expired' => 'Expired',
                                'never' => 'Never Expires',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['expiry_status'] === 'valid',
                            fn (Builder $query): Builder => $query->where(function ($q) {
                                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            })
                        )->when(
                            $data['expiry_status'] === 'expired',
                            fn (Builder $query): Builder => $query->where('expires_at', '<=', now())
                        )->when(
                            $data['expiry_status'] === 'never',
                            fn (Builder $query): Builder => $query->whereNull('expires_at')
                        );
                    }),

                Filter::make('usage_limit')
                    ->form([
                        Forms\Components\Select::make('usage_status')
                            ->options([
                                'available' => 'Available for use',
                                'limit_reached' => 'Usage limit reached',
                                'unlimited' => 'Unlimited usage',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['usage_status'] === 'available',
                            fn (Builder $query): Builder => $query->where(function ($q) {
                                $q->whereNull('usage_limit')
                                  ->orWhereColumn('used_count', '<', 'usage_limit');
                            })
                        )->when(
                            $data['usage_status'] === 'limit_reached',
                            fn (Builder $query): Builder => $query->whereColumn('used_count', '>=', 'usage_limit')
                        )->when(
                            $data['usage_status'] === 'unlimited',
                            fn (Builder $query): Builder => $query->whereNull('usage_limit')
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Coupon $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Coupon $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (Coupon $record): string => $record->is_active ? 'danger' : 'success')
                        ->action(fn (Coupon $record) => $record->update(['is_active' => !$record->is_active]))
                        ->requiresConfirmation()
                        ->modalDescription(
                            fn (Coupon $record): string =>
                            'Are you sure you want to ' . ($record->is_active ? 'deactivate' : 'activate') . ' this coupon?'
                        ),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'view' => Pages\ViewCoupon::route('/{record}'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
