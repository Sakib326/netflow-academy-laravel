<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnrollmentResource\Pages;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Course;
use App\Models\Batch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class EnrollmentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Enrollments';
    protected static ?string $pluralLabel = 'Enrollments';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // Users who have at least one enrollment OR at least one paid order without enrollment
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->has('enrollments')
                    ->orWhereHas('orders', function ($q) {
                        $q->where('status', 'paid')
                          ->whereDoesntHave('enrollment');
                    });
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('active_enrollments')
                    ->label('Active Courses')
                    ->getStateUsing(function (User $record) {
                        return $record->enrollments()
                            ->where('status', 'active')
                            ->with('batch.course')
                            ->get()
                            ->map(fn($e) => $e->batch?->course?->title)
                            ->filter()
                            ->implode(', ');
                    })
                    ->badge()
                    ->color('success')
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_pending')
                    ->label('Pending Only')
                    ->query(fn (Builder $query) => $query->whereHas('orders', function ($q) {
                        $q->where('status', 'paid')->whereDoesntHave('enrollment');
                    })),
                Tables\Filters\Filter::make('course_batch')
                    ->form([
                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->options(Course::pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('batch_id', null)),
                        Forms\Components\Select::make('batch_id')
                            ->label('Batch')
                            ->options(function (Get $get) {
                                $courseId = $get('course_id');
                                if (!$courseId) {
                                    return Batch::pluck('name', 'id');
                                }
                                $course = Course::find($courseId);
                                $courseIds = [$courseId];
                                if ($course && $course->isBundle()) {
                                    $courseIds = array_unique(array_merge($courseIds, $course->bundle_courses ?? []));
                                }
                                return Batch::whereIn('course_id', $courseIds)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get) => !$get('course_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['course_id'], function ($q, $courseId) {
                                $q->where(function ($sq) use ($courseId) {
                                    // 1. Has enrollment for this course
                                    $sq->whereHas('enrollments.batch', fn ($qb) => $qb->where('course_id', $courseId))
                                       // 2. OR Has pending paid order for this course
                                       ->orWhereHas('orders', fn ($qo) => $qo->where('course_id', $courseId)->where('status', 'paid'));
                                });
                            })
                            ->when($data['batch_id'], function ($q, $batchId) {
                                $q->whereHas('enrollments', fn ($qe) => $qe->where('batch_id', $batchId));
                            });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['course_id'] ?? null) {
                            $indicators[] = 'Course: ' . Course::find($data['course_id'])->title;
                        }
                        if ($data['batch_id'] ?? null) {
                            $indicators[] = 'Batch: ' . Batch::find($data['batch_id'])->name;
                        }
                        return $indicators;
                    }),
                Tables\Filters\Filter::make('email')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email address')
                            ->placeholder('Enter email...')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['email'],
                            fn (Builder $query, $email): Builder => $query->where('email', 'like', "%{$email}%"),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['email'] ?? null) {
                            $indicators[] = 'Email: ' . $data['email'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('manage_access')
                    ->label('Manage Access')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->mountUsing(fn (Forms\ComponentContainer $form, User $record) => $form->fill([
                        'enrollments_list' => $record->enrollments()->with('batch.course')->get()->map(fn ($e) => [
                            'id' => $e->id,
                            'course_name' => $e->batch?->course?->title ?? 'N/A',
                            'batch_name' => $e->batch?->name ?? 'N/A',
                            'status' => $e->status,
                        ])->toArray(),
                    ]))
                    ->form([
                        Forms\Components\Section::make('Current Enrollments')
                            ->description('Manage existing course access')
                            ->schema([
                                Forms\Components\Repeater::make('enrollments_list')
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Placeholder::make('course_name_display')
                                                    ->label('Course')
                                                    ->content(fn ($get) => $get('course_name')),
                                                Forms\Components\Placeholder::make('batch_name_display')
                                                    ->label('Batch')
                                                    ->content(fn ($get) => $get('batch_name')),
                                            ]),
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'active' => 'Active',
                                                'suspended' => 'Suspended',
                                                'completed' => 'Completed',
                                            ])
                                            ->required(),
                                    ])
                                    ->addable(false)
                                    ->deletable(false),
                            ]),
                        
                        Forms\Components\Section::make('New Admissions')
                            ->description('Admit student to pending paid courses')
                            ->schema([
                                Forms\Components\Select::make('admit_course_id')
                                    ->label('Paid Course')
                                    ->options(function (User $record) {
                                        $pendingOrders = Order::where('user_id', $record->id)
                                            ->where('status', 'paid')
                                            ->get()
                                            ->filter(function ($order) use ($record) {
                                                $course = Course::find($order->course_id);
                                                if (!$course) return false;
                                                $bundledIds = $course->isBundle() ? ($course->bundle_courses ?? []) : [];
                                                $courseIds = array_unique(array_merge([$course->id], $bundledIds));
                                                return !Enrollment::where('user_id', $record->id)
                                                    ->whereHas('batch', fn($q) => $q->whereIn('course_id', $courseIds))
                                                    ->exists();
                                            });

                                        return Course::whereIn('id', $pendingOrders->pluck('course_id'))->pluck('title', 'id');
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(fn (Set $set) => $set('admit_batch_id', null)),

                                Forms\Components\Select::make('admit_batch_id')
                                    ->label('Target Batch')
                                    ->options(function (Get $get) {
                                        $courseId = $get('admit_course_id');
                                        if (!$courseId) return [];
                                        $course = Course::find($courseId);
                                        $courseIds = [$courseId];
                                        if ($course && $course->isBundle()) {
                                            $courseIds = array_unique(array_merge($courseIds, $course->bundle_courses ?? []));
                                        }
                                        return Batch::whereIn('course_id', $courseIds)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get) => !$get('admit_course_id')),
                            ])
                            ->visible(function (User $record) {
                                return Order::where('user_id', $record->id)
                                    ->where('status', 'paid')
                                    ->whereDoesntHave('enrollment')
                                    ->exists();
                            }),
                    ])
                    ->action(function (User $record, array $data) {
                        // 1. Update existing enrollments
                        if (isset($data['enrollments_list'])) {
                            foreach ($data['enrollments_list'] as $item) {
                                if (!empty($item['id'])) {
                                    Enrollment::where('id', $item['id'])->update(['status' => $item['status']]);
                                }
                            }
                        }

                        // 2. Add new admission
                        if (!empty($data['admit_course_id']) && !empty($data['admit_batch_id'])) {
                            $order = Order::where('user_id', $record->id)
                                ->where('course_id', $data['admit_course_id'])
                                ->where('status', 'paid')
                                ->first();

                            Enrollment::create([
                                'user_id' => $record->id,
                                'batch_id' => $data['admit_batch_id'],
                                'order_id' => $order?->id,
                                'status' => 'active',
                                'enrolled_at' => now(),
                            ]);
                        }

                        Notification::make()
                            ->title('Student access updated successfully')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (User $record) => "Manage Student Access: {$record->name}")
                    ->modalWidth('4xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
        ];
    }
}
