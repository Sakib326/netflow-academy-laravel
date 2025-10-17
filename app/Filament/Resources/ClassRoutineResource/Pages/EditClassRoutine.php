<?php

namespace App\Filament\Resources\ClassRoutineResource\Pages;

use App\Filament\Resources\ClassRoutineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassRoutine extends EditRecord
{
    protected static string $resource = ClassRoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
