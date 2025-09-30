<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgesSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'slug' => 'first_trip',
                'name' => [
                    'ar' => 'أول رحلة',
                    'en' => 'First Trip'
                ],
                'description' => [
                    'ar' => 'أكمل رحلتك الأولى بنجاح',
                    'en' => 'Complete your first trip successfully'
                ],
                'icon' => 'heroicon-o-star',
                'category' => 'travel',
                'criteria' => ['completed_assignments' => 1],
                'rarity' => 'common',
                'points_reward' => 10,
                'is_active' => true,
            ],
            [
                'slug' => 'regular_traveler',
                'name' => [
                    'ar' => 'مسافر منتظم',
                    'en' => 'Regular Traveler'
                ],
                'description' => [
                    'ar' => 'أكمل 5 رحلات بنجاح',
                    'en' => 'Complete 5 trips successfully'
                ],
                'icon' => 'heroicon-o-user',
                'category' => 'travel',
                'criteria' => ['completed_assignments' => 5],
                'rarity' => 'common',
                'points_reward' => 25,
                'is_active' => true,
            ],
            [
                'slug' => 'frequent_traveler',
                'name' => [
                    'ar' => 'مسافر متكرر',
                    'en' => 'Frequent Traveler'
                ],
                'description' => [
                    'ar' => 'أكمل 10 رحلات بنجاح',
                    'en' => 'Complete 10 trips successfully'
                ],
                'icon' => 'heroicon-o-fire',
                'category' => 'travel',
                'criteria' => ['completed_assignments' => 10],
                'rarity' => 'rare',
                'points_reward' => 50,
                'is_active' => true,
            ],
            [
                'slug' => 'train_enthusiast',
                'name' => [
                    'ar' => 'عاشق القطارات',
                    'en' => 'Train Enthusiast'
                ],
                'description' => [
                    'ar' => 'أكمل 25 رحلة بنجاح',
                    'en' => 'Complete 25 trips successfully'
                ],
                'icon' => 'heroicon-o-heart',
                'category' => 'travel',
                'criteria' => ['completed_assignments' => 25],
                'rarity' => 'epic',
                'points_reward' => 100,
                'is_active' => true,
            ],
            [
                'slug' => 'rail_master',
                'name' => [
                    'ar' => 'خبير السكك الحديدية',
                    'en' => 'Rail Master'
                ],
                'description' => [
                    'ar' => 'أكمل 50 رحلة بنجاح',
                    'en' => 'Complete 50 trips successfully'
                ],
                'icon' => 'heroicon-o-academic-cap',
                'category' => 'travel',
                'criteria' => ['completed_assignments' => 50],
                'rarity' => 'legendary',
                'points_reward' => 200,
                'is_active' => true,
            ],
            [
                'slug' => 'location_sharer',
                'name' => [
                    'ar' => 'مشارك الموقع',
                    'en' => 'Location Sharer'
                ],
                'description' => [
                    'ar' => 'شارك موقعك في 5 رحلات',
                    'en' => 'Share your location on 5 trips'
                ],
                'icon' => 'heroicon-o-map-pin',
                'category' => 'community',
                'criteria' => ['location_shared_trips' => 5],
                'rarity' => 'rare',
                'points_reward' => 30,
                'is_active' => true,
            ],
            [
                'slug' => 'community_contributor',
                'name' => [
                    'ar' => 'مساهم المجتمع',
                    'en' => 'Community Contributor'
                ],
                'description' => [
                    'ar' => 'أرسل 10 رسائل في مجتمعات الرحلات',
                    'en' => 'Send 10 messages in trip communities'
                ],
                'icon' => 'heroicon-o-chat-bubble-left',
                'category' => 'community',
                'criteria' => ['community_messages' => 10],
                'rarity' => 'rare',
                'points_reward' => 40,
                'is_active' => true,
            ],
            [
                'slug' => 'early_bird',
                'name' => [
                    'ar' => 'الطائر المبكر',
                    'en' => 'Early Bird'
                ],
                'description' => [
                    'ar' => 'اركب القطار في الصباح الباكر 5 مرات',
                    'en' => 'Take early morning trains 5 times'
                ],
                'icon' => 'heroicon-o-sun',
                'category' => 'special',
                'criteria' => ['early_morning_trips' => 5],
                'rarity' => 'rare',
                'points_reward' => 35,
                'is_active' => true,
            ],
        ];

        foreach ($badges as $badgeData) {
            Badge::firstOrCreate(
                ['slug' => $badgeData['slug']],
                $badgeData
            );
        }
    }
}