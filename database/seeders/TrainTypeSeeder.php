<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainTypeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Importing train types from Egyptian railway system...');

        $sqliteDb = new \SQLite3('D:\trains.db');
        $trainTypes = $sqliteDb->query("SELECT * FROM Train_Type");

        $typeData = [];
        while ($row = $trainTypes->fetchArray(SQLITE3_ASSOC)) {
            $typeData[] = [
                'name' => json_encode([
                    'en' => $row['Train_Type_En'] ?: 'Standard',
                    'ar' => $row['Train_Type_Ar'] ?: 'عادي'
                ]),
                'code' => $this->generateTypeCode($row['Train_Type_En']),
                'description' => json_encode([
                    'en' => $this->getTypeDescription($row['Train_Type_En'], 'en'),
                    'ar' => $this->getTypeDescription($row['Train_Type_En'], 'ar')
                ]),
                'features' => json_encode($this->getTypeFeatures($row['Train_Type_En'])),
                'comfort_level' => $this->getComfortLevel($row['Train_Type_En']),
                'price_multiplier' => $this->getPriceMultiplier($row['Train_Type_En']),
                'max_speed' => $this->getMaxSpeed($row['Train_Type_En']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Add Qatar-specific train types
        $qatarTypes = [
            [
                'name' => json_encode(['en' => 'Qatar Metro', 'ar' => 'مترو قطر']),
                'code' => 'QM',
                'description' => json_encode([
                    'en' => 'Modern metro system for urban transportation',
                    'ar' => 'نظام مترو حديث للنقل الحضري'
                ]),
                'features' => json_encode(['air_conditioning', 'automated', 'frequent_service', 'accessibility']),
                'comfort_level' => 'premium',
                'price_multiplier' => 1.0,
                'max_speed' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => json_encode(['en' => 'Lusail Tram', 'ar' => 'ترام لوسيل']),
                'code' => 'LT',
                'description' => json_encode([
                    'en' => 'Light rail system connecting Lusail City',
                    'ar' => 'نظام سكك حديدية خفيفة يربط مدينة لوسيل'
                ]),
                'features' => json_encode(['electric', 'eco_friendly', 'modern_design', 'wifi']),
                'comfort_level' => 'premium',
                'price_multiplier' => 1.2,
                'max_speed' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => json_encode(['en' => 'High-Speed Rail', 'ar' => 'السكك الحديدية عالية السرعة']),
                'code' => 'HSR',
                'description' => json_encode([
                    'en' => 'High-speed intercity rail service',
                    'ar' => 'خدمة السكك الحديدية عالية السرعة بين المدن'
                ]),
                'features' => json_encode(['high_speed', 'luxury_seating', 'food_service', 'business_class']),
                'comfort_level' => 'luxury',
                'price_multiplier' => 2.5,
                'max_speed' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        $allTypes = array_merge($typeData, $qatarTypes);

        DB::table('train_types')->insert($allTypes);
        $this->command->info('Imported ' . count($allTypes) . ' train types');
    }

    private function generateTypeCode(string $typeName): string
    {
        $codes = [
            'Distinct' => 'DST',
            'Improved' => 'IMP',
            'AC/Distinct' => 'ACD',
            'AC' => 'AC',
            'VIP' => 'VIP',
            'Sleep' => 'SLP',
            'Passengers' => 'PSG',
        ];

        return $codes[$typeName] ?? 'STD';
    }

    private function getTypeDescription(string $type, string $lang): string
    {
        $descriptions = [
            'en' => [
                'Distinct' => 'Premium service with enhanced comfort and amenities',
                'Improved' => 'Upgraded service with better seating and facilities',
                'AC/Distinct' => 'Air-conditioned premium service with superior comfort',
                'AC' => 'Air-conditioned service for comfortable travel',
                'VIP' => 'Luxury service with exclusive amenities and first-class treatment',
                'Sleep' => 'Overnight service with sleeping accommodations',
                'Passengers' => 'Standard passenger service for regular travel',
            ],
            'ar' => [
                'Distinct' => 'خدمة مميزة مع راحة محسنة ووسائل راحة',
                'Improved' => 'خدمة محسنة مع مقاعد ومرافق أفضل',
                'AC/Distinct' => 'خدمة مميزة مكيفة مع راحة فائقة',
                'AC' => 'خدمة مكيفة للسفر المريح',
                'VIP' => 'خدمة فاخرة مع وسائل راحة حصرية ومعاملة درجة أولى',
                'Sleep' => 'خدمة ليلية مع أماكن للنوم',
                'Passengers' => 'خدمة ركاب عادية للسفر المنتظم',
            ]
        ];

        return $descriptions[$lang][$type] ?? ($lang === 'en' ? 'Standard train service' : 'خدمة قطار عادية');
    }

    private function getTypeFeatures(string $type): array
    {
        $features = [
            'Distinct' => ['premium_seating', 'enhanced_comfort', 'priority_boarding'],
            'Improved' => ['comfortable_seating', 'improved_facilities', 'better_service'],
            'AC/Distinct' => ['air_conditioning', 'premium_seating', 'enhanced_comfort', 'priority_boarding'],
            'AC' => ['air_conditioning', 'comfortable_seating'],
            'VIP' => ['luxury_seating', 'food_service', 'wifi', 'entertainment', 'exclusive_lounge'],
            'Sleep' => ['sleeping_berths', 'bedding', 'privacy_curtains', 'overnight_service'],
            'Passengers' => ['basic_seating', 'standard_service'],
        ];

        return $features[$type] ?? ['basic_seating'];
    }

    private function getComfortLevel(string $type): string
    {
        return match($type) {
            'VIP' => 'luxury',
            'Distinct', 'AC/Distinct', 'Sleep' => 'premium',
            'Improved', 'AC' => 'comfort',
            default => 'standard'
        };
    }

    private function getPriceMultiplier(string $type): float
    {
        return match($type) {
            'VIP' => 3.0,
            'Sleep' => 2.5,
            'AC/Distinct' => 2.0,
            'Distinct' => 1.8,
            'AC' => 1.5,
            'Improved' => 1.3,
            default => 1.0
        };
    }

    private function getMaxSpeed(string $type): int
    {
        return match($type) {
            'VIP' => 160,
            'Distinct', 'Improved' => 120,
            'AC/Distinct', 'AC' => 100,
            'Sleep' => 90,
            default => 80
        };
    }
}