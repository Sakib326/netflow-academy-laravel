<?php

namespace App\Filament\Resources\ClassRoutineResource\Pages;

use App\Filament\Resources\ClassRoutineResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClassRoutine extends ViewRecord
{
    protected static string $resource = ClassRoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
