<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Train\Station;

class StationsSeeder extends Seeder
{
    public function run()
    {
        $stations = [
            [
                'code' => 'RUH',
                'name' => [
                    'ar' => 'محطة الرياض المركزية',
                    'en' => 'Riyadh Central Station'
                ],
                'description' => [
                    'ar' => 'المحطة الرئيسية في العاصمة الرياض',
                    'en' => 'Main station in the capital city Riyadh'
                ],
                'city' => [
                    'ar' => 'الرياض',
                    'en' => 'Riyadh'
                ],
                'region' => [
                    'ar' => 'منطقة الرياض',
                    'en' => 'Riyadh Region'
                ],
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'facilities' => ['wifi', 'restaurant', 'parking', 'prayer_area', 'family_area'],
                'order_index' => 1,
            ],
            [
                'code' => 'JED',
                'name' => [
                    'ar' => 'محطة جدة',
                    'en' => 'Jeddah Station'
                ],
                'description' => [
                    'ar' => 'محطة جدة على ساحل البحر الأحمر',
                    'en' => 'Jeddah station on the Red Sea coast'
                ],
                'city' => [
                    'ar' => 'جدة',
                    'en' => 'Jeddah'
                ],
                'region' => [
                    'ar' => 'منطقة مكة المكرمة',
                    'en' => 'Makkah Region'
                ],
                'latitude' => 21.4858,
                'longitude' => 39.1925,
                'facilities' => ['wifi', 'restaurant', 'parking', 'prayer_area'],
                'order_index' => 2,
            ],
            [
                'code' => 'MKK',
                'name' => [
                    'ar' => 'محطة مكة المكرمة',
                    'en' => 'Makkah Station'
                ],
                'description' => [
                    'ar' => 'محطة مكة المكرمة - قلب الإسلام',
                    'en' => 'Makkah Station - Heart of Islam'
                ],
                'city' => [
                    'ar' => 'مكة المكرمة',
                    'en' => 'Makkah'
                ],
                'region' => [
                    'ar' => 'منطقة مكة المكرمة',
                    'en' => 'Makkah Region'
                ],
                'latitude' => 21.3891,
                'longitude' => 39.8579,
                'facilities' => ['wifi', 'restaurant', 'prayer_area', 'family_area', 'atm'],
                'order_index' => 3,
            ],
            [
                'code' => 'DAM',
                'name' => [
                    'ar' => 'محطة الدمام',
                    'en' => 'Dammam Station'
                ],
                'description' => [
                    'ar' => 'محطة الدمام في المنطقة الشرقية',
                    'en' => 'Dammam station in the Eastern Province'
                ],
                'city' => [
                    'ar' => 'الدمام',
                    'en' => 'Dammam'
                ],
                'region' => [
                    'ar' => 'المنطقة الشرقية',
                    'en' => 'Eastern Province'
                ],
                'latitude' => 26.4367,
                'longitude' => 50.1040,
                'facilities' => ['wifi', 'parking', 'prayer_area', 'atm'],
                'order_index' => 4,
            ],
        ];

        foreach ($stations as $stationData) {
            Station::create($stationData);
        }
    }
}