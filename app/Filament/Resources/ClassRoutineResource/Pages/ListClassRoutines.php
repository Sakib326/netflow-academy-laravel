<?php

namespace App\Filament\Resources\ClassRoutineResource\Pages;

use App\Filament\Resources\ClassRoutineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassRoutines extends ListRecords
{
    protected static string $resource = ClassRoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
