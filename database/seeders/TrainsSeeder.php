<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Train\Train;

class TrainsSeeder extends Seeder
{
    public function run()
    {
        $trains = [
            [
                'number' => 'HH001',
                'name' => [
                    'ar' => 'قطار الحرمين السريع',
                    'en' => 'Haramain High Speed Rail'
                ],
                'description' => [
                    'ar' => 'قطار سريع يربط بين مكة المكرمة والمدينة المنورة',
                    'en' => 'High-speed train connecting Makkah and Madinah'
                ],
                'type' => 'high_speed',
                'operator' => [
                    'ar' => 'الخطوط الحديدية السعودية',
                    'en' => 'Saudi Railways Organization'
                ],
                'capacity' => 417,
                'max_speed' => 300,
                'amenities' => ['wifi', 'dining', 'ac', 'prayer_area', 'family_area', 'business_class'],
                'status' => 'active',
            ],
            [
                'number' => 'SAR101',
                'name' => [
                    'ar' => 'القطار الشرقي',
                    'en' => 'Eastern Express'
                ],
                'description' => [
                    'ar' => 'قطار ركاب يربط الرياض بالدمام',
                    'en' => 'Passenger train connecting Riyadh to Dammam'
                ],
                'type' => 'passenger',
                'operator' => [
                    'ar' => 'الخطوط الحديدية السعودية',
                    'en' => 'Saudi Railways Organization'
                ],
                'capacity' => 250,
                'max_speed' => 160,
                'amenities' => ['wifi', 'ac', 'prayer_area', 'disabled_access'],
                'status' => 'active',
            ],
            [
                'number' => 'SAR201',
                'name' => [
                    'ar' => 'القطار الغربي',
                    'en' => 'Western Line'
                ],
                'description' => [
                    'ar' => 'خط القطار الغربي من الرياض إلى جدة',
                    'en' => 'Western railway line from Riyadh to Jeddah'
                ],
                'type' => 'passenger',
                'operator' => [
                    'ar' => 'الخطوط الحديدية السعودية',
                    'en' => 'Saudi Railways Organization'
                ],
                'capacity' => 300,
                'max_speed' => 200,
                'amenities' => ['wifi', 'dining', 'ac', 'prayer_area', 'family_area', 'power_outlets'],
                'status' => 'active',
            ],
            [
                'number' => 'METRO01',
                'name' => [
                    'ar' => 'مترو الرياض - الخط الأزرق',
                    'en' => 'Riyadh Metro - Blue Line'
                ],
                'description' => [
                    'ar' => 'خط المترو الأزرق في الرياض',
                    'en' => 'Blue line of Riyadh Metro system'
                ],
                'type' => 'metro',
                'operator' => [
                    'ar' => 'الهيئة الملكية لمدينة الرياض',
                    'en' => 'Royal Commission for Riyadh City'
                ],
                'capacity' => 200,
                'max_speed' => 80,
                'amenities' => ['wifi', 'ac', 'disabled_access'],
                'status' => 'active',
            ],
        ];

        foreach ($trains as $trainData) {
            Train::create($trainData);
        }
    }
}