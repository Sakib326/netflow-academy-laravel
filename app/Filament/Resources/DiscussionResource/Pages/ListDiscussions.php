<?php

namespace App\Filament\Resources\DiscussionResource\Pages;

use App\Filament\Resources\DiscussionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDiscussions extends ListRecords
{
    protected static string $resource = DiscussionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
