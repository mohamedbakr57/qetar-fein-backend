<?php

namespace App\Filament\Resources\Train\TrainTripResource\Pages;

use App\Filament\Resources\Train\TrainTripResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainTrip extends EditRecord
{
    protected static string $resource = TrainTripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
