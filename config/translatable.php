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
        'store' => 'database',
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