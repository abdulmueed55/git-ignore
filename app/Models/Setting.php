<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    /**
     * Settings managed in the Website studio. [key => [group, label, type, public?]]
     * Only "public" keys are exposed by /api/v1/settings.
     */
    public const FIELDS = [
        'college_name' => ['identity', 'College name', 'text', true],
        'college_tagline' => ['identity', 'Tagline', 'text', true],
        'established_year' => ['identity', 'Established', 'text', true],
        'address' => ['contact', 'Postal address', 'textarea', true],
        'phone' => ['contact', 'Main phone', 'text', true],
        'phone_admissions' => ['contact', 'Admissions phone', 'text', true],
        'email' => ['contact', 'Email', 'email', true],
        'office_hours' => ['contact', 'Office hours', 'text', true],
        'map_url' => ['contact', 'Google Maps link', 'url', true],
        'facebook_url' => ['social', 'Facebook', 'url', true],
        'youtube_url' => ['social', 'YouTube', 'url', true],
        'instagram_url' => ['social', 'Instagram', 'url', true],
        'x_url' => ['social', 'X (Twitter)', 'url', true],
        'principal_name' => ['principal', "Principal's name", 'text', true],
        'principal_title' => ['principal', 'Designation line', 'text', true],
        'principal_message' => ['principal', "Principal's message", 'textarea', true],
        'principal_message_updated_at' => ['principal', 'Message last updated', 'hidden', false],
        'admission_banner_enabled' => ['banners', 'Show admission banner', 'toggle', true],
        'admission_banner_text' => ['banners', 'Admission banner text', 'text', true],
        'ticker_enabled' => ['banners', 'Show news ticker', 'toggle', true],
        'brand_primary' => ['appearance', 'Primary colour', 'color', true],
        'brand_accent' => ['appearance', 'Accent colour', 'color', true],
    ];

    /** @return array<string, string|null> */
    public static function publicValues(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => [, , $type, $public]) {
            if ($public) {
                $v = static::get($key);
                $out[$key] = $type === 'toggle' ? (bool) $v : $v;
            }
        }

        return $out;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget('settings.all');
    }
}
