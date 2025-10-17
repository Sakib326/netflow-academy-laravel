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
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'title')
                    ->searchable(),

                Tables\Filters\Filter::make('issue_date')
                    ->form([
                        Forms\Components\DatePicker::make('issued_from')
                            ->label('Issued From'),
                        Forms\Components\DatePicker::make('issued_until')
                            ->label('Issued Until'),
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
                    }),

                Tables\Filters\Filter::make('score_range')
                    ->label('Score Range')
                    ->form([
                        Forms\Components\Select::make('score_filter')
                            ->options([
                                '90-100' => '90-100% (Excellent)',
                                '80-89' => '80-89% (Very Good)',
                                '70-79' => '70-79% (Good)',
                                '60-69' => '60-69% (Satisfactory)',
                                '40-59' => '40-59% (Pass)',
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['score_filter'], function ($query, $range) {
                            [$min, $max] = explode('-', $range);
                            return $query->whereHas('examResponse', function ($q) use ($min, $max) {
                                $q->whereBetween('percentage', [$min, $max]);
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
