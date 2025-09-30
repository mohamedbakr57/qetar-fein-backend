<?php

namespace App\Filament\Widgets;

use App\Models\Train\Train;
use App\Models\Train\Station;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TrainSystemOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeTrains = Train::where('status', 'active')->count();
        $activeStations = Station::where('status', 'active')->count();
        $totalCapacity = Train::where('status', 'active')->sum('capacity');

        return [
            Stat::make('Active Trains', $activeTrains)
                ->description('Currently operational')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Active Stations', $activeStations)
                ->description('Serving passengers')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Total Capacity', number_format($totalCapacity))
                ->description('Combined passenger capacity')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}