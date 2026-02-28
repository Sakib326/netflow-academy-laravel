<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BulkAdmissionResource\Pages;
use App\Models\Course;
use App\Models\Batch;
use App\Models\User;
use App\Models\Order;
use App\Models\Enrollment;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;

class BulkAdmissionResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Bulk Admission';
    protected static ?string $pluralLabel = 'Bulk Admission';
    protected static ?string $navigationGroup = 'Admission Management';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('course_type', 'bundle');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Bundle Course')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bundled_count')
                    ->label('Sub-courses')
                    ->getStateUsing(fn (Course $record) => count($record->bundle_courses ?? []))
                    ->badge(),
            ])
            ->actions([
                Action::make('admit_to_subcourse')
                    ->label('Admit to Sub-course')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->form([
                        Select::make('sub_course_id')
                            ->label('Select Sub-course')
                            ->options(fn (Course $record) => 
                                Course::whereIn('id', $record->bundle_courses ?? [])->pluck('title', 'id')
                            )
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('batch_id', null)),
                        
                        Select::make('batch_id')
                            ->label('Target Batch')
                            ->options(function (Get $get) {
                                $subCourseId = $get('sub_course_id');
                                if (!$subCourseId) return [];

                                return Batch::where('course_id', $subCourseId)
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($b) => [$b->id => "{$b->name} ({$b->getAvailableSlots()} slots left)"]);
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn (Get $get) => !$get('sub_course_id')),
                        
                        CheckboxList::make('user_ids')
                            ->label('Select Students')
                            ->options(function (Course $record, Get $get) {
                                $subCourseId = $get('sub_course_id');
                                if (!$subCourseId) return [];

                                $orders = Order::where('course_id', $record->id)
                                    ->where('status', 'paid')
                                    ->with('user')
                                    ->get();

                                return $orders->filter(function ($order) use ($subCourseId) {
                                    // Not already enrolled in this sub-course
                                    return !Enrollment::where('user_id', $order->user_id)
                                        ->whereHas('batch', fn($q) => $q->where('course_id', $subCourseId))
                                        ->exists();
                                })->pluck('user.name', 'user.id');
                            })
                            ->descriptions(function (Course $record, Get $get) {
                                $subCourseId = $get('sub_course_id');
                                if (!$subCourseId) return [];

                                $orders = Order::where('course_id', $record->id)
                                    ->where('status', 'paid')
                                    ->with('user')
                                    ->get();

                                return $orders->filter(function ($order) use ($subCourseId) {
                                    return !Enrollment::where('user_id', $order->user_id)
                                        ->whereHas('batch', fn($q) => $q->where('course_id', $subCourseId))
                                        ->exists();
                                })->pluck('user.email', 'user.id');
                            })
                            ->columns(2)
                            ->required()
                            ->bulkToggleable()
                            ->searchable()
                            ->visible(fn (Get $get) => $get('batch_id')),
                    ])
                    ->action(function (Course $record, array $data) {
                        $batch = Batch::find($data['batch_id']);
                        $userIds = $data['user_ids'];
                        $studentCount = count($userIds);

                        // 1. Check if batches exist (form already handles this partially but let's be safe)
                        if (!$batch) {
                            Notification::make()->title('Error: Target batch not found.')->danger()->send();
                            return;
                        }

                        // 2. Check if batch has ended
                        if ($batch->hasEnded()) {
                            Notification::make()
                                ->title('Error: Cannot admit students to an ended batch.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // 3. Check Capacity
                        $availableSlots = $batch->getAvailableSlots();
                        if ($studentCount > $availableSlots) {
                            Notification::make()
                                ->title('Error: Batch Capacity Exceeded')
                                ->body("You selected $studentCount students, but this batch only has $availableSlots slots remaining.")
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // 4. Perform Admission
                        $count = 0;
                        foreach ($userIds as $userId) {
                            $order = Order::where('user_id', $userId)
                                ->where('course_id', $record->id)
                                ->where('status', 'paid')
                                ->first();

                            Enrollment::create([
                                'user_id' => $userId,
                                'batch_id' => $batch->id,
                                'order_id' => $order?->id,
                                'status' => 'active',
                                'enrolled_at' => now(),
                            ]);
                            $count++;
                        }

                        Notification::make()
                            ->title("$count Students Admitted Successfully")
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (Course $record) => "Bundle Sub-course Admission: {$record->title}")
                    ->modalDescription('Admit bundle purchasers to a newly added sub-course module.')
                    ->modalSubmitActionLabel('Confirm Admission')
                    ->modalWidth('4xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkAdmissions::route('/'),
        ];
    }
}
