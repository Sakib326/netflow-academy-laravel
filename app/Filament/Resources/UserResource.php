<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\CourseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $pluralLabel = 'Users';
    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->description('Manage user personal details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->placeholder('Enter full name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-user'),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->placeholder('user@example.com')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-envelope'),
                        
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->placeholder('+8801XXXXXXXXX')
                            ->tel()
                            ->maxLength(20)
                            ->default(null)
                            ->prefixIcon('heroicon-o-phone'),
                        
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->seconds(false)
                            ->placeholder('Not verified yet')
                            ->prefixIcon('heroicon-o-check-badge'),
                            Forms\Components\TextInput::make('designation')
                                ->label('Designation')
                                ->placeholder('e.g. Software Engineer, Student')
                                ->maxLength(100)
                                ->prefixIcon('heroicon-o-briefcase'),

                            Forms\Components\Textarea::make('bio')
                                ->label('Bio')
                                ->placeholder('Write a short bio...')
                                ->rows(3)
                                ->maxLength(500)
                                ->autosize()
                                ->prefixIcon('heroicon-o-document-text'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Account Settings')
                    ->description('Manage account access and preferences')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->placeholder('Leave blank to keep current password')
                            ->password()
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->required(fn (string $context) => $context === 'create')
                            ->prefixIcon('heroicon-o-lock-closed'),
                        
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'admin' => 'Admin',
                                'instructor' => 'Instructor',
                                'student' => 'Student',
                                'moderator' => 'Moderator',
                            ])
                            ->required()
                            ->searchable()
                            ->prefixIcon('heroicon-o-shield-check'),

                        Forms\Components\FileUpload::make('avatar')
                            ->label('Avatar')
                            ->image()
                            ->directory('avatars')
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->maxSize(2048)
                            ->openable()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->onColor('success')
                            ->offColor('danger')
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->iconColor('primary'),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copied!'),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->email_verified_at ? 'success' : 'danger')
                    ->formatStateUsing(fn ($record) => $record->email_verified_at ? 'Verified' : 'Not Verified'),
                
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'admin' => 'danger',
                        'instructor' => 'warning',
                        'student' => 'info',
                        'moderator' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Enrollments')
                    ->counts('enrollments')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Role Filter
                Tables\Filters\SelectFilter::make('role')
                    ->label('User Role')
                    ->options([
                        'admin' => 'Admin',
                        'instructor' => 'Instructor',
                        'student' => 'Student',
                        'moderator' => 'Moderator',
                    ])
                    ->multiple()
                    ->searchable(),

                // Active Status Filter
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All users')
                    ->trueLabel('Active users only')
                    ->falseLabel('Inactive users only'),

                // Email Verification Filter
                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verification')
                    ->placeholder('All users')
                    ->trueLabel('Verified users only')
                    ->falseLabel('Unverified users only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),

                // Course Filter (Required)
                Tables\Filters\SelectFilter::make('courses')
                    ->label('Enrolled in Course')
                    ->relationship('enrollments.batch.course', 'title')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereHas('enrollments.batch.course', function (Builder $q) use ($data) {
                                $q->whereIn('courses.id', $data['values']);
                            });
                        }
                        return $query;
                    }),

                // Batch Filter (Required)
                Tables\Filters\SelectFilter::make('batches')
                    ->label('Enrolled in Batch')
                    ->relationship('enrollments.batch', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereHas('enrollments.batch', function (Builder $q) use ($data) {
                                $q->whereIn('batches.id', $data['values']);
                            });
                        }
                        return $query;
                    }),

                // Course Category Filter
                Tables\Filters\SelectFilter::make('course_categories')
                    ->label('Course Category')
                    ->relationship('enrollments.batch.course.category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereHas('enrollments.batch.course.category', function (Builder $q) use ($data) {
                                $q->whereIn('course_categories.id', $data['values']);
                            });
                        }
                        return $query;
                    }),

                // Enrollment Status Filter
                Tables\Filters\SelectFilter::make('enrollment_status')
                    ->label('Enrollment Status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'dropped' => 'Dropped',
                        'suspended' => 'Suspended',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereHas('enrollments', function (Builder $q) use ($data) {
                                $q->whereIn('status', $data['values']);
                            });
                        }
                        return $query;
                    }),

                // Date Range Filters
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Joined from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Joined until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Joined from ' . \Carbon\Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Joined until ' . \Carbon\Carbon::parse($data['created_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),

                // Has Phone Filter
                Tables\Filters\TernaryFilter::make('has_phone')
                    ->label('Has Phone Number')
                    ->placeholder('All users')
                    ->trueLabel('Users with phone')
                    ->falseLabel('Users without phone')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone'),
                        false: fn (Builder $query) => $query->whereNull('phone'),
                    ),

                // Has Avatar Filter
                Tables\Filters\TernaryFilter::make('has_avatar')
                    ->label('Has Avatar')
                    ->placeholder('All users')
                    ->trueLabel('Users with avatar')
                    ->falseLabel('Users without avatar')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('avatar'),
                        false: fn (Builder $query) => $query->whereNull('avatar'),
                    ),

                // Recent Activity Filter
                Tables\Filters\Filter::make('recent_activity')
                    ->label('Recent Activity')
                    ->query(fn (Builder $query): Builder => $query->where('updated_at', '>=', now()->subDays(7)))
                    ->toggle(),

                // No Enrollments Filter
                Tables\Filters\Filter::make('no_enrollments')
                    ->label('No Enrollments')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('enrollments'))
                    ->toggle(),

                // Multiple Enrollments Filter
                Tables\Filters\Filter::make('multiple_enrollments')
                    ->label('Multiple Enrollments')
                    ->query(fn (Builder $query): Builder => $query->has('enrollments', '>=', 2))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('view_enrollments')
                        ->label('View Enrollments')
                        ->icon('heroicon-o-academic-cap')
                        ->color('info')
                        ->url(fn ($record) => "/admin/users/{$record->id}/enrollments"),

                    Tables\Actions\Action::make('send_verification')
                        ->label('Resend Verification')
                        ->icon('heroicon-o-envelope')
                        ->color('warning')
                        ->visible(fn ($record) => !$record->email_verified_at)
                        ->action(function ($record) {
                            // Logic to resend verification email
                            $record->sendEmailVerificationNotification();
                        }),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                        ->action(fn ($record) => $record->update(['is_active' => !$record->is_active]))
                        ->requiresConfirmation(),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('verify_email')
                        ->label('Mark as Verified')
                        ->icon('heroicon-o-check-badge')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['email_verified_at' => now()]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}