<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Payment;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';
    protected static ?string $title = 'Course Batches';
    protected static ?string $icon = 'heroicon-o-user-group';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Batch Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Batch 1 - January 2024')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('max_students')
                            ->label('Maximum Students')
                            ->numeric()
                            ->default(30)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('end_date')
                            ->label('End Date')
                            ->after('start_date')
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Open for Enrollment',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Student Enrollment')
                    ->schema([
                        Forms\Components\Repeater::make('enrollments')
                            ->label('Students')
                            ->relationship('enrollments')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Student')
                                    ->relationship('user', 'name', fn($query) => 
                                        $query->where('role', 'student')
                                    )
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'dropped' => 'Dropped',
                                        'suspended' => 'Suspended',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('enrolled_at')
                                    ->label('Enrollment Date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('progress_percentage')
                                    ->label('Progress %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->columnSpan(1),

                                // Payment Information
                                Forms\Components\Section::make('Payment Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('payment_amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->prefix('$')
                                            ->columnSpan(1),

                                        Forms\Components\Select::make('payment_status')
                                            ->label('Payment Status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'completed' => 'Completed',
                                                'failed' => 'Failed',
                                                'refunded' => 'Refunded',
                                            ])
                                            ->default('pending')
                                            ->columnSpan(1),

                                        Forms\Components\Select::make('payment_method')
                                            ->label('Payment Method')
                                            ->options([
                                                'credit_card' => 'Credit Card',
                                                'paypal' => 'PayPal',
                                                'bank_transfer' => 'Bank Transfer',
                                                'cash' => 'Cash',
                                                'free' => 'Free',
                                            ])
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('transaction_id')
                                            ->label('Transaction ID')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(4)
                                    ->collapsed()
                                    ->collapsible(),
                            ])
                            ->columns(5)
                            ->itemLabel(fn (array $state): string => 
                                User::find($state['user_id'])?->name ?? 'New Student'
                            )
                            ->collapsible()
                            ->cloneable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'open' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Students')
                    ->counts('enrollments')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('active_enrollments_count')
                    ->label('Active')
                    ->counts(['enrollments' => fn($query) => $query->where('status', 'active')])
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('max_students')
                    ->label('Max')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('USD')
                    ->getStateUsing(function ($record) {
                        return $record->enrollments()
                            ->join('payments', 'enrollments.user_id', '=', 'payments.user_id')
                            ->where('payments.course_id', $this->ownerRecord->id)
                            ->where('payments.status', 'completed')
                            ->sum('payments.amount') ?? 0;
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Batch')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['course_id'] = $this->ownerRecord->id;
                        
                        // Handle enrollments and payments
                        if (isset($data['enrollments'])) {
                            foreach ($data['enrollments'] as &$enrollment) {
                                // Create payment record if payment info provided
                                if (!empty($enrollment['payment_amount'])) {
                                    $enrollment['payment_data'] = [
                                        'amount' => $enrollment['payment_amount'],
                                        'status' => $enrollment['payment_status'] ?? 'pending',
                                        'payment_method' => $enrollment['payment_method'] ?? 'credit_card',
                                        'transaction_id' => $enrollment['transaction_id'] ?? null,
                                    ];
                                }
                                
                                // Remove payment fields from enrollment data
                                unset($enrollment['payment_amount'], $enrollment['payment_status'], 
                                      $enrollment['payment_method'], $enrollment['transaction_id']);
                            }
                        }
                        
                        return $data;
                    })
                    ->after(function ($record, array $data) {
                        // Create payments for enrollments
                        if (isset($data['enrollments'])) {
                            foreach ($data['enrollments'] as $enrollmentData) {
                                if (isset($enrollmentData['payment_data'])) {
                                    Payment::create([
                                        'user_id' => $enrollmentData['user_id'],
                                        'course_id' => $this->ownerRecord->id,
                                        'amount' => $enrollmentData['payment_data']['amount'],
                                        'status' => $enrollmentData['payment_data']['status'],
                                        'payment_method' => $enrollmentData['payment_data']['payment_method'],
                                        'transaction_id' => $enrollmentData['payment_data']['transaction_id'],
                                    ]);
                                }
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Load payment data for enrollments
                        if (isset($data['enrollments'])) {
                            foreach ($data['enrollments'] as &$enrollment) {
                                $payment = Payment::where('user_id', $enrollment['user_id'])
                                    ->where('course_id', $this->ownerRecord->id)
                                    ->first();
                                
                                if ($payment) {
                                    $enrollment['payment_amount'] = $payment->amount;
                                    $enrollment['payment_status'] = $payment->status;
                                    $enrollment['payment_method'] = $payment->payment_method;
                                    $enrollment['transaction_id'] = $payment->transaction_id;
                                }
                            }
                        }
                        
                        return $data;
                    }),

                Tables\Actions\Action::make('manage_students')
                    ->label('Manage Students')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->action(function ($record) {
                        // Custom action to manage students
                        // You can redirect to a dedicated page or open a modal
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_in_progress')
                        ->label('Mark In Progress')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['status' => 'in_progress']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark Completed')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'completed']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create First Batch'),
            ]);
    }
}