<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
{
    public function toArray($request)
    {
        $locale = $request->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

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
            'elevation' => $this->elevation,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
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

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}