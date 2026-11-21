<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const GROUPS = [
        'identity' => ['College identity', 'Name and tagline used in the site header, footer and search results.'],
        'contact' => ['Contact details', 'Shown in the footer and on the Contact page.'],
        'principal' => ["Principal's message", 'Featured on the home page.'],
        'banners' => ['Banners & ticker', 'Site-wide strips above the header.'],
        'social' => ['Social links', 'Leave blank to hide an icon.'],
        'appearance' => ['Appearance', 'Brand colours the redesigned website will read from the API.'],
    ];

    public function edit(): View
    {
        $values = [];
        foreach (array_keys(Setting::FIELDS) as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('studio.settings', ['values' => $values, 'groups' => self::GROUPS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'college_name' => ['required', 'string', 'max:160'],
            'college_tagline' => ['required', 'string', 'max:200'],
            'established_year' => ['required', 'digits:4'],
            'address' => ['required', 'string', 'max:300'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]+$/'],
            'phone_admissions' => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['required', 'email', 'max:160'],
            'office_hours' => ['required', 'string', 'max:120'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'facebook_url' => ['nullable', 'url:https', 'max:300'],
            'youtube_url' => ['nullable', 'url:https', 'max:300'],
            'instagram_url' => ['nullable', 'url:https', 'max:300'],
            'x_url' => ['nullable', 'url:https', 'max:300'],
            'principal_name' => ['required', 'string', 'max:120'],
            'principal_title' => ['required', 'string', 'max:160'],
            'principal_message' => ['required', 'string', 'min:80', 'max:3000'],
            'admission_banner_enabled' => ['nullable', 'boolean'],
            'admission_banner_text' => ['required_if:admission_banner_enabled,1', 'nullable', 'string', 'max:200'],
            'ticker_enabled' => ['nullable', 'boolean'],
            'brand_primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'admission_banner_text.required_if' => 'Write the banner text, or switch the banner off.',
            'phone.regex' => 'Use digits, spaces, + and - only.',
            'phone_admissions.regex' => 'Use digits, spaces, + and - only.',
            'principal_message.min' => 'The message should be at least a short paragraph.',
        ]);

        $data['admission_banner_enabled'] = $request->boolean('admission_banner_enabled') ? '1' : '0';
        $data['ticker_enabled'] = $request->boolean('ticker_enabled') ? '1' : '0';

        if (trim((string) Setting::get('principal_message')) !== trim($data['principal_message'])) {
            Setting::put('principal_message_updated_at', now()->toDateTimeString(), 'principal');
        }
        foreach ($data as $key => $value) {
            Setting::put($key, $value, Setting::FIELDS[$key][0] ?? 'general');
        }
        ActivityLog::record('studio.settings.updated', 'Updated website settings');

        return back()->with('success', 'Settings saved. The website picks them up within a minute.');
    }
}
