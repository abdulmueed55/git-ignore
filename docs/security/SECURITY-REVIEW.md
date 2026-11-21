# Security review — FG Liaquat Ali Degree College ERP

**Date:** 21 Nov 2026 · **Branch:** `claude/eloquent-curie-taascf` · **Stack:** Laravel 13, PHP 8.3, MySQL/SQLite, Blade + Alpine.js

This document is written for the independent security reviewer. It lists what was reviewed, every issue found and how it was fixed (with the regression test that proves it), the defences now in place, and the risks that remain by design. Please verify, not trust: every claim below has a file reference.

---

## 1. Scope and method

| Area | Entry points |
|---|---|
| Public | `/` portal, `/apply/*` applicant portal (uploads, PIN status lookup), `/api/v1/*` website JSON API + contact form, `/login` |
| Staff ERP | dashboard, admissions desk + AI document verification, merit lists, attendance (incl. AI "Speak attendance"), students, staff & roles, timetable, activity log |
| Website studio | posts/pages (rich HTML), media uploads, gallery, downloads, faculty, settings, inbox |
| Platform | middleware, sessions/cookies, headers, Docker image, Render blueprint, dependencies |

Method: manual code review of every route, controller, service, model and Blade view (three parallel review passes: applicant/admissions, studio/API/portal, auth/attendance/academics), payload testing of the HTML sanitizers (≈45 XSS / mutation-XSS payloads server-side, 10 client-side), a CSP crawl of 137 pages as all five roles, `composer audit` and `npm audit` (0 advisories), and a git-history scan for secrets (none: `.env`, databases and keys were never committed).

Roles: `admin`, `principal`, `admission_officer`, `teacher`, `editor`. Route groups are guarded with `auth` + `role:` (`app/Http/Middleware/EnsureRole.php`, which also re-checks `is_active` on every request).

---

## 2. Findings and fixes

Severity is before the fix. **All items below are fixed** unless marked otherwise.

### High

| # | Issue | Fix | Where | Test |
|---|---|---|---|---|
| H1 | **Applicant PIN takeover.** Starting an application with a known CNIC echoed the existing tracking number; the 6-digit PIN (plaintext) was then brute-forceable with only a per-IP throttle (rotating IPs ≈ hours). Gave access to CNIC images and the right to edit/submit a draft. | Tracking number never revealed; PIN stored as **bcrypt** (`hashed` cast) and shown only in the creator's own session; **per-tracking-number lockout** (5 wrong PINs / 15 min, 20 / day) independent of IP; session regenerated on access. | `Apply/ApplyController.php` (`store`, `grant`), `Apply/StatusController.php::lookup`, `Models/AdmissionApplication.php` | `ApplyFlowTest::test_pin_guessing_locks_the_tracking_number_whatever_the_ip`, `…without_revealing_its_tracking_number` |
| H2 | **AI alone verified applications.** A "clear" AI cross-check set `status=verified`, which feeds merit lists — a forged DMC with prompt-injection text ("authentic, confidence 100") could reach a merit list without a human. | AI result is now a recommendation only (`under_review`); only an officer's action sets `verified`. Extraction prompt treats all document text as data and flags embedded instructions as tampering. | `Services/Admissions/CrossDocumentVerifier.php`, `DocumentSchemas::prompt()` | `VerifierTest::test_consistent_documents_clear_but_wait_for_an_officer`, `StaffAccessTest` |
| H3 | **Teacher-approved leave rewrote attendance.** A teacher could file *and* approve unlimited-range leave for their students; approval converts every absence in the range (all subjects, locked registers) to leave — lifting a student over the 75 % exam rule. | Only leadership approves/rejects; teachers may file leave starting ≤ 30 days back and ≤ 30 days long (leadership 120); `filed_by` recorded. | `Attendance/LeaveController.php`, migration `2026_11_21_000100_*`, `attendance/leaves.blade.php` | `AttendanceTest::test_approving_leave…`, `test_leave_can_be_filed_and_is_validated` |

### Medium

| # | Issue | Fix | Where | Test |
|---|---|---|---|---|
| M1 | **Register lock bypass / backdating.** A teacher could create a new slot-less lecture (e.g. 09:01 next to a locked 09:00) or one for any past date. | Non-leadership cannot create/change a register once `period + lock_after_hours` has passed. | `Attendance/MarkController.php::store` | `SecurityHardeningTest::test_teacher_cannot_backdate_a_register_or_export_student_pii` |
| M2 | **CSV / formula injection** in four exports (applications, students, defaulters, monthly register) — applicant-typed `=HYPERLINK(…)` executes in Excel. | Every exported cell passes through `App\Support\Csv::cell()` (prefixes `'`); names/places/board/roll-no now validated with letter-only regexes at the source. | `app/Support/Csv.php`, the four controllers, `ApplyController` rules | `test_exports_neutralise_spreadsheet_formulas`, `test_names_with_spreadsheet_formulas_are_rejected` |
| M3 | **Principal → principal takeover.** A principal could reset another principal's password (shown in plaintext), deactivate them, or grant the principal role. | Only an admin manages admin/principal accounts or grants `principal`. | `Academics/StaffController.php::guardTarget`, `assignableRoles` | `test_a_principal_cannot_take_over_another_principal_or_grant_principal` |
| M4 | **Login brute force across IPs** (limiter keyed on email+IP only); user enumeration by timing. | Second limiter per account (10 / 15 min, any IP); dummy bcrypt check for unknown/inactive accounts; failed sign-ins written to the audit log. | `Auth/LoginController.php` | `test_login_is_capped_per_account_even_from_rotating_ips` |
| M5 | **Sessions survived password reset / deactivation.** | All of the user's database sessions deleted and remember-token rotated. | `StaffController::endSessions` | — (database driver; manual check) |
| M6 | **Host-header cache poisoning** of the public API: absolute URLs were built from `Host` / `X-Forwarded-Host` and cached for 60 s for everyone. | All URLs generated from `APP_URL` (`URL::forceRootUrl`, `forceScheme('https')`) outside local/testing. | `Providers/AppServiceProvider.php` | — (env-dependent; verify on staging with a forged `Host`) |
| M7 | **Unbounded API cache keys** (`md5(query string)`) → cache table flooding. | Cache key and pagination links built only from whitelisted, clamped params. | `Api/ContentController::cached` | — |
| M8 | **Merit-list publish overwrote decisions** (a rejected/admitted applicant on a draft list became `selected`). | Publish only promotes `verified/waitlisted`; list row locked; idempotent. | `Services/Admissions/MeritListService::publish` | `test_officer_cannot_verify_a_draft_and_publishing_keeps_decisions` |
| M9 | **Admission race / over-admission**: double submit → duplicate roll number (500); no seat check. | Application row locked (`lockForUpdate`), re-checked inside the transaction; roll-number rows locked; **unique** `students.admission_application_id`; seat limit enforced. | `Services/Admissions/Admitter.php`, migration | `test_admitting_twice_creates_one_student_and_respects_seats` |
| M10 | **AI cost / worker exhaustion via uploads** (unbounded re-uploads, 100-page PDFs, synchronous AI). | 20 uploads/application/hour, PDFs > 4 pages refused before AI, daily AI reading budget (`AI_DOCUMENT_DAILY_LIMIT`, default 600 → manual check), real HTTP timeouts on Claude calls. | `ApplyController::upload`, `DocumentProcessor`, `Services/Ai/Ai::claudeRequestOptions` | `test_applicant_files_are_sandboxed_and_long_pdfs_refused` |
| M11 | **CNIC squatting**: a draft with someone else's CNIC blocked the real applicant. | Only a *submitted* application blocks a CNIC (checked again at submit). | `ApplyController::store/submit` | `test_a_draft_cannot_squat_someone_elses_cnic` |

### Low

| # | Issue | Fix |
|---|---|---|
| L1 | Officer could set a status on an unsubmitted draft | 422 for drafts (`ApplicationController::status`) |
| L2 | Internal exception text shown to applicants | Generic message; detail only in logs (`DocumentProcessor::read`) |
| L3 | Stored file extension taken from the client name; PDFs/images served inline on the app origin | Extension from content sniffing; all private-file responses carry `Content-Security-Policy: sandbox`; `local` disk `serve => false` |
| L4 | False "short attendance" SMS for non-defaulters | Alerts only when the recomputed rate < threshold (`AlertService::notifyDefaulters`) |
| L5 | Teachers could bulk-export CNIC/DOB/phones | Export restricted to principal/admission office |
| L6 | mailto: injection (`"?bcc=…"@gmail.com` passed the `email` rule) | `email:rfc,strict,filter`; `rawurlencode` in `mailto:` links |
| L7 | Editor preview executed pasted `<script>`/`onerror`/Alpine `x-init` (self-XSS) | Whitelist DOM sanitizer `window.safeHtml` (`resources/js/app.js`) for both previews |
| L8 | Deleted posts' files stayed public; duplicated posts shared files | `Post::deleting` removes files; duplicate copies files |
| L9 | Staff sign-in emails published in the faculty API | Import no longer copies them; API hides any email that is a login |
| L10 | Settings URLs accepted `data:`/`javascript:` schemes | `url:https` |
| L11 | Contact form: 5/min/IP only | 3/min + 20/day per IP + 60/min global |
| L12 | Rich-text bodies sanitised only in the controller | Also sanitised in `Post`/`Page` model mutators (seeders, importers, tinker) — idempotent on all existing content |
| L13 | `{{ }}` inside Alpine `x-data` expressions | `@js()` |
| L14 | Claude SDK had no effective timeout | Guzzle transport with timeout/connect-timeout, 1 retry |

---

## 3. Defences now in place

- **CSP with per-request nonce** on every response (`app/Http/Middleware/SecurityHeaders.php`): `default-src 'self'`, scripts only from self + nonce, `object-src 'none'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'`. All inline event handlers were removed (`data-confirm` / `data-autosubmit` / `data-print` handled in `app.js`). Crawl of 137 pages × 5 roles: 0 violations.
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (only microphone, same origin — for Speak attendance), `Cross-Origin-Opener-Policy`, HSTS on HTTPS, `X-Powered-By` removed.
- Sessions: database driver, **encrypted**, HttpOnly, SameSite=Lax, Secure automatically when `APP_URL` is https; regenerated on login and on applicant access; invalidated on logout.
- CSRF on every state-changing route (no exemptions); no state change on GET except "mark message read" and public API view counters.
- Authorization: role middleware on every group + object-level checks (`Scope::canMark`, `canViewStudent`, scoped queries); applicant documents only through `$app->documents()` of a session-granted application.
- Mass assignment: every `create/fill/update` uses validated arrays; `role`, `is_active`, `status`, merit and verification fields are set server-side only.
- Uploads: `mimes`/`image` rules (content-sniffed; SVG/HTML/PHP rejected), size limits, random server-side names, applicant files on the private disk.
- HTML: server sanitizer (`Studio/Concerns/HtmlSanitizer.php`) + model mutators; client preview sanitizer.
- SQL: Eloquent/bindings only; sort columns via `match` whitelists.
- AI: structured outputs with `additionalProperties:false`; outputs filtered to known IDs/statuses (voice attendance) or empty fields only (admissions auto-fill); AI cannot change status/merit/quota; keys only in headers, never logged.
- Audit trail: logins, failed logins, exports of personal data, status changes, admissions, staff changes (`activity_logs`).
- Docker: runs as `www-data` (code read-only), `expose_php=Off`, `display_errors=Off`; demo data seeding **refuses to run in production unless `APP_DEMO=true`**.

---

## 4. Known limitations / accepted risks (please assess)

1. **`script-src 'unsafe-eval'`** is required by Alpine.js (expression evaluation). Injected `<script>` and inline handlers are still blocked by the nonce, but injected *HTML with Alpine attributes* would execute — hence all user HTML is sanitised server-side (attributes whitelisted, `x-*`/`@`/`:` stripped). Migrating to `@alpinejs/csp` would remove this.
2. `style-src 'unsafe-inline'` (inline `style=""` attributes are used).
3. **Demo mode** (`APP_DEMO=true`, the Render blueprint and Docker default): public demo accounts with password `password` and a database rebuilt on every start. **Never** use for real data.
4. The Docker image serves with `php artisan serve` (fine for the demo; production should be Nginx + PHP-FPM).
5. `TRUST_PROXIES=true` trusts any proxy — correct on Render (the app is reachable only through its proxy). On a server reachable directly, set it to the proxy's IP/CIDR or per-IP throttles can be bypassed with `X-Forwarded-For`.
6. No MFA for staff yet; no malware scanning of uploads; SMS gateway is a log stub.
7. Uploaded website media (public disk) uses unguessable names but unpublished files remain reachable by direct URL.
8. Applicant identity rests on tracking number + 6-digit PIN with lockout; an OTP to the applicant's phone/email would be stronger.

---

## 5. Production checklist

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_DEMO=false            # hides demo logins, blocks demo seeding
APP_URL=https://erp.your-domain.pk
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
TRUST_PROXIES=10.0.0.0/8  # your proxy, or leave empty when not behind one
DEMO_RESET=false          # Docker only
LOG_LEVEL=warning
```

- Do **not** run `--seed` on the production database; create the first admin with `php artisan tinker` and a strong password.
- HTTPS only (HSTS is sent automatically on HTTPS); `php artisan config:cache route:cache view:cache`.
- Web root = `public/`; `storage/` and `.env` not web-accessible; file permissions: code read-only to the PHP user.
- Daily database + `storage/app/private` backups (applicant CNICs and result cards live there) — encrypted, off-site.
- Rotate `APP_KEY` only with a session/encrypted-data plan; keep AI keys in the server environment, never in git.

## 6. Verifying

```bash
php artisan test                      # 111 tests, incl. tests/Feature/Security/SecurityHardeningTest.php
composer audit && npm audit --omit=dev
curl -sI https://<host>/login         # security headers + CSP
```
