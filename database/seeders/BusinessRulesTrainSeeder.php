<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessRulesTrainSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Implementing train system according to business rules...');

        $stationsJsonPath = base_path('stations.json');
        $trainsJsonPath = base_path('trains_edited.json');

        if (!file_exists($stationsJsonPath)) {
            $this->command->warn("Stations JSON file not found at: {$stationsJsonPath}");
            $this->command->info('Skipping import.');
            return;
        }

        if (!file_exists($trainsJsonPath)) {
            $this->command->warn("Trains JSON file not found at: {$trainsJsonPath}");
            $this->command->info('Skipping import.');
            return;
        }

        try {
            $stationsJson = json_decode(file_get_contents($stationsJsonPath), true);
            $trainsJson = json_decode(file_get_contents($trainsJsonPath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command->error('Error parsing JSON files: ' . json_last_error_msg());
                return;
            }

            $this->clearUnnecessaryData();
            $this->importCoreData($stationsJson, $trainsJson);

            $this->command->info('Completed business rules implementation');
        } catch (\Exception $e) {
            $this->command->error('Error importing from JSON files: ' . $e->getMessage());
            $this->command->info('Skipping import.');
        }
    }

    private function clearUnnecessaryData(): void
    {
        $this->command->info('Clearing existing train and station data for business rules import...');
        $this->command->info('Preserving assignments and communities features...');

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Clear business rules tables for proper import
        DB::table('no_stops')->delete();
        DB::table('stops')->delete();
        DB::table('trains')->delete();
        DB::table('stations')->delete();

        // DO NOT TOUCH: assignments, communities, users, and related tables
        // These remain intact as separate features

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    private function importCoreData(array $stationsJson, array $trainsJson): void
    {
        $this->importStations($stationsJson);
        $this->importTrains($trainsJson);
        $this->importStops($trainsJson);
    }

    private function importStations(array $stationsJson): void
    {
        $this->command->info('Importing stations from JSON...');

        $stationData = [];
        foreach ($stationsJson as $index => $stationName) {
            // Trim whitespace from station names
            $stationName = trim($stationName);

            // Skip empty station names
            if (empty($stationName)) {
                continue;
            }

            $stationId = $index + 1;
            $coordinates = $this->getStationCoordinates($stationName);

            $stationData[] = [
                'id' => $stationId,
                'code' => strtoupper(substr(str_replace([' ', '-'], '', $stationName), 0, 3)) . sprintf('%03d', $stationId),
                'name' => json_encode([
                    'en' => $stationName,
                    'ar' => $stationName
                ]),
                'description' => json_encode([
                    'en' => 'Railway station in Egypt',
                    'ar' => 'محطة سكة حديد في مصر'
                ]),
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
                'elevation' => 0,
                'city' => json_encode([
                    'en' => $stationName,
                    'ar' => $stationName
                ]),
                'region' => json_encode([
                    'en' => 'Egypt',
                    'ar' => 'مصر'
                ]),
                'country_code' => 'EG',
                'timezone' => 'Africa/Cairo',
                'facilities' => json_encode($this->getStationFacilities('Standard')),
                'status' => 'active',
                'order_index' => $stationId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in batches
        $chunks = array_chunk($stationData, 100);
        foreach ($chunks as $chunk) {
            DB::table('stations')->insert($chunk);
        }

        $this->command->info('Imported ' . count($stationData) . ' stations');
    }

    private function importTrains(array $trainsJson): void
    {
        $this->command->info('Importing trains from JSON...');

        $trainData = [];
        foreach ($trainsJson as $index => $train) {
            $trainId = $index + 1;
            $trainNumber = $train['number'];
            $trainType = $train['type'] ?? 'passenger';

            $trainData[] = [
                'id' => $trainId,
                'number' => $trainNumber,
                'name' => json_encode([
                    'en' => 'Train ' . $trainNumber . ' (' . $trainType . ')',
                    'ar' => 'قطار ' . $trainNumber . ' (' . $trainType . ')'
                ]),
                'description' => json_encode([
                    'en' => 'Egyptian National Railways service',
                    'ar' => 'خدمة السكك الحديدية المصرية'
                ]),
                'type' => $this->mapTrainType($trainType),
                'operator' => json_encode([
                    'en' => 'Egyptian National Railways',
                    'ar' => 'السكك الحديدية المصرية'
                ]),
                'capacity' => $this->getTrainCapacity($trainType),
                'max_speed' => $this->getMaxSpeed($trainType),
                'amenities' => json_encode($this->getTrainAmenities($trainType)),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('trains')->insert($trainData);
        $this->command->info('Imported ' . count($trainData) . ' trains');
    }

    private function importStops(array $trainsJson): void
    {
        $this->command->info('Importing train stops from JSON...');

        // Build station name to ID mapping (with trimmed names)
        $stationMapping = [];
        $stations = DB::table('stations')->get();
        foreach ($stations as $station) {
            $nameData = json_decode($station->name, true);
            $trimmedName = trim($nameData['en']);
            $stationMapping[$trimmedName] = $station->id;
        }

        $stopData = [];
        $skippedStopsCount = 0;
        $missingStations = [];
        $corruptedTrains = [];

        foreach ($trainsJson as $index => $train) {
            $trainId = $index + 1;
            $trainNumber = $train['number'] ?? $trainId;

            if (!isset($train['points']) || !is_array($train['points'])) {
                continue;
            }

            $totalPoints = count($train['points']);
            $emptyPoints = 0;

            foreach ($train['points'] as $stopIndex => $point) {
                $stationName = trim($point['stationName'] ?? '');

                // Track empty station names
                if (empty($stationName)) {
                    $emptyPoints++;
                    $skippedStopsCount++;
                    continue;
                }

                $stationId = $stationMapping[$stationName] ?? null;

                if (!$stationId) {
                    if (!isset($missingStations[$stationName])) {
                        $missingStations[$stationName] = 0;
                    }
                    $missingStations[$stationName]++;
                    $skippedStopsCount++;
                    continue;
                }

                $stopData[] = [
                    'train_id' => $trainId,
                    'station_id' => $stationId,
                    'stop_number' => $stopIndex + 1,
                    'arrival_time' => $this->parseTime($point['arrivingTime']),
                    'departure_time' => $this->parseTime($point['departingTime']),
                    'platform' => 'Platform ' . rand(1, 8),
                    'stop_duration_minutes' => $this->calculateStopDuration(
                        $point['arrivingTime'],
                        $point['departingTime']
                    ),
                    'is_major_stop' => false,
                    'notes' => $this->getStopNotes($stationName),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Insert in batches to avoid memory issues
                if (count($stopData) >= 1000) {
                    DB::table('stops')->insert($stopData);
                    $this->command->info('Inserted batch of ' . count($stopData) . ' stops');
                    $stopData = [];
                }
            }

            // Track trains with significant data corruption
            if ($emptyPoints > 0) {
                $corruptionPercent = round(($emptyPoints / $totalPoints) * 100, 1);
                $corruptedTrains[] = [
                    'number' => $trainNumber,
                    'empty' => $emptyPoints,
                    'total' => $totalPoints,
                    'percent' => $corruptionPercent
                ];
            }
        }

        // Insert remaining data
        if (!empty($stopData)) {
            DB::table('stops')->insert($stopData);
        }

        // Report summary
        $this->command->newLine();

        if ($skippedStopsCount > 0) {
            $this->command->warn("⚠ Skipped {$skippedStopsCount} stops due to missing/empty station names");
        }

        if (!empty($missingStations)) {
            $this->command->warn("Missing stations in JSON: " . implode(', ', array_keys($missingStations)));
        }

        if (!empty($corruptedTrains)) {
            $this->command->warn("⚠ Found " . count($corruptedTrains) . " trains with corrupted data (empty station names):");
            foreach ($corruptedTrains as $corrupt) {
                $this->command->warn("  - Train {$corrupt['number']}: {$corrupt['empty']}/{$corrupt['total']} stops empty ({$corrupt['percent']}%)");
            }
            $this->command->info("💡 Consider cleaning the trains_edited.json file to fix these issues.");
        }

        $this->command->newLine();
        $total = DB::table('stops')->count();
        $this->command->info("✓ Imported total of {$total} train stops successfully");
    }

    // Helper methods
    private function getStationCoordinates(string $stationName): array
    {
        // Generate placeholder coordinates (centered around Egypt)
        return [
            'lat' => 26.0 + (rand(0, 800) / 100),
            'lng' => 30.0 + (rand(0, 400) / 100)
        ];
    }

    private function getStationFacilities(string $category): array
    {
        return ['platform', 'waiting_area'];
    }

    private function mapTrainType(string $type): string
    {
        return match($type) {
            'VIP' => 'high_speed',
            default => 'passenger'
        };
    }

    private function getTrainCapacity(string $type): int
    {
        return match($type) {
            'VIP' => 150,
            'AC', 'AC/Distinct' => 300,
            'Improved', 'Distinct' => 400,
            default => 500
        };
    }

    private function getTrainAmenities(string $type): array
    {
        $amenities = ['seats', 'luggage_storage'];

        if (str_contains($type, 'AC') || $type === 'VIP') {
            $amenities[] = 'air_conditioning';
        }

        if ($type === 'VIP') {
            $amenities[] = 'wifi';
            $amenities[] = 'food_service';
        }

        return $amenities;
    }

    private function getMaxSpeed(string $type): int
    {
        return match($type) {
            'VIP' => 160,
            'Improved', 'Distinct' => 120,
            'AC', 'AC/Distinct' => 100,
            default => 80
        };
    }

    private function parseTime(?string $time): ?string
    {
        if (!$time) return null;
        return date('H:i:s', strtotime($time));
    }

    private function calculateStopDuration(?string $arrival, ?string $departure): int
    {
        if (!$arrival || !$departure) return 1;

        $arrivalTime = strtotime($arrival);
        $departureTime = strtotime($departure);

        if ($departureTime <= $arrivalTime) {
            return 1;
        }

        return max(1, (int)(($departureTime - $arrivalTime) / 60));
    }

    private function getStopNotes(string $stationName): ?string
    {
        return null;
    }
}