<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Course;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Batch;
use App\Models\Payment; // ADD THIS LINE
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Order Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Order Information')->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->live(),
                    ])->columns(2),

                    Forms\Components\Section::make('Pricing')->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->default(0)
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Placeholder::make('final_amount')
                            ->label('Final Amount')
                            ->content(function ($get) {
                                $amount = $get('amount') ?? 0;
                                $discount = $get('discount_amount') ?? 0;
                                return '$' . number_format(max(0, $amount - $discount), 2);
                            }),
                    ])->columns(3),
                ])->columnSpan(2),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Additional Information')->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Admin Notes')
                            ->rows(4)
                            ->placeholder('Add notes about this order...'),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created At')
                            ->content(fn ($record) => $record?->created_at?->format('M j, Y g:i A') ?? '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last Updated')
                            ->content(fn ($record) => $record?->updated_at?->format('M j, Y g:i A') ?? '-'),
                    ]),

                    Forms\Components\Section::make('Enrollment Status')->schema([
                        Forms\Components\Placeholder::make('enrollment_status')
                            ->label('Enrollment Status')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }

                                $enrollment = Enrollment::where('order_id', $record->id)->first();
                                if ($enrollment) {
                                    return ucfirst($enrollment->status);
                                }
                                return 'Not Enrolled';
                            }),
                    ])->visible(fn ($record) => $record !== null),
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->user->email),

                TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->description(function ($record) {
                        $course = $record->course;
                        return $course->course_type === 'bundle' ? 'Bundle Course' : 'Single Course';
                    }),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->description(function ($record) {
                        if ($record->discount_amount > 0) {
                            return 'Discount: $' . number_format($record->discount_amount, 2);
                        }
                        return null;
                    }),

                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'paid',
                        'heroicon-o-x-circle' => 'cancelled',
                    ]),

                TextColumn::make('enrollment_status')
                    ->label('Enrollment')
                    ->getStateUsing(function ($record) {
                        $enrollment = Enrollment::where('order_id', $record->id)->first();
                        return $enrollment ? ucfirst($enrollment->status) : 'Not Enrolled';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Suspended' => 'warning',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('notes')
                    ->limit(30)
                    ->placeholder('No notes')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('course_type')
                    ->label('Course Type')
                    ->options([
                        'single' => 'Single Course',
                        'bundle' => 'Bundle Course',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value'])) {
                            return $query->whereHas('course', function (Builder $q) use ($data) {
                                $q->where('course_type', $data['value']);
                            });
                        }
                        return $query;
                    }),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->placeholder('Min amount'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->placeholder('Max amount'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
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
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Action::make('approve_payment')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Payment')
                        ->modalDescription('This will mark the order as paid and automatically enroll the user in the course.')
                        ->action(function ($record) {
                            static::approvePayment($record);
                        }),

                    Action::make('cancel_order')
                        ->label('Cancel Order')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Order')
                        ->modalDescription('Are you sure you want to cancel this order?')
                        ->action(function ($record) {
                            $record->update(['status' => 'cancelled']);

                            Notification::make()
                                ->title('Order cancelled successfully')
                                ->success()
                                ->send();
                        }),

                    Action::make('view_enrollment')
                        ->label('View Enrollment')
                        ->icon('heroicon-o-academic-cap')
                        ->color('info')
                        ->visible(function ($record) {
                            return Enrollment::where('order_id', $record->id)->exists();
                        })
                        ->url(function ($record) {
                            $enrollment = Enrollment::where('order_id', $record->id)->first();
                            return $enrollment ? "/admin/enrollments/{$enrollment->id}" : null;
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected Orders')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Selected Orders')
                        ->modalDescription('This will mark all selected pending orders as paid and enroll users.')
                        ->action(function ($records) {
                            $approved = 0;
                            foreach ($records as $order) {
                                if ($order->status === 'pending') {
                                    static::approvePayment($order);
                                    $approved++;
                                }
                            }

                            Notification::make()
                                ->title("Approved {$approved} orders successfully")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('cancel_selected')
                        ->label('Cancel Selected Orders')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $cancelled = 0;
                            foreach ($records as $order) {
                                if ($order->status === 'pending') {
                                    $order->update(['status' => 'cancelled']);
                                    $cancelled++;
                                }
                            }

                            Notification::make()
                                ->title("Cancelled {$cancelled} orders successfully")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Order')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    protected static function approvePayment(Order $order)
    {
        DB::beginTransaction();
        try {
            // 1. Update order status
            $order->update(['status' => 'paid']);

            // 2. Create/update payment record
            Payment::updateOrCreate(
                ['user_id' => $order->user_id, 'course_id' => $order->course_id],
                ['order_id' => $order->id, 'amount' => $order->amount, 'status' => 'completed']
            );

            // 3. Auto-enroll user
            static::autoEnrollUser($order);

            DB::commit();

            Notification::make()
                ->title('Payment approved and user enrolled successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function autoEnrollUser(Order $order)
    {
        $course = $order->course;
        $user = $order->user;

        if ($course->isBundle()) {
            // Bundle: enroll in each course
            foreach ($course->getBundledCourses() as $bundleCourse) {
                $batch = static::getOrCreateBatch($bundleCourse);

                // Check if not already enrolled
                $existingEnrollment = Enrollment::where('user_id', $user->id)
                    ->where('batch_id', $batch->id)
                    ->first();

                if (!$existingEnrollment) {
                    Enrollment::create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'batch_id' => $batch->id,
                        'status' => 'active',
                    ]);
                }
            }
        } else {
            // Single course
            $batch = static::getOrCreateBatch($course);

            // Check if not already enrolled
            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->where('batch_id', $batch->id)
                ->first();

            if (!$existingEnrollment) {
                Enrollment::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'batch_id' => $batch->id,
                    'status' => 'active',
                ]);
            }
        }
    }

    protected static function getOrCreateBatch($course)
    {
        $batch = Batch::where('course_id', $course->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>', now());
            })
            ->first();

        if (!$batch) {
            $batch = Batch::create([
                'course_id' => $course->id,
                'name' => $course->title . ' Batch ' . (Batch::where('course_id', $course->id)->count() + 1),
                'is_active' => true,
                'start_date' => now(),
            ]);
        }

        return $batch;
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
