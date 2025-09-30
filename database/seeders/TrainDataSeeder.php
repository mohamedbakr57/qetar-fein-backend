<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainDataSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('stations')->count() == 0) {
            $this->importStations();
        } else {
            $this->command->info('Stations already imported, skipping...');
        }

        if (DB::table('trains')->count() == 0) {
            $this->importTrains();
        } else {
            $this->command->info('Trains already imported, skipping...');
        }

        if (DB::table('routes')->count() == 0) {
            $this->importRoutes();
        } else {
            $this->command->info('Routes already imported, skipping...');
        }

        if (DB::table('schedules')->count() == 0) {
            $this->importSchedules();
        } else {
            $this->command->info('Schedules already imported, skipping...');
        }
    }

    private function importStations(): void
    {
        $this->command->info('Importing stations...');

        // Get station data from SQLite
        $sqliteDb = new \SQLite3('D:\trains.db');
        $stations = $sqliteDb->query("
            SELECT s.*, sc.Stop_Category_En, sc.Stop_Category_Ar
            FROM Station s
            LEFT JOIN Stop_Category sc ON s.Stop_Category_ID = sc.Stop_Category_ID
        ");

        $stationData = [];
        while ($row = $stations->fetchArray(SQLITE3_ASSOC)) {
            $coordinates = $this->getStationCoordinates($row['Station_En']);
            $stationData[] = [
                'code' => strtoupper(substr(str_replace(' ', '', $row['Station_En'] ?? 'UNK'), 0, 3)) . sprintf('%03d', $row['Station_ID']),
                'name' => json_encode([
                    'en' => $row['Station_En'] ?? 'Unknown',
                    'ar' => $row['Station_Ar'] ?? 'غير معروف'
                ]),
                'description' => json_encode([
                    'en' => 'Railway station in Egypt',
                    'ar' => 'محطة سكة حديد في مصر'
                ]),
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
                'elevation' => 0,
                'city' => json_encode([
                    'en' => $row['Station_En'] ?? 'Unknown',
                    'ar' => $row['Station_Ar'] ?? 'غير معروف'
                ]),
                'region' => json_encode([
                    'en' => 'Egypt',
                    'ar' => 'مصر'
                ]),
                'country_code' => 'EG',
                'timezone' => 'Africa/Cairo',
                'facilities' => json_encode($this->getStationFacilities($row['Stop_Category_En'] ?? 'Major Cities')),
                'status' => 'active',
                'order_index' => $row['Station_ID'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('stations')->insert($stationData);
        $this->command->info('Imported ' . count($stationData) . ' stations');
    }

    private function importTrains(): void
    {
        $this->command->info('Importing trains...');

        $sqliteDb = new \SQLite3('D:\trains.db');
        $trains = $sqliteDb->query("
            SELECT t.*, tt.Train_Type_En, tt.Train_Type_Ar, tn.Train_Note_En, tn.Train_Note_Ar
            FROM Train t
            LEFT JOIN Train_Type tt ON t.Train_Type_ID = tt.Train_Type_ID
            LEFT JOIN Train_Note tn ON t.Train_Note_ID = tn.Train_Note_ID
        ");

        $trainData = [];
        while ($row = $trains->fetchArray(SQLITE3_ASSOC)) {
            $trainData[] = [
                'number' => $row['Train_No'],
                'name' => json_encode([
                    'en' => 'Train ' . $row['Train_No'],
                    'ar' => 'قطار ' . $row['Train_No']
                ]),
                'description' => json_encode([
                    'en' => $row['Train_Note_En'] ?? 'Egyptian train service',
                    'ar' => $row['Train_Note_Ar'] ?? 'خدمة قطارات مصرية'
                ]),
                'type' => $this->mapTrainType($row['Train_Type_En'] ?? 'Passengers'),
                'operator' => json_encode([
                    'en' => 'Egyptian National Railways',
                    'ar' => 'السكك الحديدية المصرية'
                ]),
                'capacity' => $this->getTrainCapacity($row['Train_Type_En'] ?? 'Passengers'),
                'max_speed' => $this->getMaxSpeed($row['Train_Type_En'] ?? 'Passengers'),
                'amenities' => json_encode($this->getTrainAmenities($row['Train_Type_En'] ?? 'Passengers')),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('trains')->insert($trainData);
        $this->command->info('Imported ' . count($trainData) . ' trains');
    }

    private function importRoutes(): void
    {
        $this->command->info('Importing routes...');

        // Get imported trains from our database
        $trains = DB::table('trains')->get();
        $stations = DB::table('stations')->limit(20)->get(); // Get first 20 stations for demo routes

        $routeData = [];
        $routeId = 1;

        // Create sample routes for each train using our stations
        foreach ($trains as $train) {
            if ($routeId > count($stations) - 1) break; // Don't exceed available stations

            $originStation = $stations[$routeId - 1];
            $destinationStation = $stations[$routeId % count($stations)];

            $originName = json_decode($originStation->name, true);
            $destinationName = json_decode($destinationStation->name, true);

            $routeData[] = [
                'train_id' => $train->id,
                'origin_station_id' => $originStation->id,
                'destination_station_id' => $destinationStation->id,
                'name' => json_encode([
                    'en' => $originName['en'] . ' - ' . $destinationName['en'],
                    'ar' => $originName['ar'] . ' - ' . $destinationName['ar']
                ]),
                'description' => json_encode([
                    'en' => 'Railway route from ' . $originName['en'] . ' to ' . $destinationName['en'],
                    'ar' => 'خط السكة الحديد من ' . $originName['ar'] . ' إلى ' . $destinationName['ar']
                ]),
                'distance_km' => rand(50, 800) + (rand(0, 99) / 100),
                'estimated_duration_minutes' => rand(60, 480),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $routeId++;
            if (count($routeData) >= 100) break; // Limit to 100 routes for now
        }

        DB::table('routes')->insert($routeData);
        $this->command->info('Imported ' . count($routeData) . ' routes');
    }

    private function importSchedules(): void
    {
        $this->command->info('Importing schedules...');

        // Get imported routes from our database
        $routes = DB::table('routes')->get();

        $scheduleData = [];
        $classes = ['economy', 'business', 'first'];

        foreach ($routes as $route) {
            foreach ($classes as $class) {
                $departureTime = sprintf('%02d:%02d:00', rand(5, 22), rand(0, 59));
                $arrivalHour = (int)substr($departureTime, 0, 2) + rand(2, 8);
                if ($arrivalHour >= 24) $arrivalHour -= 24;
                $arrivalTime = sprintf('%02d:%02d:00', $arrivalHour, rand(0, 59));

                $scheduleData[] = [
                    'route_id' => $route->id,
                    'departure_time' => $departureTime,
                    'arrival_time' => $arrivalTime,
                    'days_of_week' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                    'valid_from' => now()->format('Y-m-d'),
                    'valid_until' => now()->addYear()->format('Y-m-d'),
                    'price_adult' => match($class) {
                        'economy' => rand(50, 150),
                        'business' => rand(100, 300),
                        'first' => rand(200, 500)
                    },
                    'price_child' => match($class) {
                        'economy' => rand(25, 75),
                        'business' => rand(50, 150),
                        'first' => rand(100, 250)
                    },
                    'currency' => 'QAR',
                    'booking_class' => $class,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($scheduleData, 1000);
        foreach ($chunks as $chunk) {
            DB::table('schedules')->insert($chunk);
        }

        $this->command->info('Imported ' . count($scheduleData) . ' schedules');
    }

    private function mapStationType(string $category): string
    {
        return match($category) {
            'Express' => 'main',
            'Direct' => 'main',
            'Major Cities' => 'main',
            'Suburbs' => 'regional',
            'Pseudo' => 'depot',
            default => 'regional'
        };
    }

    private function mapTrainType(string $type): string
    {
        return match($type) {
            'Distinct' => 'passenger',
            'Improved' => 'passenger',
            'AC/Distinct' => 'passenger',
            'AC' => 'passenger',
            'VIP' => 'high_speed',
            'Sleep' => 'passenger',
            'Passengers' => 'passenger',
            default => 'passenger'
        };
    }

    private function getTrainCapacity(?string $type): int
    {
        return match($type) {
            'VIP' => 150,
            'AC/Distinct', 'AC' => 300,
            'Distinct', 'Improved' => 400,
            'Sleep' => 200,
            default => 500
        };
    }

    private function getTrainAmenities(?string $type): array
    {
        $amenities = ['seats', 'luggage_storage'];

        if ($type && (str_contains($type, 'AC') || $type === 'VIP')) {
            $amenities[] = 'air_conditioning';
        }

        if ($type === 'VIP') {
            $amenities = array_merge($amenities, ['wifi', 'food_service', 'power_outlets']);
        }

        if ($type === 'Sleep') {
            $amenities[] = 'sleeping_berths';
        }

        return $amenities;
    }

    private function getMaxSpeed(?string $type): int
    {
        return match($type) {
            'VIP' => 160,
            'Express', 'Distinct', 'Improved' => 120,
            'AC/Distinct', 'AC' => 100,
            default => 80
        };
    }

    private function getStationCoordinates(string $stationName): array
    {
        // Sample coordinates for major Egyptian cities
        $coordinates = [
            'Cairo' => ['lat' => 30.0444, 'lng' => 31.2357],
            'Alexandria' => ['lat' => 31.2001, 'lng' => 29.9187],
            'Aswan' => ['lat' => 24.0889, 'lng' => 32.8998],
            'Luxor' => ['lat' => 25.6872, 'lng' => 32.6396],
            'Tanta' => ['lat' => 30.7865, 'lng' => 31.0004],
            'Mansoura' => ['lat' => 31.0409, 'lng' => 31.3785],
        ];

        return $coordinates[$stationName] ?? ['lat' => 30.0444, 'lng' => 31.2357];
    }

    private function getStationFacilities(?string $category): array
    {
        $facilities = ['platform', 'waiting_area'];

        if ($category === 'Express' || $category === 'Major Cities') {
            $facilities = array_merge($facilities, ['restrooms', 'food_court', 'parking']);
        }

        return $facilities;
    }

    private function translateStationName(string $englishName): string
    {
        $translations = [
            'Cairo' => 'القاهرة',
            'Alexandria' => 'الأسكندرية',
            'Aswan' => 'أسوان',
            'Luxor' => 'الأقصر',
            'Tanta' => 'طنطا',
            'Mansoura' => 'المنصورة',
        ];

        return $translations[$englishName] ?? $englishName;
    }

    private function calculateDistance(string $origin, string $destination): float
    {
        // Simplified distance calculation - in real implementation would use coordinates
        return rand(50, 800) + (rand(0, 99) / 100);
    }

    private function calculateDuration(int $trainId): int
    {
        $sqliteDb = new \SQLite3('D:\trains.db');
        $result = $sqliteDb->query("
            SELECT
                MIN(s.Departure_Time) as start_time,
                MAX(s.Arrival_Time) as end_time
            FROM Stop s
            WHERE s.Train_ID = $trainId
        ");

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && $row['start_time'] && $row['end_time']) {
            $start = strtotime($row['start_time']);
            $end = strtotime($row['end_time']);
            return max(30, ($end - $start) / 60); // Duration in minutes
        }

        return rand(60, 480); // Random duration between 1-8 hours
    }

    private function getStationIdByName(string $stationName): ?int
    {
        static $stationMap = null;

        if ($stationMap === null) {
            $stations = DB::table('stations')->get();
            $stationMap = [];
            foreach ($stations as $station) {
                $name = json_decode($station->name, true);
                $stationMap[$name['en']] = $station->id;
            }
        }

        return $stationMap[$stationName] ?? null;
    }
}