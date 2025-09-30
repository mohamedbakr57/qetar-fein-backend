<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessRulesTrainSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Implementing train system according to business rules...');

        $sqlitePath = env('SQLITE_IMPORT_PATH', 'D:\trains.db');

        if (!file_exists($sqlitePath)) {
            $this->command->error("SQLite database not found at: {$sqlitePath}");
            $this->command->info('Please set SQLITE_IMPORT_PATH in your .env file or place trains.db in the project root');
            return;
        }

        $sqliteDb = new \SQLite3($sqlitePath);

        $this->clearUnnecessaryData();
        $this->importCoreData($sqliteDb);

        $sqliteDb->close();
        $this->command->info('Completed business rules implementation');
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

    private function importCoreData($sqliteDb): void
    {
        $this->importStations($sqliteDb);
        $this->importTrains($sqliteDb);
        $this->importStops($sqliteDb);
        $this->importNoStops($sqliteDb);
    }

    private function importStations($sqliteDb): void
    {
        $this->command->info('Importing stations with stop categories...');

        $stations = $sqliteDb->query("
            SELECT s.*, sc.Stop_Category_En, sc.Stop_Category_Ar
            FROM Station s
            LEFT JOIN Stop_Category sc ON s.Stop_Category_ID = sc.Stop_Category_ID
        ");

        $stationData = [];
        while ($row = $stations->fetchArray(SQLITE3_ASSOC)) {
            $coordinates = $this->getStationCoordinates($row['Station_En']);
            $stationData[] = [
                'id' => $row['Station_ID'],
                'code' => strtoupper(substr(str_replace(' ', '', $row['Station_En'] ?? 'UNK'), 0, 3)) . sprintf('%03d', $row['Station_ID']),
                'name' => json_encode([
                    'en' => $row['Station_En'] ?? 'Unknown',
                    'ar' => $row['Station_Ar'] ?? 'غير معروف'
                ]),
                'description' => json_encode([
                    'en' => 'Railway station in Egypt - Category: ' . ($row['Stop_Category_En'] ?? 'Standard'),
                    'ar' => 'محطة سكة حديد في مصر - فئة: ' . ($row['Stop_Category_Ar'] ?? 'عادية')
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

    private function importTrains($sqliteDb): void
    {
        $this->command->info('Importing trains with types and notes...');

        $trains = $sqliteDb->query("
            SELECT t.*, tt.Train_Type_En, tt.Train_Type_Ar, tn.Train_Note_En, tn.Train_Note_Ar
            FROM Train t
            LEFT JOIN Train_Type tt ON t.Train_Type_ID = tt.Train_Type_ID
            LEFT JOIN Train_Note tn ON t.Train_Note_ID = tn.Train_Note_ID
        ");

        $trainData = [];
        while ($row = $trains->fetchArray(SQLITE3_ASSOC)) {
            $trainData[] = [
                'id' => $row['Train_ID'],
                'number' => $row['Train_No'],
                'name' => json_encode([
                    'en' => 'Train ' . $row['Train_No'] . ' (' . ($row['Train_Type_En'] ?? 'Standard') . ')',
                    'ar' => 'قطار ' . $row['Train_No'] . ' (' . ($row['Train_Type_Ar'] ?? 'عادي') . ')'
                ]),
                'description' => json_encode([
                    'en' => $row['Train_Note_En'] ?? 'Egyptian National Railways service',
                    'ar' => $row['Train_Note_Ar'] ?? 'خدمة السكك الحديدية المصرية'
                ]),
                'type' => $this->mapTrainType($row['Train_Type_En'] ?? 'passenger'),
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

    private function importStops($sqliteDb): void
    {
        $this->command->info('Importing train stops (journey sequences)...');

        $stops = $sqliteDb->query("
            SELECT
                s.Train_ID,
                s.Station_ID,
                s.Stop_No,
                s.Arrival_Time,
                s.Departure_Time,
                st.Station_En,
                sc.Stop_Category_En
            FROM Stop s
            JOIN Station st ON s.Station_ID = st.Station_ID
            LEFT JOIN Stop_Category sc ON st.Stop_Category_ID = sc.Stop_Category_ID
            ORDER BY s.Train_ID, s.Stop_No
        ");

        $stopData = [];
        while ($row = $stops->fetchArray(SQLITE3_ASSOC)) {
            $stopData[] = [
                'train_id' => $row['Train_ID'],
                'station_id' => $row['Station_ID'],
                'stop_number' => (int) $row['Stop_No'],
                'arrival_time' => $this->parseTime($row['Arrival_Time']),
                'departure_time' => $this->parseTime($row['Departure_Time']),
                'platform' => 'Platform ' . rand(1, 8),
                'stop_duration_minutes' => $this->calculateStopDuration(
                    $row['Arrival_Time'],
                    $row['Departure_Time']
                ),
                'is_major_stop' => $this->isMajorStop($row['Stop_Category_En']),
                'notes' => $this->getStopNotes($row['Station_En']),
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

        // Insert remaining data
        if (!empty($stopData)) {
            DB::table('stops')->insert($stopData);
        }

        $total = DB::table('stops')->count();
        $this->command->info('Imported total of ' . $total . ' train stops');
    }

    private function importNoStops($sqliteDb): void
    {
        $this->command->info('Importing no-stop records (express behavior)...');

        // Create no_stops table if it doesn't exist
        if (!DB::getSchemaBuilder()->hasTable('no_stops')) {
            DB::getSchemaBuilder()->create('no_stops', function ($table) {
                $table->id();
                $table->foreignId('train_id')->constrained('trains');
                $table->integer('stop_number');
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->unique(['train_id', 'stop_number'], 'unique_train_no_stop');
            });
        }

        // Check if No_Stops view exists in SQLite
        $noStopsQuery = $sqliteDb->query("
            SELECT name FROM sqlite_master
            WHERE type='table' AND name='No_Stops'
        ");

        if ($noStopsQuery->fetchArray()) {
            $noStops = $sqliteDb->query("
                SELECT Train_ID, Stop_No
                FROM No_Stops
            ");

            $noStopData = [];
            while ($row = $noStops->fetchArray(SQLITE3_ASSOC)) {
                $noStopData[] = [
                    'train_id' => $row['Train_ID'],
                    'stop_number' => (int) $row['Stop_No'],
                    'reason' => 'Express service - no passenger boarding/alighting',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($noStopData)) {
                DB::table('no_stops')->insert($noStopData);
                $this->command->info('Imported ' . count($noStopData) . ' no-stop records');
            }
        } else {
            $this->command->info('No no-stop data found in source database');
        }
    }

    // Helper methods (same as before but simplified)
    private function getStationCoordinates(string $stationName): array
    {
        $coordinates = [
            'Cairo' => ['lat' => 30.0444, 'lng' => 31.2357],
            'Alexandria' => ['lat' => 31.2001, 'lng' => 29.9187],
            'Aswan' => ['lat' => 24.0889, 'lng' => 32.8998],
            'Luxor' => ['lat' => 25.6872, 'lng' => 32.6396],
            'Tanta' => ['lat' => 30.7865, 'lng' => 31.0004],
            'Mansoura' => ['lat' => 31.0409, 'lng' => 31.3785],
        ];

        return $coordinates[$stationName] ?? ['lat' => 30.0444 + (rand(-500, 500) / 1000), 'lng' => 31.2357 + (rand(-500, 500) / 1000)];
    }

    private function getStationFacilities(?string $category): array
    {
        $facilities = ['platform', 'waiting_area'];
        if ($category === 'Express' || $category === 'Major Cities') {
            $facilities = array_merge($facilities, ['restrooms', 'food_court', 'parking']);
        }
        return $facilities;
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

    private function parseTime(?string $time): ?string
    {
        if (!$time) return null;
        return date('H:i:s', strtotime($time));
    }

    private function calculateStopDuration(?string $arrival, ?string $departure): int
    {
        if (!$arrival || !$departure) return 5;

        $arrivalTime = strtotime($arrival);
        $departureTime = strtotime($departure);

        if ($departureTime <= $arrivalTime) {
            return 5;
        }

        $duration = ($departureTime - $arrivalTime) / 60;
        return max(1, (int) $duration);
    }

    private function isMajorStop(?string $category): bool
    {
        return in_array($category, ['Express', 'Major Cities', 'Direct']);
    }

    private function getStopNotes(string $stationName): ?string
    {
        $notesData = [
            'Cairo' => [
                'en' => 'Main terminal station - all services available',
                'ar' => 'المحطة الرئيسية - جميع الخدمات متاحة'
            ],
            'Alexandria' => [
                'en' => 'Coastal terminus - Mediterranean gateway',
                'ar' => 'محطة ساحلية - بوابة البحر المتوسط'
            ],
            'Aswan' => [
                'en' => 'Southern terminus - Nile cruise connections',
                'ar' => 'المحطة الجنوبية - رحلات النيل النهرية'
            ],
            'Luxor' => [
                'en' => 'Tourist hub - Valley of the Kings access',
                'ar' => 'مركز سياحي - مدخل وادي الملوك'
            ],
        ];

        $note = $notesData[$stationName] ?? null;
        return $note ? json_encode($note) : null;
    }
}