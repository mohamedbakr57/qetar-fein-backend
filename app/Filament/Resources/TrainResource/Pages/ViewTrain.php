<?php

namespace App\Filament\Resources\TrainResource\Pages;

use App\Filament\Resources\TrainResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;

class ViewTrain extends ViewRecord
{
    protected static string $resource = TrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Train Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Train Number')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        'passenger' => 'success',
                                        'high_speed' => 'warning',
                                        'freight' => 'info',
                                        default => 'gray'
                                    }),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        'active' => 'success',
                                        'maintenance' => 'warning',
                                        'inactive' => 'danger',
                                        default => 'gray'
                                    }),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale())),
                                TextEntry::make('operator')
                                    ->formatStateUsing(fn ($record) => $record->getTranslation('operator', app()->getLocale())),
                            ]),
                        TextEntry::make('description')
                            ->formatStateUsing(fn ($record) => $record->getTranslation('description', app()->getLocale()))
                            ->columnSpanFull(),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('capacity')
                                    ->suffix(' passengers'),
                                TextEntry::make('max_speed')
                                    ->suffix(' km/h'),
                                TextEntry::make('amenities')
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),

                Section::make('Train Journey (Business Rules)')
                    ->schema([
                        RepeatableEntry::make('stops')
                            ->schema([
                                Grid::make(8)
                                    ->schema([
                                        TextEntry::make('stop_number')
                                            ->label('Stop #')
                                            ->badge()
                                            ->size('sm')
                                            ->color('primary'),
                                        TextEntry::make('station.name')
                                            ->label('Station')
                                            ->formatStateUsing(function ($record) {
                                                if (!$record->station) return 'N/A';

                                                $name = $record->station->name;

                                                // Try to decode as JSON first
                                                $decoded = json_decode($name, true);
                                                if ($decoded && is_array($decoded)) {
                                                    return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['ar'] ?? $name;
                                                }

                                                // If not JSON, return the raw name
                                                return $name;
                                            })
                                            ->weight('medium'),
                                        TextEntry::make('arrival_time')
                                            ->label('Arrival')
                                            ->time('H:i')
                                            ->color('success'),
                                        TextEntry::make('departure_time')
                                            ->label('Departure')
                                            ->time('H:i')
                                            ->color('warning'),
                                        TextEntry::make('platform')
                                            ->label('Platform')
                                            ->badge()
                                            ->color('secondary'),
                                        TextEntry::make('formatted_stop_duration')
                                            ->label('Stop Duration'),
                                        IconEntry::make('is_major_stop')
                                            ->label('Major')
                                            ->boolean()
                                            ->trueIcon('heroicon-m-star')
                                            ->falseIcon('heroicon-m-minus')
                                            ->trueColor('warning')
                                            ->falseColor('gray'),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->words(3),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        TextEntry::make('journey_summary')
                            ->label('Journey Summary')
                            ->default(function ($record) {
                                $firstStop = $record->stops()->orderBy('stop_number')->first();
                                $lastStop = $record->stops()->orderByDesc('stop_number')->first();

                                if ($firstStop && $lastStop) {
                                    // Handle both JSON and plain text station names
                                    $originName = $firstStop->station->name;
                                    $destName = $lastStop->station->name;

                                    $originDecoded = json_decode($originName, true);
                                    $destDecoded = json_decode($destName, true);

                                    $origin = $originDecoded && is_array($originDecoded)
                                        ? ($originDecoded[app()->getLocale()] ?? $originDecoded['en'] ?? $originDecoded['ar'] ?? $originName)
                                        : $originName;

                                    $destination = $destDecoded && is_array($destDecoded)
                                        ? ($destDecoded[app()->getLocale()] ?? $destDecoded['en'] ?? $destDecoded['ar'] ?? $destName)
                                        : $destName;

                                    $totalStops = $record->stops()->count();

                                    return "Journey from {$origin} to {$destination} with {$totalStops} stops";
                                }

                                return 'No stops defined for this train';
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}