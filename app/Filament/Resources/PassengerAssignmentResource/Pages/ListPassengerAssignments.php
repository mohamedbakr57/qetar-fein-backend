<?php

namespace App\Filament\Resources\PassengerAssignmentResource\Pages;

use App\Filament\Resources\PassengerAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPassengerAssignments extends ListRecords
{
    protected static string $resource = PassengerAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
