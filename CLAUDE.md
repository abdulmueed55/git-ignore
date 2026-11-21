# FG Liaquat Ali Degree College — College ERP

Laravel 13 · PHP 8.3 · MySQL in production (SQLite locally/tests) · Blade + Alpine.js + Tailwind v4 · Chart.js.

## Modules and where they live

| Module | Routes file | Controllers | Views | Seeder |
|---|---|---|---|---|
| Auth + Command centre | `routes/web.php` | `Auth/LoginController`, `DashboardController` | `auth/`, `dashboard.blade.php` | `AcademicSeeder` |
| Public landing page | `routes/modules/portal.php` | `Portal/` | `portal/` | — |
| Applicant portal | `routes/modules/apply.php` | `Apply/` | `apply/` | — |
| Admissions desk + AI verification | `routes/modules/admissions.php` | `Admissions/` | `admissions/` | `AdmissionSeeder` |
| Attendance | `routes/modules/attendance.php` | `Attendance/` | `attendance/` | `AttendanceSeeder` |
| Academics, students, staff, timetable + extra classes, activity log | `routes/modules/academics.php` | `Academics/` | `academics/`, `students/`, `staff/`, `system/` | `ExtraClassSeeder` |
| Staff alerts (bell, class reminders) | `routes/web.php` | `NotificationController` | `components/layouts/app` (bell) | — |
| Website studio (CMS) + public JSON API | `routes/modules/studio.php`, `routes/modules/api.php` | `Studio/`, `Api/` | `studio/` | `CmsSeeder` |

Route names are fixed by `app/Support/Navigation.php` (sidebar + Ctrl+K palette). Keep them.

## Roles

`admin` (everything), `principal`, `admission_officer`, `teacher`, `editor` (website studio only).
Guard routes with `->middleware(['auth', 'role:principal,teacher'])` — admin always passes.
Teachers only see their own courses/sections unless leadership (`$user->isLeadership()`).

## UI rules (same look as the college website)

- One typeface everywhere: Libre Franklin (no serif display or code fonts). Crest green `#0b6b3f`, dark rail `#0a3d26`,
  crest blue rail in the website studio, white panels on `#f4f5f4`, 3–6px corners. Official crest: `public/images/crest.jpg` via `<x-crest>`.

- Every staff page uses `<x-layouts.app title="…">` (ERP) or `<x-layouts.app title="…" area="studio">` (website studio).
- Start pages with `<x-ui.page-header eyebrow="…" title="…" description="…">` + optional `<x-slot:actions>`.
- Components: `x-ui.stat`, `x-ui.status`, `x-ui.field` (label + required marker + validation error), `x-ui.empty`
  (empty state **must** say what to do next and offer an action), `x-ui.ring`, `x-ui.sparkline`, `x-ui.avatar`,
  `x-ui.confirm`, `x-icon name="…"`. CSS classes: `panel`, `panel-head`, `panel-title`, `btn btn-primary|btn-ghost|btn-brass|btn-danger`,
  `input`, `table`, `badge badge-ok|warn|bad|info|brand|brass`, `eyebrow`, `numeral` (big bold figures), `mono` (IDs, roll numbers — tabular figures, same typeface).
- Charts: `<div class="h-64"><canvas x-data="chart({type:'line', labels:[…], series:[{label:'…', data:[…], tone:'brand'}]})"></canvas></div>`.
- Colors only via tokens (`text-ink`, `text-muted`, `bg-surface`, `border-line`, `text-brand`, `text-brass`, `bg-ok-soft` …) so dark mode works.
- **Nothing is left blank**: every form field validated server-side, required fields marked; every list has a helpful empty state;
  every seeded screen shows realistic data.

## Security rules (every new module)

Full review and fixes: `docs/security/SECURITY-REVIEW.md`. For any new screen or route:
- Route group with `auth` + `role:`; object-level checks too (a teacher only touches own courses/sections — `Scope`).
- Validate every field; never `$request->all()` into `create/fill`; status/role/money fields set server-side only.
- No inline JS handlers (`onclick=` is blocked by the CSP): use Alpine, or `data-confirm` / `data-autosubmit` / `data-print`.
  Inline `<script>` must be `<script @nonce>`. Data into JS via `@js()`, never `{{ }}` inside `x-data`.
- Never `{!! !!}` user content unless it went through `HtmlSanitizer`; previews use `window.safeHtml()`.
- CSV exports: wrap rows in `App\Support\Csv::row()`; log exports of personal data with `ActivityLog`.
- Uploads: `mimes`/`image` + `max`, server-chosen names, private disk for personal documents, `throttle` on public POSTs.
- AI output is untrusted: schema-constrained, filtered to known IDs/values, never sets status or permissions by itself.
- Add a regression test in `tests/Feature/<Module>/` (and `tests/Feature/Security/` for access rules).

## Conventions

- "Today's classes" anywhere = `App\Services\Timetable\TeacherSchedule::day()` (timetable + extra classes). Staff alerts = `App\Notifications\StaffAlert` with a unique `key`.
- Log important actions with `ActivityLog::record('module.action', 'Human sentence', $model)`.
- Uploaded applicant documents: private `local` disk under `admissions/{application_id}/`; website media: `public` disk.
- Timezone `Asia/Karachi`; dates shown as `j M Y`; Hijri via `App\Support\Dates::hijri()`.
- Tests: `php artisan test` (SQLite in-memory). Feature tests per module in `tests/Feature/<Module>/`.
- Seed demo data: `php artisan migrate:fresh --seed`. Demo logins use password `password`
  (`admin@`, `principal@`, `admissions@`, `teacher@`, `editor@` `fglacollege.edu.pk`).
