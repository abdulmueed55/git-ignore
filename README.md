# FG Liaquat Ali Degree College — College ERP

A complete college management system for **FG Liaquat Ali Degree College, Rawalpindi**:

| Module | What it does |
|---|---|
| **Command centre** | Role-aware home: today's attendance, registers still to mark, admission funnel, AI-flagged applications, students below 75 %, what is live on the website, audit trail. |
| **Admissions + AI verification** | Online applications with document upload. Claude reads CNIC/B-Form, Matric/Inter result cards and domicile, **auto-fills the form**, and **cross-checks every document against the others and the form** (name, father name, CNIC, date of birth, roll numbers, marks). Mismatches and duplicate CNICs are flagged; merit is calculated; merit lists generated and published; admitted applicants become students with roll numbers. |
| **Attendance** | 4-state register (Present / Absent / Late / Leave) marked by teachers in seconds — or by voice: **Speak attendance** (“Ahmed aur roll 7 absent, Hamza ki chutti, baqi sab present”) fills the register using OpenAI, Gemini or Claude, lock window, leave requests, guardian SMS alerts (absent today, below threshold, consecutive absences), analytics, defaulters list, per-student profile, printable monthly register. |
| **Academics** | Programs, sections, subjects, clash-free timetable, **extra / make-up / test classes** (clash-checked, students told by SMS), students, staff & roles, activity log. **Class alerts**: each teacher gets the day's list in the morning and a reminder 10 minutes before every class (bell + pop-up + optional SMS). |
| **Website studio** | A separate dashboard for the college website: announcements, news, events, notices, pages, hero slides, gallery, downloads, faculty directory, contact inbox, site settings — all exposed through a public JSON API (`/api/v1/…`) for the new website. |

## Requirements

- PHP 8.3+ with `pdo_mysql`, `gd`, `intl`, `mbstring`, `fileinfo`, `zip`
- Composer 2, Node 20+ (only to build assets)
- MySQL 8 / MariaDB 10.6+ in production (SQLite works for local use)

## Quick demo (test the whole system)

Windows: double-click `demo.bat` (needs PHP 8.3, Composer and Node — Laragon has all three). macOS/Linux: `./demo.sh`.
Then open http://localhost:8000. Step-by-step testing checklist (Roman Urdu): `docs/TESTING-GUIDE.md`.

Online demo link in ~10 minutes: deploy `render.yaml` as a Render Blueprint (free plan) or run the `Dockerfile` anywhere — see `docs/LIVE-DEMO.md`.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set the database in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fgla_erp
DB_USERNAME=fgla
DB_PASSWORD=change-me
APP_TIMEZONE=Asia/Karachi
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link
npm ci && npm run build
```

`--seed` loads a complete demo college (staff, ~450 students, timetable, ten weeks of attendance, two admission cycles, website content). For a clean production install run `php artisan migrate` only and create the first admin with `php artisan tinker`.

Demo logins (password `password`): `admin@`, `principal@`, `admissions@`, `teacher@`, `editor@` + `fglacollege.edu.pk`.

## AI document reading (admissions)

Add an Anthropic API key to `.env`:

```dotenv
ANTHROPIC_API_KEY=sk-ant-...
AI_EXTRACTION_MODEL=claude-opus-5-5
```

Each uploaded document is sent once to Claude, which returns structured fields (name, father name, CNIC, DOB, board, roll no, marks…) plus a confidence score and any visible problems (blurred, cropped, looks edited). The verifier then compares every field across all documents and the form. Without a key the system still works: documents are marked "enter manually" and staff verify by eye — nothing is ever guessed.

Document reading runs right after the upload request finishes, so no queue worker is required. On a VPS you may run `php artisan queue:work` under Supervisor for other background jobs.

## Speak attendance (AI)

On every register a teacher can press **Speak attendance**, say (or type) who is absent, late or on leave — in English, Urdu or Roman Urdu — and the register is filled: named students get their status, everyone else is marked present (approved leave stays leave). The teacher checks it and presses **Save register** as usual; nothing is saved without that.

Add **one** key to `.env`:

```dotenv
OPENAI_API_KEY=sk-...        # or
GEMINI_API_KEY=AIza...       # or ANTHROPIC_API_KEY (already used for admissions)
AI_PROVIDER=auto             # openai | gemini | anthropic | auto (first key found)
```

Only the class list (names, roll numbers, father names) and the sentence are sent. Voice is turned into text by the browser (Chrome / Edge / Android Chrome; Safari on iPhone too), so no audio leaves the device. Without a key a built-in matcher handles English / Roman Urdu names and roll numbers; anything it is unsure of (e.g. two students called Usman) is listed for the teacher instead of being guessed.

## SMS alerts to guardians

Alerts are queued in **Attendance → Guardian alerts**. The default gateway writes to the log; plug in a Pakistani SMS provider by implementing `App\Services\Attendance\SmsGateway` and setting it in `config/attendance.php`.

## Security

Reviewed 21 Nov 2026 — every finding, its fix and the regression test: **`docs/security/SECURITY-REVIEW.md`** (CSP with nonces, PIN lockout, role checks, CSV-injection guards, upload limits, production checklist). Run `php artisan test` to verify (incl. `tests/Feature/Security`).

## Deploying

- **VPS (recommended):** Nginx + PHP-FPM 8.3, document root `public/`, MySQL, `php artisan config:cache route:cache view:cache`, and a cron entry `* * * * * php /path/artisan schedule:run` (sends the teacher class reminders every minute; without it reminders are still created when the teacher has the ERP open).
- **Shared hosting (cPanel):** upload the project above `public_html`, point `public_html` to `public/`, import the database, set `.env`. Works without SSH queue workers.

## Tests

```bash
php artisan test
```

## Project layout

See `CLAUDE.md` for module boundaries, route files, roles and the design-system rules every screen follows.
