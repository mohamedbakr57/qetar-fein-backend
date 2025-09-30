<?php

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