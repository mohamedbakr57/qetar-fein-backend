<?php

namespace App\Filament\Resources\Train\TrainTripResource\Pages;

use App\Filament\Resources\Train\TrainTripResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrainTrips extends ListRecords
{
    protected static string $resource = TrainTripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
