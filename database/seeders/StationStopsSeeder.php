<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Station;
use App\Models\Train;
use App\Models\Route;

class 
StationStopsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Importing detailed stop information...');

        $sqliteDb = new \SQLite3('D:\trains.db');

        // Import route stations (intermediate stops)
        $this->importRouteStations($sqliteDb);

        $this->command->info('Completed importing station stops');
    }

    private function importRouteStations($sqliteDb): void
    {
        // Get all stops with station and train information
        $stops = $sqliteDb->query("
            SELECT
                s.Train_ID,
                s.Station_ID,
                s.Stop_No,
                s.Arrival_Time,
                s.Departure_Time,
                st.Station_En,
                st.Station_Ar,
                t.Train_No
            FROM Stop s
            JOIN Station st ON s.Station_ID = st.Station_ID
            JOIN Train t ON s.Train_ID = t.Train_ID
            ORDER BY s.Train_ID, s.Stop_No
        ");

        $routeStations = [];
        $currentTrainId = null;
        $currentRoute = null;
        $stopOrder = 1;

        while ($row = $stops->fetchArray(SQLITE3_ASSOC)) {
            if ($currentTrainId !== $row['Train_ID']) {
                // New train route
                $currentTrainId = $row['Train_ID'];
                $stopOrder = 1;

                // Find corresponding route in our system
                $currentRoute = $this->findRouteByTrainNumber($row['Train_No']);
            }

            if ($currentRoute) {
                $station = $this->findStationByName($row['Station_En']);

                if ($station) {
                    $routeStations[] = [
                        'route_id' => $currentRoute['id'],
                        'station_id' => $station['id'],
                        'stop_order' => $stopOrder,
                        'arrival_time' => $this->parseTime($row['Arrival_Time']),
                        'departure_time' => $this->parseTime($row['Departure_Time']),
                        'duration_minutes' => $this->calculateStopDuration(
                            $row['Arrival_Time'],
                            $row['Departure_Time']
                        ),
                        'distance_from_start' => $this->calculateDistanceFromStart($stopOrder),
                        'platform' => $this->assignPlatform($station['type']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $stopOrder++;
        }

        // Insert route stations in chunks
        $chunks = array_chunk($routeStations, 1000);
        foreach ($chunks as $chunk) {
            DB::table('route_stations')->insert($chunk);
        }

        $this->command->info('Imported ' . count($routeStations) . ' route stations');
    }

    private function findRouteByTrainNumber(string $trainNumber): ?array
    {
        static $routes = null;
        static $trainToRoute = null;

        if ($routes === null) {
            $routes = DB::table('routes')->get()->keyBy('id')->toArray();

            // Create train to route mapping
            $trainToRoute = [];
            foreach ($routes as $route) {
                $trainToRoute[$route->train_id] = (array) $route;
            }
        }

        // Try to find route by train number (assuming train numbers match train IDs for now)
        $trainId = (int) $trainNumber;
        if (isset($trainToRoute[$trainId])) {
            return $trainToRoute[$trainId];
        }

        // Fallback to first route if no specific match
        return isset($routes[array_key_first($routes)]) ? (array) $routes[array_key_first($routes)] : null;
    }

    private function findStationByName(string $stationName): ?array
    {
        static $stations = null;

        if ($stations === null) {
            $stations = DB::table('stations')
                ->get()
                ->keyBy(function ($station) {
                    $name = json_decode($station->name, true);
                    return $name['en'] ?? 'unknown';
                })
                ->toArray();
        }

        return isset($stations[$stationName]) ? (array) $stations[$stationName] : null;
    }

    private function parseTime(?string $time): ?string
    {
        if (!$time) return null;

        // Convert time to proper format
        return date('H:i:s', strtotime($time));
    }

    private function calculateStopDuration(?string $arrival, ?string $departure): int
    {
        if (!$arrival || !$departure) return 2; // Default 2 minutes

        $arrivalTime = strtotime($arrival);
        $departureTime = strtotime($departure);

        $duration = ($departureTime - $arrivalTime) / 60; // Convert to minutes

        return max(1, (int) $duration); // At least 1 minute
    }

    private function calculateDistanceFromStart(int $stopOrder): float
    {
        // Estimate distance based on stop order
        // Assuming average 25km between major stops
        return ($stopOrder - 1) * 25.0 + rand(-5, 15);
    }

    private function assignPlatform(string $stationType): string
    {
        return match($stationType) {
            'main' => 'Platform ' . rand(1, 8),
            'regional' => 'Platform ' . rand(1, 4),
            'depot' => 'Platform A',
            default => 'Platform 1'
        };
    }
}