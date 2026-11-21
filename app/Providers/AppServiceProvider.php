<?php

namespace App\Providers;

use App\Models\AdmissionApplication;
use App\Models\ContactMessage;
use App\Models\LeaveRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // <script @nonce> — lets the page's own inline scripts pass the Content Security Policy.
        Blade::directive('nonce', fn () => "<?php echo 'nonce=\"'.e(\\Illuminate\\Support\\Facades\\Vite::cspNonce()).'\"'; ?>");

        Paginator::defaultView('components.ui.pagination');

        // Every generated link (redirects, API URLs, pagination) uses APP_URL, never the
        // request's Host / X-Forwarded-Host header, so a forged host cannot poison cached
        // API responses or password-less redirects.
        if (! $this->app->environment('local', 'testing') && filled(config('app.url'))) {
            URL::forceRootUrl(rtrim(config('app.url'), '/'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Public contact form: per-IP minute and day caps plus a global ceiling.
        RateLimiter::for('website-contact', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by('day:'.$request->ip()),
            Limit::perMinute(60)->by('all'),
        ]);

        // Sidebar counters: things that need someone's attention.
        View::composer('components.layouts.app', function ($view) {
            $badges = [];
            try {
                $badges = array_filter([
                    'admissions.verification' => Schema::hasTable('admission_applications')
                        ? AdmissionApplication::whereIn('verification_status', ['review', 'mismatch'])->whereNotIn('status', ['admitted', 'rejected', 'draft'])->count() : 0,
                    'attendance.leaves.index' => Schema::hasTable('leave_requests')
                        ? LeaveRequest::where('status', 'pending')->count() : 0,
                    'studio.messages.index' => Schema::hasTable('contact_messages')
                        ? ContactMessage::whereNull('read_at')->count() : 0,
                ]);
            } catch (Throwable) {
                // never let a counter break the page
            }
            $view->with('badges', $badges);
        });
    }
}
