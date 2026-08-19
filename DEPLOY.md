# AIFOD Media Package — Deployment & Remaining Setup

This branch delivers the **automated delivery system** and the reliability/security
fixes for the Geneva Summit 2026 media store. Below is what changed, where each
file goes, and the handful of **values you still have to paste in** (folder IDs +
live keys) before go-live.

## Files in this repo

| File | Deploy to | What it is |
|---|---|---|
| `aifod-apps-script.js` | Google Apps Script project | Sheet recording **+ new automated delivery engine** |
| `stripe-proxy.php` | `public_html/stripe-proxy.php` | PHP API proxy (Stripe, Drive, orders, delivery trigger) |
| `gallery-wordpress.html` | WordPress `gallery-2` page (Custom HTML) | Photo-selection gallery |
| `aifod-speaker-media.html` | WordPress `mediapackage` page (Custom HTML) | Main sales page |

## What was built / fixed

1. **Automated delivery (item #1)** — after a buyer confirms their selection
   (25/50 packages) or immediately after payment (All Photos / Video / Bundle),
   `stripe-proxy.php` calls the Apps Script `deliver` action, which:
   - creates `AIFOD-MEDIA-2026-<seq>_<Speaker>/` under your deliveries parent folder,
   - copies the **entitled** files in (`Photos/` and/or `Videos/`) per the spec's
     entitlement flags — never moves them, never shares the master folder,
   - shares that per-order folder view-only, and
   - emails the buyer the link, CC'ing `z@af.net` and `abdul@af.net`.
   It is **idempotent** (one folder per payment intent → safe against retries).
2. **Sheet recording reliability (item #2)** — the Apps Script call now retries
   (0.4s/0.8s backoff), checks the response, and logs any failure to
   `orders-data/apps-script-fails.log`. The Apps Script `recordOrder` skips
   duplicates and auto-creates the header/tab. CSV remains the local source of truth.
3. **Server-side security (spec check #3)** — `saveSelection` now rejects the
   whole submission unless **every** selected photo id belongs to that speaker's
   own master folder, and it resolves the limit from the order's package, not the
   request. Prevents a tampered browser turning a €299 order into the full set.
4. **Em-dash mojibake (item #6)** — fixed in both HTML pages.
5. **Gallery double-init bug** — `gallery-wordpress.html` had the entire `<script>`
   duplicated, building every thumbnail twice; removed.
6. **Preview 400s (item #7)** — `getThumb` now falls back to the Drive API media
   endpoint when the public thumbnail endpoint fails.
7. **Package logic centralized** — one `packages()` table in PHP and one
   `PACKAGES` map in Apps Script (mirrors the spec's entitlement flags).

## ⚠️ You must still fill these in

### A. Deliveries parent folder — Apps Script `CONFIG.DELIVERY_PARENT_ID`
Once Shalom creates **"AIFOD Media Deliveries 2026"**, open it and copy the ID
from the URL (`drive.google.com/drive/folders/<ID>`) into `aifod-apps-script.js`.
Until this is set, a paid order emails the team a "delivery not configured" warning
instead of failing silently.

### B. Speaker → master folder IDs (item #4)
Add every speaker in **all three** places (keep them identical):
- `aifod-apps-script.js` → `SPEAKER_FOLDERS`
- `stripe-proxy.php` → `speaker_folders()`
- `aifod-speaker-media.html` → `AP_FOLDERS` (this one only drives the on-page
  *preview*; delivery uses the PHP + Apps Script maps).

Only `Tianze Zhang` is mapped so far.

### C. Stripe secret + Drive API key — do this on the server, NOT in git
The committed `stripe-proxy.php` ships with **placeholders** for the two secrets
(`STRIPE_SECRET` and `DRIVE_API_KEY`) — GitHub blocks pushing real keys. Your
server already has the working values, so either keep your existing file's two
`define(...)` lines or paste the real values back when you upload:
- `STRIPE_SECRET`: your `sk_test_…` for testing, `sk_live_…` for go-live.
- `DRIVE_API_KEY`: your existing Google Drive API key.
- `aifod-speaker-media.html` → `APPK` (inside the base64 `atob` block): replace
  the `pk_test_…` value with your `pk_live_…` key.
  To edit the JS: decode the base64 in the `var c=atob("…")` line → change `APPK`
  → re-encode → paste back.

> Live secret keys are intentionally **not** committed here — committing an
> `sk_live_` key would leak it and trigger Stripe/GitHub secret-scanning. Keep it
> on the server only.

## Deploy order
1. Set A + B in `aifod-apps-script.js`, paste into the Apps Script project, then
   **Deploy ▸ Manage deployments ▸ edit ▸ New version ▸ Deploy** (keeps the same
   `/exec` URL already referenced by PHP + HTML).
2. Run `testDelivery_` once from the Apps Script editor to authorize Drive/Gmail
   scopes and confirm a folder is created + email arrives.
3. Upload `stripe-proxy.php` (add speaker folders in `speaker_folders()`).
4. Paste the two HTML files into their WordPress pages.
5. Test with card `4242 4242 4242 4242 · 12/26 · 123`, confirm a selection, and
   verify: gallery link → delivery email → order in the Sheet.
6. When ready, do step C (live keys) on the server.
