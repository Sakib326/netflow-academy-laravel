<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificateResource\Pages;
use App\Models\Certificate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Response;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Academics';
    protected static ?string $navigationLabel = 'Certificates';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('certificate_code')
                    ->required()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('issue_date')
                    ->required(),

                Forms\Components\TextInput::make('path')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('certificate_code')
                    ->label('Certificate Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('examResponse.percentage')
                    ->label('Score')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('exists')
                    ->label('PDF Status')
                    ->boolean()
                    ->getStateUsing(function (Certificate $record): bool {
                        return file_exists(public_path($record->path));
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generated At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                // Course Filter
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'title')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Student Filter
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Issue Date Range Filter
                Tables\Filters\Filter::make('issue_date_range')
                    ->label('Issue Date Range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('issued_from')
                                    ->label('From Date')
                                    ->placeholder('Select start date'),
                                Forms\Components\DatePicker::make('issued_until')
                                    ->label('To Date')
                                    ->placeholder('Select end date'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['issued_from'] ?? null) {
                            $indicators['issued_from'] = 'From: ' . \Carbon\Carbon::parse($data['issued_from'])->toFormattedDateString();
                        }
                        if ($data['issued_until'] ?? null) {
                            $indicators['issued_until'] = 'Until: ' . \Carbon\Carbon::parse($data['issued_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),

                // Score Range Filter
                Tables\Filters\Filter::make('score_range')
                    ->label('Score Range')
                    ->form([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('score_filter')
                                    ->label('Grade Category')
                                    ->options([
                                        '90-100' => 'A+ (90-100%)',
                                        '80-89' => 'A (80-89%)',
                                        '70-79' => 'B (70-79%)',
                                        '60-69' => 'C (60-69%)',
                                        '50-59' => 'D (50-59%)',
                                        '40-49' => 'E (40-49%)',
                                    ])
                                    ->placeholder('Select grade range'),
                                Forms\Components\TextInput::make('min_score')
                                    ->label('Min Score (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('0'),
                                Forms\Components\TextInput::make('max_score')
                                    ->label('Max Score (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('100'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['score_filter'], function ($query, $range) {
                                [$min, $max] = explode('-', $range);
                                return $query->whereHas('examResponse', function ($q) use ($min, $max) {
                                    $q->whereBetween('percentage', [$min, $max]);
                                });
                            })
                            ->when($data['min_score'], function ($query, $minScore) {
                                return $query->whereHas('examResponse', function ($q) use ($minScore) {
                                    $q->where('percentage', '>=', $minScore);
                                });
                            })
                            ->when($data['max_score'], function ($query, $maxScore) {
                                return $query->whereHas('examResponse', function ($q) use ($maxScore) {
                                    $q->where('percentage', '<=', $maxScore);
                                });
                            });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['score_filter'] ?? null) {
                            $indicators['score_filter'] = 'Grade: ' . $data['score_filter'];
                        }
                        if ($data['min_score'] ?? null) {
                            $indicators['min_score'] = 'Min: ' . $data['min_score'] . '%';
                        }
                        if ($data['max_score'] ?? null) {
                            $indicators['max_score'] = 'Max: ' . $data['max_score'] . '%';
                        }
                        return $indicators;
                    }),

                // Course Category Filter
                Tables\Filters\SelectFilter::make('course_category')
                    ->label('Course Category')
                    ->options(function () {
                        return \App\Models\CourseCategory::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, $state) {
                        if ($state['value']) {
                            $query->whereHas('course.category', function ($q) use ($state) {
                                $q->where('id', $state['value']);
                            });
                        }
                    }),

                // Certificate Status Filter
                Tables\Filters\Filter::make('certificate_status')
                    ->label('Certificate Status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'exists' => 'PDF File Exists',
                                'missing' => 'PDF File Missing',
                            ])
                            ->placeholder('Select status')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['status'], function ($query, $status) {
                            $certificates = \App\Models\Certificate::all();
                            if ($status === 'exists') {
                                $existingIds = $certificates->filter(function ($cert) {
                                    return file_exists(public_path($cert->path));
                                })->pluck('id');
                                return $query->whereIn('id', $existingIds);
                            } elseif ($status === 'missing') {
                                $missingIds = $certificates->filter(function ($cert) {
                                    return !file_exists(public_path($cert->path));
                                })->pluck('id');
                                return $query->whereIn('id', $missingIds);
                            }
                        });
                    }),

                // Recent Certificates Filter
                Tables\Filters\Filter::make('recent')
                    ->label('Recent Certificates')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'last_week' => 'Last Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'this_year' => 'This Year',
                            ])
                            ->placeholder('Select time period')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['period'], function ($query, $period) {
                            return match($period) {
                                'today' => $query->whereDate('created_at', today()),
                                'yesterday' => $query->whereDate('created_at', yesterday()),
                                'this_week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                                'last_week' => $query->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                                'this_month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                                'last_month' => $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year),
                                'this_year' => $query->whereYear('created_at', now()->year),
                                default => $query,
                            };
                        });
                    }),

                // Certificate Code Pattern Filter
                Tables\Filters\Filter::make('certificate_pattern')
                    ->label('Certificate Code Pattern')
                    ->form([
                        Forms\Components\TextInput::make('code_contains')
                            ->label('Code Contains')
                            ->placeholder('Enter text to search in certificate code'),
                        Forms\Components\Select::make('code_prefix')
                            ->label('Code Prefix')
                            ->options([
                                'CERT-' => 'Standard (CERT-)',
                                'TEST-' => 'Test Certificates',
                                'DEMO-' => 'Demo Certificates',
                            ])
                            ->placeholder('Select prefix pattern')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['code_contains'], function ($query, $text) {
                                return $query->where('certificate_code', 'like', "%{$text}%");
                            })
                            ->when($data['code_prefix'], function ($query, $prefix) {
                                return $query->where('certificate_code', 'like', "{$prefix}%");
                            });
                    }),

                // Batch Filter (if certificates are linked to batches through enrollments)
                Tables\Filters\SelectFilter::make('batch')
                    ->label('Batch')
                    ->options(function () {
                        return \App\Models\Batch::with('course')->get()
                            ->mapWithKeys(function ($batch) {
                                return [$batch->id => $batch->course->title . ' - ' . $batch->name];
                            });
                    })
                    ->query(function (Builder $query, $state) {
                        if ($state['value']) {
                            $query->whereHas('user.enrollments', function ($q) use ($state) {
                                $q->where('batch_id', $state['value']);
                            });
                        }
                    }),

                // Performance Filter
                Tables\Filters\Filter::make('performance')
                    ->label('Performance Level')
                    ->form([
                        Forms\Components\CheckboxList::make('levels')
                            ->options([
                                'excellent' => 'Excellent (90%+)',
                                'very_good' => 'Very Good (80-89%)',
                                'good' => 'Good (70-79%)',
                                'satisfactory' => 'Satisfactory (60-69%)',
                                'pass' => 'Pass (40-59%)',
                            ])
                            ->columns(2)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['levels'], function ($query, $levels) {
                            return $query->whereHas('examResponse', function ($q) use ($levels) {
                                $q->where(function ($subQuery) use ($levels) {
                                    foreach ($levels as $level) {
                                        match($level) {
                                            'excellent' => $subQuery->orWhere('percentage', '>=', 90),
                                            'very_good' => $subQuery->orWhereBetween('percentage', [80, 89]),
                                            'good' => $subQuery->orWhereBetween('percentage', [70, 79]),
                                            'satisfactory' => $subQuery->orWhereBetween('percentage', [60, 69]),
                                            'pass' => $subQuery->orWhereBetween('percentage', [40, 59]),
                                        };
                                    }
                                });
                            });
                        });
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Certificate $record) {
                        $filePath = public_path($record->path);

                        if (file_exists($filePath)) {
                            return Response::download($filePath);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('File not found')
                            ->body('Certificate PDF file does not exist.')
                            ->danger()
                            ->send();
                    }),

                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Certificate $record): string => asset($record->path))
                    ->openUrlInNewTab(),

                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Certificate $record) {
                        // Trigger regeneration by updating exam response
                        $record->examResponse->touch();

                        \Filament\Notifications\Notification::make()
                            ->title('Certificate Regenerated')
                            ->body('The certificate has been regenerated successfully.')
                            ->success()
                            ->send();
                    }),

                // Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Action::make('bulk_download')
                        ->label('Download Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function ($records) {
                            $zip = new \ZipArchive();
                            $zipFileName = 'certificates_' . now()->format('Y-m-d_H-i-s') . '.zip';
                            $zipPath = public_path($zipFileName);

                            if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                                foreach ($records as $certificate) {
                                    $filePath = public_path($certificate->path);
                                    if (file_exists($filePath)) {
                                        $zip->addFile($filePath, basename($certificate->path));
                                    }
                                }
                                $zip->close();

                                return Response::download($zipPath)->deleteFileAfterSend(true);
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificates::route('/'),
            'create' => Pages\CreateCertificate::route('/create'),
            // 'view' => Pages\ViewCertificate::route('/{record}'),
            'edit' => Pages\EditCertificate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
