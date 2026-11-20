<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
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
        Paginator::defaultView('components.ui.pagination');

        // Sidebar counters: things that need someone's attention.
        View::composer('components.layouts.app', function ($view) {
            $badges = [];
            try {
                $badges = array_filter([
                    'admissions.verification' => Schema::hasTable('admission_applications')
                        ? \App\Models\AdmissionApplication::whereIn('verification_status', ['review', 'mismatch'])->whereNotIn('status', ['admitted', 'rejected', 'draft'])->count() : 0,
                    'attendance.leaves.index' => Schema::hasTable('leave_requests')
                        ? \App\Models\LeaveRequest::where('status', 'pending')->count() : 0,
                    'studio.messages.index' => Schema::hasTable('contact_messages')
                        ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0,
                ]);
            } catch (Throwable) {
                // never let a counter break the page
            }
            $view->with('badges', $badges);
        });
    }
}
