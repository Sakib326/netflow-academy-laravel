<?php

namespace App\Filament\Resources\CourseReviewResource\Pages;

use App\Filament\Resources\CourseReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourseReview extends EditRecord
{
    protected static string $resource = CourseReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
