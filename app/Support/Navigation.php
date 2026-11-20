<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for sidebar + command palette.
 * Each item: [label, route, icon, roles[], badge-callback?]
 */
class Navigation
{
    /** @return array<string, array<int, array>> */
    public static function admin(User $user): array
    {
        return self::filter($user, [
            'Overview' => [
                ['Command centre', 'dashboard', 'grid', ['principal', 'admission_officer', 'teacher']],
            ],
            'Admissions' => [
                ['Admissions desk', 'admissions.dashboard', 'inbox', ['principal', 'admission_officer']],
                ['Applications', 'admissions.index', 'files', ['principal', 'admission_officer']],
                ['AI verification', 'admissions.verification', 'scan', ['principal', 'admission_officer']],
                ['Merit lists', 'admissions.merit.index', 'trophy', ['principal', 'admission_officer']],
                ['Admission cycles', 'admissions.cycles.index', 'calendar', ['principal', 'admission_officer']],
            ],
            'Attendance' => [
                ['Mark attendance', 'attendance.index', 'check', ['principal', 'teacher']],
                ['Analytics', 'attendance.analytics', 'chart', ['principal', 'teacher']],
                ['Defaulters', 'attendance.defaulters', 'alert', ['principal', 'teacher']],
                ['Leave requests', 'attendance.leaves.index', 'leaf', ['principal', 'teacher']],
                ['Guardian alerts', 'attendance.alerts', 'bell', ['principal']],
            ],
            'Academics' => [
                ['Students', 'students.index', 'users', ['principal', 'admission_officer', 'teacher']],
                ['Programs', 'academics.programs.index', 'book', ['principal']],
                ['Sections', 'academics.sections.index', 'layers', ['principal']],
                ['Subjects', 'academics.subjects.index', 'bookmark', ['principal']],
                ['Timetable', 'academics.timetable', 'clock', ['principal', 'teacher']],
            ],
            'Administration' => [
                ['Staff & roles', 'staff.index', 'id', ['principal']],
                ['Website studio', 'studio.dashboard', 'globe', ['principal', 'editor']],
                ['Activity log', 'system.activity', 'list', ['principal']],
            ],
        ]);
    }

    /** @return array<string, array<int, array>> */
    public static function studio(User $user): array
    {
        return self::filter($user, [
            'Studio' => [
                ['Studio overview', 'studio.dashboard', 'globe', ['principal', 'editor']],
            ],
            'Publish' => [
                ['Announcements', 'studio.posts.index', 'megaphone', ['principal', 'editor'], ['type' => 'announcement']],
                ['News', 'studio.posts.index', 'news', ['principal', 'editor'], ['type' => 'news']],
                ['Events', 'studio.posts.index', 'calendar', ['principal', 'editor'], ['type' => 'event']],
                ['Notices', 'studio.posts.index', 'pin', ['principal', 'editor'], ['type' => 'notice']],
            ],
            'Site content' => [
                ['Pages', 'studio.pages.index', 'file', ['principal', 'editor']],
                ['Hero slides', 'studio.slides.index', 'image', ['principal', 'editor']],
                ['Gallery', 'studio.gallery.index', 'images', ['principal', 'editor']],
                ['Downloads', 'studio.downloads.index', 'download', ['principal', 'editor']],
                ['Faculty directory', 'studio.faculty.index', 'id', ['principal', 'editor']],
            ],
            'Inbox & settings' => [
                ['Contact inbox', 'studio.messages.index', 'mail', ['principal', 'editor']],
                ['Site settings', 'studio.settings', 'sliders', ['principal', 'editor']],
                ['Back to college ERP', 'dashboard', 'arrow-left', ['principal']],
            ],
        ]);
    }

    private static function filter(User $user, array $groups): array
    {
        $out = [];
        foreach ($groups as $group => $items) {
            $kept = array_values(array_filter($items, fn ($i) => Route::has($i[1]) && ($user->isAdmin() || in_array($user->role, $i[3], true))));
            if ($kept) {
                $out[$group] = array_map(fn ($i) => [
                    'label' => $i[0],
                    'route' => $i[1],
                    'icon' => $i[2],
                    'params' => $i[4] ?? [],
                    'url' => route($i[1], $i[4] ?? []),
                ], $kept);
            }
        }

        return $out;
    }

    public static function isActive(array $item): bool
    {
        $patterns = [$item['route'], $item['route'].'.*'];
        $base = preg_replace('/\.index$/', '', $item['route']);
        if ($base !== $item['route'] && str_contains($base, '.')) {
            $patterns[] = $base.'.*'; // e.g. academics.programs.edit, but never a bare module prefix
        }
        if (! request()->routeIs(...$patterns)) {
            return false;
        }
        foreach ($item['params'] as $k => $v) {
            if ((string) request($k, request()->route($k)?->type ?? null) !== (string) $v) {
                return false;
            }
        }

        return true;
    }

    /** Flat list for the Ctrl+K palette. */
    public static function palette(User $user, string $area): array
    {
        $items = [];
        foreach (array_merge(self::admin($user), self::studio($user)) as $group => $links) {
            foreach ($links as $l) {
                $items[$l['url']] = ['label' => $l['label'], 'group' => $group, 'url' => $l['url']];
            }
        }

        return array_values($items);
    }
}
