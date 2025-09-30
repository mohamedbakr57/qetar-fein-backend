# Spatie Translatable Implementation - قطر فين

## 1. Configuration Setup

### A. Publish and Configure Translatable

```bash
# Publish the config file
php artisan vendor:publish --provider="Spatie\Translatable\TranslatableServiceProvider"
```

### B. Configuration File (config/translatable.php)

```php
<?php

return [
    /*
     * If a translation has not been set for a given locale, use this locale instead.
     */
    'fallback_locale' => 'ar', // Arabic as default

    /*
     * Locales supported by the application.
     */
    'locales' => [
        'ar', // Arabic (default)
        'en', // English
    ],

    /*
     * If you want to cache the translations, you may specify a cache store.
     * If you set this to null, no caching will be done.
     */
    'cache' => [
        'store' => 'redis',
        'prefix' => 'translatable',
        'ttl' => 3600, // 1 hour
    ],

    /*
     * The default country for each locale.
     */
    'locale_country_mapping' => [
        'ar' => 'SA', // Saudi Arabia
        'en' => 'US', // United States
    ],

    /*
     * RTL (Right-to-Left) locales
     */
    'rtl_locales' => ['ar'],
];
```

## 2. Model Examples with Translations

### A. Station Model with Bilingual Fields

```php
<?php
// app/Models/Train/Station.php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Station extends Model
{
    use HasTranslations;

    protected $fillable = [
        'code',
        'name',
        'description', 
        'city',
        'region',
        'latitude',
        'longitude',
        'facilities',
        'status',
        'order_index',
    ];

    // Define which fields are translatable
    protected $translatable = [
        'name',
        'description',
        'city', 
        'region'
    ];

    protected $casts = [
        'facilities' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // Helper method to get name in specific locale
    public function getNameInLocale(string $locale): string
    {
        return $this->getTranslation('name', $locale) ?: $this->getTranslation('name', 'ar');
    }

    // Helper method to get all translations for a field
    public function getAllTranslations(string $field): array
    {
        return $this->getTranslations($field);
    }
}
```

### B. Train Model with Operator Translation

```php
<?php
// app/Models/Train/Train.php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Train extends Model
{
    use HasTranslations;

    protected $fillable = [
        'number',
        'name',
        'description',
        'type',
        'operator',
        'capacity',
        'max_speed',
        'amenities',
        'status',
    ];

    protected $translatable = [
        'name',
        'description',
        'operator' // Railway operator name in both languages
    ];

    protected $casts = [
        'amenities' => 'array',
    ];

    // Scope to get trains by operator in current locale
    public function scopeByOperator($query, string $operatorName)
    {
        $locale = app()->getLocale();
        return $query->whereRaw("JSON_EXTRACT(operator, '$.{$locale}') LIKE ?", ["%{$operatorName}%"]);
    }
}
```

### C. Badge Model for Gamification

```php
<?php
// app/Models/Gamification/Badge.php

namespace App\Models\Gamification;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Badge extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'category',
        'criteria',
        'rarity',
        'points_reward',
        'is_active',
    ];

    protected $translatable = [
        'name',
        'description'
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    // Get localized badge name with fallback
    public function getLocalizedName(): string
    {
        $locale = app()->getLocale();
        return $this->getTranslation('name', $locale) ?: $this->name;
    }
}
```

## 3. Database Migrations with JSON Columns

### A. Stations Migration

```php
<?php
// database/migrations/2024_01_01_000001_create_stations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->json('name'); // {"ar": "محطة الرياض", "en": "Riyadh Station"}
            $table->json('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('elevation')->nullable();
            $table->json('city'); // {"ar": "الرياض", "en": "Riyadh"}
            $table->json('region')->nullable();
            $table->string('country_code', 2)->default('SA');
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->json('facilities')->nullable(); // ["wifi", "restaurant", "parking"]
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('status');
            $table->index('order_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stations');
    }
};
```

### B. System Notifications Migration

```php
<?php
// database/migrations/2024_01_01_000020_create_system_notifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->json('title'); // {"ar": "تأخير في القطار", "en": "Train Delay"}
            $table->json('message'); // Bilingual message content
            $table->enum('type', ['info', 'warning', 'alert', 'maintenance'])->default('info');
            $table->enum('target_type', ['all', 'specific_users', 'train_passengers', 'route_users'])->default('all');
            $table->json('target_criteria')->nullable(); // Targeting criteria
            $table->timestamp('scheduled_at')->default(now());
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('scheduled_at');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_notifications');
    }
};
```

## 4. Seeder Examples with Bilingual Data

### A. Stations Seeder

```php
<?php
// database/seeders/StationsSeeder.php

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
                'facilities' => ['wifi', 'restaurant', 'prayer_area', 'family_area', 'hajj_services'],
                'order_index' => 3,
            ],
        ];

        foreach ($stations as $stationData) {
            Station::create($stationData);
        }
    }
}
```

### B. Badges Seeder

```php
<?php
// database/seeders/BadgesSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gamification\Badge;

class BadgesSeeder extends Seeder
{
    public function run()
    {
        $badges = [
            [
                'name' => [
                    'ar' => 'مسافر مبتدئ',
                    'en' => 'Beginner Traveler'
                ],
                'description' => [
                    'ar' => 'أكمل أول رحلة بالقطار',
                    'en' => 'Complete your first train journey'
                ],
                'icon' => 'beginner-traveler.svg',
                'category' => 'travel',
                'criteria' => [
                    'type' => 'assignment_completion',
                    'count' => 1
                ],
                'rarity' => 'common',
                'points_reward' => 10,
            ],
            [
                'name' => [
                    'ar' => 'مسافر نشط',
                    'en' => 'Active Traveler'
                ],
                'description' => [
                    'ar' => 'أكمل 10 رحلات بنجاح',
                    'en' => 'Complete 10 successful journeys'
                ],
                'icon' => 'active-traveler.svg',
                'category' => 'travel',
                'criteria' => [
                    'type' => 'assignment_completion',
                    'count' => 10
                ],
                'rarity' => 'uncommon',
                'points_reward' => 50,
            ],
            [
                'name' => [
                    'ar' => 'عضو مجتمع فعال',
                    'en' => 'Active Community Member'
                ],
                'description' => [
                    'ar' => 'شارك في 25 محادثة مجتمعية',
                    'en' => 'Participate in 25 community conversations'
                ],
                'icon' => 'community-active.svg',
                'category' => 'community',
                'criteria' => [
                    'type' => 'community_participation',
                    'count' => 25
                ],
                'rarity' => 'rare',
                'points_reward' => 75,
            ],
        ];

        foreach ($badges as $badgeData) {
            Badge::create($badgeData);
        }
    }
}
```

## 5. API Resource Transformers

### A. Station Resource with Locale Detection

```php
<?php
// app/Http/Resources/StationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
{
    public function toArray($request)
    {
        $locale = $request->header('Accept-Language', 'ar');
        
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'city' => $this->getTranslation('city', $locale),
            'region' => $this->getTranslation('region', $locale),
            'coordinates' => [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ],
            'facilities' => $this->facilities ?? [],
            'status' => $this->status,
            'order_index' => $this->order_index,
            
            // Include all translations if requested
            'translations' => $this->when(
                $request->get('include_translations'), 
                function () {
                    return [
                        'name' => $this->getTranslations('name'),
                        'description' => $this->getTranslations('description'),
                        'city' => $this->getTranslations('city'),
                        'region' => $this->getTranslations('region'),
                    ];
                }
            ),
        ];
    }
}
```

## 6. Middleware for Locale Detection

### A. Locale Detection Middleware

```php
<?php
// app/Http/Middleware/SetLocale.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Get locale from various sources
        $locale = $this->detectLocale($request);
        
        // Validate locale
        if (in_array($locale, config('translatable.locales'))) {
            App::setLocale($locale);
        } else {
            App::setLocale(config('translatable.fallback_locale'));
        }

        return $next($request);
    }

    protected function detectLocale(Request $request): string
    {
        // 1. Check URL parameter
        if ($request->has('lang')) {
            return $request->get('lang');
        }

        // 2. Check Accept-Language header
        if ($request->hasHeader('Accept-Language')) {
            $header = $request->header('Accept-Language');
            $locale = substr($header, 0, 2);
            if (in_array($locale, config('translatable.locales'))) {
                return $locale;
            }
        }

        // 3. Check authenticated user preference
        if ($request->user() && $request->user()->preferred_language) {
            return $request->user()->preferred_language;
        }

        // 4. Default to Arabic
        return config('translatable.fallback_locale');
    }
}
```

## 7. Helper Functions and Utilities

### A. Translation Helper Service

```php
<?php
// app/Services/TranslationService.php

namespace App\Services;

use Illuminate\Support\Facades\App;

class TranslationService
{
    public static function getLocalizedText(array $translations, ?string $locale = null): string
    {
        $locale = $locale ?: App::getLocale();
        
        // Return requested locale if exists
        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        // Fallback to Arabic
        if (isset($translations['ar'])) {
            return $translations['ar'];
        }

        // Fallback to English
        if (isset($translations['en'])) {
            return $translations['en'];
        }

        // Return first available translation
        return array_values($translations)[0] ?? '';
    }

    public static function getCurrentDirection(): string
    {
        $locale = App::getLocale();
        $rtlLocales = config('translatable.rtl_locales', ['ar']);
        
        return in_array($locale, $rtlLocales) ? 'rtl' : 'ltr';
    }

    public static function formatDateTime(\DateTime $date, ?string $locale = null): string
    {
        $locale = $locale ?: App::getLocale();
        
        if ($locale === 'ar') {
            return $date->format('d/m/Y H:i');
        }
        
        return $date->format('M d, Y H:i');
    }
}
```

This implementation provides:

1. **Complete Bilingual Support**: Arabic (default) and English
2. **Flexible Locale Detection**: Header, URL param, user preference
3. **Optimized Performance**: Caching translations in Redis
4. **Proper Fallbacks**: Arabic → English → First available
5. **Database Structure**: JSON columns for translations
6. **API Resources**: Locale-aware data transformation
7. **Seeders**: Bilingual sample data
8. **Helper Services**: Utility functions for translations