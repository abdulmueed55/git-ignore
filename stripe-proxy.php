<?php
/**
 * AIFOD Stripe Proxy — af.net/stripe-proxy.php
 * Geneva Summit 2026 media packages.
 *
 * ── GOING LIVE ──────────────────────────────────────────────────────────────
 * Swap STRIPE_SECRET from the sk_test_ key to your sk_live_ key **on the server
 * only** (edit this file in cPanel/FTP — do NOT commit the live secret to git).
 * Also flip the publishable key in the sales-page HTML (APPK) to pk_live_.
 * ────────────────────────────────────────────────────────────────────────────
 */

define('STRIPE_SECRET', 'sk_test_REPLACE_WITH_YOUR_STRIPE_SECRET_KEY'); /* keep your existing sk_test_ here on the server; swap to sk_live_ when going live */
define('APPS_SCRIPT',   'https://script.google.com/macros/s/AKfycbwPUwEyTiemO_-EkJRIy2DJJ4nHOujkNxKiCbNld8h3JDNdU9p9D0fN1uig2wTs4u3Rtg/exec');
define('ORDERS_FILE',   __DIR__ . '/orders-data/orders.csv');
define('FAIL_LOG',      __DIR__ . '/orders-data/apps-script-fails.log');
define('DRIVE_API_KEY', 'REPLACE_WITH_YOUR_GOOGLE_DRIVE_API_KEY'); /* keep your existing Drive API key here on the server */

/* ── VIDEO PREVIEW ────────────────────────────────────────────────────────────
 * A preview must play in the browser, must not hand over the finished video,
 * and must not make the buyer wait. What actually satisfies all three is a
 * real, self-contained preview CLIP cut from the front of the file:
 *   CLIP_SECONDS  how long that clip is.
 *   CLIP_HEIGHT   the clip is re-encoded down to 720p at CLIP_MAX_KBPS, with
 *                 the AIFOD logo burned in. A straight -c copy cut would keep
 *                 the master's ~16 Mbit/s, i.e. 40 MB for 20 seconds — the same
 *                 "videos load slow" the buyer is complaining about, and a
 *                 master-quality file on the wire. Re-encoded it is ~1.3 MB.
 *   BLOCK/MAX     the source is pulled from Drive in blocks, only as far as the
 *                 clip needs. Those blocks are scratch material — they are
 *                 dropped once the clip exists.
 *   HEAD          only used in the no-ffmpeg fallback below: how much of the
 *                 original this endpoint keeps cached locally.
 * WHY NOT JUST CAP THE ORIGINAL, as this endpoint used to (first 3 MB, then 416
 * for everything else)? Three reasons it kept failing:
 *   - 3 MB of a 16 Mbit/s master is about 1.5 seconds. Playback ran straight
 *     off the end of what the endpoint would serve, and the player reports that
 *     as an error, not as "preview finished".
 *   - whatever Drive answered was forwarded as-is. When Drive replied 403
 *     (its abusive-file gate, or a download-quota trip on a 145 MB file), that
 *     JSON error body went to the browser as a 200 with Drive's content type —
 *     the <video> element got a "successful" response that was not a video and
 *     gave up. That is the "Could not load video." in the screenshot.
 *   - the bytes on the wire were master quality: 40 MB for 20 seconds.
 * A clip has none of those problems: it is a complete file (so it plays and
 * seeks like any other), it ends where it is meant to end, and it is ~1.3 MB.
 * WITHOUT ffmpeg on the server there is no way to make a valid short clip, so
 * the endpoint falls back to serving the original with proper range support
 * (playback works, but the preview is no longer length-limited — see the
 * ffmpeg note in the deployment README). */
/* Length of the preview clip in seconds (0 = the whole video). At 60 the buyer
 * sees essentially all of a ~70 second Summit clip, so what still protects the
 * master is the 720p downscale and the burned-in watermark rather than the
 * length. Lower this if that ever needs tightening. */
define('VIDEO_CLIP_SECONDS',          60);
/* The preview player on the sales page is about 900 px wide, so 576p matches
 * what is actually displayed — 720p would be spending disk and bandwidth on
 * pixels nobody sees. ~2 MB for a 60 s clip against 145 MB of master. */
define('VIDEO_CLIP_HEIGHT',         576);
define('VIDEO_CLIP_MAX_KBPS',      1200);
define('VIDEO_PREVIEW_BLOCK_BYTES',   8 * 1024 * 1024);
define('VIDEO_CLIP_MAX_BLOCKS',       32);   /* runaway guard on source pulled per clip */
define('VIDEO_PREVIEW_HEAD_BYTES',   40 * 1024 * 1024);
/* Ceiling for everything under video-cache/ (least recently used pruned first). */
define('VIDEO_CACHE_BUDGET_BYTES', 2048 * 1024 * 1024);

/* ── FFMPEG ───────────────────────────────────────────────────────────────────
 *   ''      auto-detect ffmpeg on PATH (also /usr/bin, /usr/local/bin)
 *   'path'  use this binary, e.g. '/usr/bin/ffmpeg'
 *   'off'   never shell out (preview falls back to serving the original)
 * Check with: stripe-proxy.php?action=videoDiag&key=YOUR_ADMIN_KEY */
define('VIDEO_FFMPEG', '');
define('VIDEO_POSTER_SECOND', 8);   /* how far in to grab each thumbnail frame */

/* Admin key for viewOrders/clearThumbCache/resetOrders — rotate this if it's
 * ever typed/screenshotted somewhere it shouldn't be. Keep your real value on
 * the server only (never commit it — same rule as STRIPE_SECRET above); must
 * match ADMIN_KEY in aifod-apps-script.js exactly (clearOrders/clearDeliveries
 * there check the same value). */
define('ADMIN_KEY', 'PASTE_YOUR_ADMIN_KEY_HERE');

/* Stripe webhook signing secret — from Stripe Dashboard ▸ Developers ▸
 * Webhooks ▸ (your endpoint) ▸ "Signing secret", after adding an endpoint
 * pointing at ?action=stripeWebhook listening for payment_intent.succeeded.
 * Without this correctly set, stripeWebhook rejects every request (fails
 * closed — never trusts an unverified "payment succeeded" claim). */
define('STRIPE_WEBHOOK_SECRET', 'whsec_REPLACE_WITH_YOUR_WEBHOOK_SIGNING_SECRET');

/* Build id of this file. Bumped whenever it changes, so there is a way to tell
 * from the outside which copy a server is actually running — an uploaded file
 * that appears to change nothing is usually either sitting in a different
 * directory or still cached by opcache, and guessing between those wastes more
 * time than printing a version does. Read it with ?action=version. */
define('AIFOD_PROXY_VERSION', '2026-08-24.1');

/* Run from the shell as well as over HTTP:
 *   php stripe-proxy.php action=prewarmVideos key=... max=100
 * Building sixty preview clips takes longer than any web server will hold a
 * request open, and a run killed halfway wastes the download it had done. From
 * the CLI there is no timeout to lose. */
if (PHP_SAPI === 'cli') {
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (strpos($arg, '=') === false) continue;
        list($k, $v) = explode('=', $arg, 2);
        $_GET[$k] = $v;
    }
    $_SERVER['HTTP_REFERER']   = 'https://af.net/cli';
    $_SERVER['REQUEST_METHOD'] = 'GET';   /* the HTTP paths below assume one */
}

ob_start(); /* Buffer output to prevent warnings corrupting JSON */
header('Content-Type: application/json');
/* These are API answers about live state — an order list, a diagnostic, a
 * sold-out check — and none of them may ever be replayed from a cache. Without
 * this a browser (or a page cache in front of the site) will happily serve a
 * stale videoDiag from before an upload, which reads exactly like the upload
 * having failed. Endpoints that DO want caching — getThumb, streamVideo — set
 * their own Cache-Control further down, which replaces this. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: https://af.net');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); exit; }

/* SECURITY: blocks the exact "paste the streamVideo/getThumb URL straight
 * into a new browser tab" access path — a legitimate request always arrives
 * as a sub-resource of a page on af.net (the sales page's <video>/<img>
 * src, or the gallery review page), so the browser attaches a Referer
 * header pointing at af.net. A URL pasted directly into the address bar, a
 * link shared in chat, or a bookmark carries no Referer at all and gets
 * rejected here. Not bulletproof — Referer is client-supplied and a script
 * (curl, Postman) can fake it — but it stops casual link-sharing/reuse,
 * which is the realistic threat for a leaked fileId. */
function require_af_referer_or_die() {
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $host = strtolower(parse_url($ref, PHP_URL_HOST) ?: '');
    if ($host !== 'af.net' && $host !== 'www.af.net') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

/* Every working directory this script creates (order records, gallery tokens,
 * caches, preview clips) lives beside the script itself, i.e. inside the web
 * root — so without this they are all reachable by URL. af.net/orders-data/
 * orders.csv would hand over every buyer's name and email, and a cached
 * preview clip would be downloadable with none of the referer checks that
 * guard streamVideo. Drop a deny rule in as soon as a directory is created.
 * Apache honours this; if the site is ever moved behind nginx the same
 * directories must be blocked in the server config instead. */
function protect_dir_($dir) {
    $ht = rtrim($dir, '/') . '/.htaccess';
    if (is_file($ht)) return;
    @file_put_contents($ht,
        "# Written by stripe-proxy.php — do not serve this directory over HTTP.\n" .
        "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
        "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
}

function ensure_private_dir_($dir, $mode = 0755) {
    if (!is_dir($dir)) @mkdir($dir, $mode, true);
    protect_dir_($dir);
    return $dir;
}

/* ── PACKAGE ENTITLEMENTS — single source of truth (mirrors Apps Script) ──
 *   limit  : selection_limit (null = no selection, deliver everything entitled)
 *   photos : 'all' | 'limited' | 'none'
 *   video  : true | false
 */
function packages() {
    return [
        'Select 25 Photos'      => ['limit'=>25,   'photos'=>'limited', 'video'=>false],
        'Select 50 Photos'      => ['limit'=>50,   'photos'=>'limited', 'video'=>false],
        'All Photos Super XL'   => ['limit'=>null, 'photos'=>'all',     'video'=>false],
        'Video Package'         => ['limit'=>null, 'photos'=>'none',    'video'=>true],
        'Complete Media Bundle' => ['limit'=>null, 'photos'=>'all',     'video'=>true],
    ];
}
function pkg_info($name) {
    $p = packages();
    return $p[$name] ?? null;
}

/* ── SPEAKER → MASTER DRIVE FOLDER ──────────────────────────────────────────
 * Keep in sync with SPEAKER_FOLDERS in aifod-apps-script.js and AP_FOLDERS in
 * the sales-page HTML. TODO: add every speaker as 'Name' => 'DRIVE_FOLDER_ID'.
 */
function speaker_folders() {
    return [
        'Test Speaker' => '1j_MWqd6dS7qhAyReOWq-K2swFt4FRj7U',
        'Tianze Zhang' => '1VF32ITyqbhFDPDRDqQORBys0mXyFtjZ6',
        'Chet Greene' => '1I4gHEdPg-6TZrFadxUzqvFsiPZO4d1KO',
        'Tšiu Khathibe' => '1fOhPqeFXqatfCgtZsuLQZuc-kxEP7FGz',
        'Muhammadou M.O. Kah' => '1JOO13OIyJcGHDpOueeNpNyxpEX-iWw72',
        'Labeeqah Schuurman' => '1Ej9dG7twgDQdvYu9imBHQxfzEzNkEymG',
        'Kiyoshi Adachi' => '17BYsmIhQx5b9R_MC1TZEzNivG7ERM-Du',
        'Gladwin Mendez' => '1e9wDiqhMvQWfq2q7_UEHklMNuY5wh0BH',
        'Sundara Rajan Aravamuthan' => '1SpOGbSfBYz78n8tvVwBuzoSEUkyC2pui',
        'Shiva Kumar' => '10bsh-LAQBMC0NAPIZqxs7RfGcMUbH7aU',
        'Dimitri Boylan' => '1KJvcXn03fMRZlwKqinWIa4I8CiAbAJnt',
        'Giorgio Bortoli' => '1-kSgas39Y4xKUzS-eJZ7mO_MLg8LFVT7',
        'Susana Falcão' => '1h4ho4GSqiYq357Z3KXszVo4S1TsLCO4o',
        'Tola Yusuf' => '11KltZY4WnRImAcq8NLJLZKuP8m5f4QD_',
        'Hesti Aryani' => '1jCvpUYEulkqlpSOkPfsThUOD_tYiJ1Qf',
        'Ivana Brutenic' => '1vg8K1yB4CqjKK8sjLNEuOQ3Crzw7BnmJ',
        'Hyunwoo Kim' => '1ubrdtW2cZuZcTFXnq5UPp5P79PVbtm7Q',
        'Anand Tatavarthi' => '1meHEHBckUWpiNlsC8fRDL-q1Djhpe53h',
        'Kunal Panchamia' => '1U3J7x5xmr1c76dqkoHzgnTKCGCO9znPf',
        'Pascal Hetzscholdt' => '12fNTE573H4lb3zNstPXkhK7yxSieJ_Fe',
        'Abasiama Idaresit' => '1a12Ztja663DvrMCfh5MMTYU7_RlC3oEe',
        'Jean-Simon Venne' => '1XgM3GAH5QPjGOa9nisWxySDKq2KfcVRc',
        'Boris Grivič' => '1REfRDmrvO1_xsGAof3nWRSYTqyZ1bxrw',
        'Andrei Dumitrica' => '1EOQPZjUIMu23MM7D-AMraFYGYlhl3aM5',
        'Nagesh Sharma' => '1eFi-j7F8YLlMKxi3gjIbHCS7xAxWhm4k',
        'Erin Black' => '1wkmLy-XE_UXxnyLXO3Mw8o8RYfwUr4sC',
        'Robert Hortschitz' => '1LAXbIkUAbP3Q17PjEWWm02KdLxKnTzos',
        'Patricia Krall' => '1-UHqq3ftFd9NWw8_n8ns7Z7qYV11uYcY',
        'Valerie Pelton' => '1GNu_g0z7r_l2Qov29W59dItWcUTIBcPm',
        'Veronika Stoka' => '1CWMAW35Mp6GEO_ajBkyhFujVuyY7yHTf',
        'Alexander Inchbald' => '1iOEgx9wfoJIsJOVmt30ImdTrGWnR_qRl',
        'Adina Suciu' => '14PqbLyQv0qyIN49KEPpeCeG2O5d4wc1S',
        'Mikael Loefstrand' => '1b62aiqJ01WdNuVumzyjv4Iu8l7mP0Ixd',
        'Craig Gibbs' => '1yVgYAwX4EWd9PkiUw0FJ8QLgp2mgu29n',
        'Vuk Vegezzi' => '1D-yHPPxlQrg16E6zhP-zav79slqmyQzH',
        'Alejandro Castañeira' => '1sQ--vqsYH7KCZXglNs0tP-2iB3CkUWb4',
        'Nelson T. Ajulo' => '1VJiMAzGsrCDbeMH6NfBhUSn4qg9uc_gu',
        'Bernardo Cartoni' => '1NO2xH4uJfNAyP788BVGG6gj8Mt8NNYsV',
        'Michael Che Bugembe' => '1QAxBMJof-7YWnGpwm8zr1Z86g2rhelre',
        'Rory Macmillan' => '1rma31J_TUNmk_datgcmgaKVQQM6ZP7wu',
        'Guillaume Lamothe' => '1y1WfMY_J2vXLel7kxSbNpmeCczaFKyZU',
        'Joy-Marie King' => '1pSWRCAbaSa5u7d4hgIPGCEMXQpSMtzoK',
        'Patrick Vlačič' => '1dgkNu6_i_ZwyMLvpCKYUVO1yj8nBdKHx',
        'Sheila Jagannathan' => '1-g_8ya5Zd1YzknS5csHMthcwzi3vU18Q',
        'Frederic Emane' => '1-O_gNzOfgOePgTOBPlMugqHQvObNCa4X',
        'Rene Kok' => '1vkUjAaZhK23J1zoSfPHhy_70rTS3waTp',
        'Kashif Ul Haq' => '1rJ244ijAcXgXesaWJ8CPX1dXlLEOUfDE',
        'Chirag Dhirajlal Lakhani' => '1FnZu6QoIBiDHRuXcla3IY7rxIduqjM9H',
        'Joanne Sweeney' => '1nG_1TUckC5N8pmZgtEnoT4ewMPd_6OGL',
        'John Hemery' => '1WJkObWieZR-gFk-Jwzbwi42TJEHigCeN',
        'Marybelle Cherfan' => '1BLEriugIP_YLCETwR1tE2EP7vbOyVldV',
        'Alexandria Cogdill' => '1NX5bDjlumW9mJfP2n5u5IXWJmvDZaCqq',
        'Jon-Hans Coetzer' => '1wtqG_y6u8V5LUlGEIUDlrU_kKSFIWSRx',
        'Ivica Srncevic' => '1dG1Ss2ewJRdpGnOHKU11QpKarbj2aBZw',
        'Vibhav Mithal' => '17LNGOG2i2RM-fMShocRAI5SwINOVwpd2',
        'Georg Zangl' => '1y8XQqRvmpDQfZ_fP7q0s3g4xjUEyoWp8',
        'Sakthikumar Ramachandran' => '1K8xTLzaC9n2OKx8bCCBhTTlY-cHe8UtZ',
        'Vinod Sood' => '1T3xr_NAZmrawsroqwzHrkLMMKEBKHatF',
        'Fredrik Kocon' => '1kZ8WUdgIvmtVJ5PlVzhnshdl_zQQLsEj',
        'Elżbieta Deja' => '1fVUT7rTW-LZO0R3N6BccVyZg3ZfbYw6z',
        'Rosalie Palmer' => '14CmII1viKi0NiK69kBHPkO8blWQKL0xi',
        'Deniz Şerifoğlu' => '1NhSqt9IopGzcL_ef4EOt0X9twjF8foTG',
        'Kenechi Okeleke' => '1K9egsl9cv2KJTlRjoDfmPDErTQlKMThs',
        'Danielle Brault' => '1DsYxPcApjR--MA1BdbSqGCKLphLPBRWc',
        'Jeremy Stein' => '15gDY1sIn-ty3k5vD66HpIq--xVrMNRpr',
        'Derrick Davis' => '1CKt5lJaOJlR_kYcGse8z7hKDj1Rl8RkZ',
        'Dorian Vanhorenbeeck' => '1oWMvn9xwOtuwVIQGboxla49LT9NTZM3J',
        'Daniel Ojdanic' => '1rAYkUIC5SRQ0QdHTs-7hKWDEEAt7Gefb',
        'David Williams' => '1JphI7m7llxJ2t7aEPh9rf9Hx0qAIfhvZ',
        'Eric Famanas' => '1Uwdn4RJFgFpnPe1suVA_9VJB76wvEYtK',
        'Nobuyuki Ota' => '1OUoLWjhQmG8Jo0mz1zhPEL8TpRzctbS6',
        'Tan James Anthony' => '16aPPnZdebl-QdiJJE_PB3UoehxtNBp8G',
        'Sönke Lund' => '1Ml0ckn0auetzWzFmH4PBlDQvBDGHTEsn',
        'Sven Soomuste' => '1NJA-KAYNNu5tZYs9rDdij_Z3lUx3AUzV',
        'Ruth Gafni' => '1bcNayw2HMWscDBBZ0R1iIC6RWmHbcETN',
        'Flavio Bordignon' => '1_UCT-VTTcctGqmCBZIS1IB6C8HmfroTo',
        'Pavlo Yalovol' => '1nrM9_LSDiYJXQUCa3Aq6DpFj4jmBg03G',
        'Shikhin Agarwal' => '14VmsWzUsayJH81RXu305toY4KZcq5pnH',
        'Marisa Boller' => '11ueITkok40LoqF3yVfeyCScLCZtozieq',
        'Muhammad Jawad Ali' => '1C8Hx7S5-rgPIQceRhDrBtmye8cDkToy0',
        'Bruce Mellado' => '1McNUxUmu-6VpeZ6dC6xd9TZYqMpvkyzo',
        'Dip Nandi' => '1LKYfmJa4HSMzWgLbhhpEXWQeBgLjIDDV',
        'Ezekiel Barclay Pajibo' => '10q1_4ZtjbFweQEkfqXVtwyYzpIMTwuys',
        'Daniel Osuna' => '1wZQTcGQSHV1cH4Yu7HRl63rQX1u5Wn6L',
        'Helen Pino Vera' => '1bD1KMBY4J0VQqBBMk_3ZUNJpFvbF5EU4',
        'Oyedayo Otokiti' => '12QfMVOwcHGD2bQGn_Q1TLGKbOkLFvE1r',
        'Frédéric Jallat' => '10CDAHMm-XNYloSyZ-GLMGxj1Sb12YgdF',
        'Nathalie Simille' => '12I1sNwHAp4a3ADCZv51jur5OJyM4yCP4',
        'Orsolya Kneitner' => '18An29c0vrtsPM56ItKH2jsn2dfKUBUe4',
        'Konrad Sztolc' => '1YUs6rhRlcMljMPmGkpLt3o4T4qnfLXro',
        'Fabrizio Degni' => '1QGFC3tAebjem5PgM3PQloDq6lH0wnVW5',
        'Rahul Ghatalia' => '1uEjgjafig9qc7NPStORebN5H5jM2oPBP',
        'Fiorenzo Manganiello' => '1Iu5uuGkE7qtJQUwIhWQ7a9Vb8lscOY2m',
        'Stephen Cornish' => '1ajTmxVpee_Uc3Q9x_f63zjtmojSIpGYk',
        'Raja Gopalan Balasubramanyan' => '12cpgbJI-R-MizOZqLan64t31hexR2-Gq',
        'Rui Alexandre Castanho' => '1LAhFWjmPmEfOkyAbCdM0TYSdoS5tZ_RB',
        'Peter Ruggle' => '1pXTdHd7BpZSxAa-y6zGEOgF1cJJ0zSB3',
        'Frederick Tschernutter' => '1DxxyBQC__Naz5Mz8ug4aeiH52_ec1wR6',
        'Sreeraj PM' => '1v-2FSa04jHEAmWyA7a9RCycXtd8Y4Bnb',
        'Philip Leaper' => '1v6AAd9CoHKEJcqkUJ-YWyrIihQtq3TfO',
        'Tijn van der Zant' => '1VjKQReqZJUyIjJxx9MkzAY3VuV3C1T6q',
        'Wolfgang Pinegger' => '128rQksdJOzppB13Fc7xwWj_sdnQpp0-E',
        'Yogesh Gupta' => '121z85HFDEJCXNBpzLO7JFIAWKi3AvyYz',
        'Wilhelm Loderer' => '1l87jGNOE5Og-3xgiPFqTOi8vVLcu8Cwe',
        'Velynne Ji' => '1muQe6IY-vTQzgjT0sdhxqBD7NYHHRShO',
        'Carmen Lamagna' => '13CohitmG4xU0pC_rrec65UAgCfhFc80Q',
        'Cyrille C. Catel' => '1Kh5oTsUpMykqoQuhbxbsUuJK1IYIDyhe',
        'Manohar Kosuru' => '1xm8fnSmHiY4H9tkIQ5ygafupQB-YFKt1',
        'Oliver Ropke' => '1sEYWDQOUlwiTqIoBsvi37OzDwIyqTwEj',
        // add remaining speakers as 'Name' => 'DRIVE_FOLDER_ID'
    ];
}

/* ── SPEAKER → VIDEO DRIVE FOLDER ────────────────────────────────────────────
 * Separate collection from speaker_folders() above — videos live in their own
 * per-speaker folder tree (Siphiwe's collection), not mixed in with photos.
 * Keep in sync with SPEAKER_VIDEO_FOLDERS in aifod-apps-script.js and
 * AP_VIDEO_FOLDERS in the sales-page HTML.
 */
function speaker_video_folders() {
    return [
        'Ayu Indirawanty'        => '1D51va7qxHVv_ayq0T6c38hvSuI_fwmg5',
        'Abasiama Idaresit'      => '15-N1qoiCq1hiQeOhw-U9sskLL-2gXznZ',
        'Adina Suciu'            => '1LJH3gkDfMQlWfspY5ia4HqE1TZGkigSI',
        'Alejandro Castañeira'   => '1hFG8d3LsfWyAL9mp_JiIOMPmQp_3hOVH',
        'Alexander Inchbald'     => '1PflfI9fAese37bEoGcplJw1WDvqIAPDn',
        'Alexandria Cogdill'     => '1b4l7yFf1TYxTXy2Pc7intaxOJBohuagt',
        'Anand Tatavarthi'       => '1aWGYCBhCaMSm5wOqIQ5P7oAm3AclVLdl',
        'Bernardo Cartoni'       => '1xvdaOLKvxhsxZa-ykN-ozo4PHZylH0Qv',
        'Craig Gibbs'            => '1A86ZG0QZCV_G8rXvNHqtlj8gx76ZmCIU',
        'Deniz Şerifoğlu'        => '1jhUmR9kuExp69C10iK5-FPMdHl8LbwGv',
        'Boris Grivič'           => '1zfc7rXYSg4ruAAtVPWedTfcYpznA46sR',
        'Bruce Mellado'          => '1dLjreAqiM1rdDRCqAndxgS2Ej-0732qC',
        'Chirag Dhirajlal Lakhani' => '1fYXy_DHboEXLF78iQTd9n2GsBtAWHE7T',
        'Daniel Ojdanic'         => '11uXms5LllH9HRSq8MF8sp_e4acSNDQQ7',
        'Daniel Osuna'           => '1nE9Aq4wGtTMRSMC5aYCY5862bHUhzicd',
        'Danielle Brault'        => '1XKz79MaGOP7lEEELUry7YZ5bXBRBd0JO',
        'Dip Nandi'              => '1wxv711N1y-ymiXVZg7FMRrL01lKLMjoO',
        'Dorian Vanhorenbeeck'   => '1RdrdmsugiwNjwcciNMcFcC4PMXv0FOBz',
        'Erin Black'             => '1AVGxXwbBXzbGun2j4aapXUlKJGJW4Ab_',
        'Flavio Bordignon'       => '1fVEbqioBt0NjQH6ZiX2p8z-iSE21CP0B',
        'Frederick Tschernutter' => '1s8Xig6VxMep8Td4paz0s_r9nUKGG_ozq',
        'Georg Zangl'            => '1d7ZoSpBwh0KSp3SqKo8iVkjMLvFexEIe',
        'Guillaume Lamothe'      => '1Fnr-ZFXhNDf3cmuQIXnRBZVUr4R9Bc6J',
        'Tšiu Khathibe'          => '1TQnMcKH5_9bqS9LeatQjGdkACGGsIO8i',
        'Hyunwoo Kim'            => '1PG4_Cj_Evy-5cSA1CcFjXnmH4-Qyvaz-',
        'Jean-Simon Venne'       => '1PjBmqzmk_NHAvXNMJhsrw6QWj5aTz0Yr',
        'Jeremy Stein'           => '1Kg18h6_gsf1fQCl3eEUC8r3JG8hfLL1U',
        'John Hemery'            => '1FT-EcDVVGFc667cGDK0iMFe5Q8iC78CD',
        'Jon-Hans Coetzer'       => '1Hz1Ply9aOkPO-JvnEXa04myymA0uKQtm',
        'Joy-Marie King'         => '1ZmFxGGylGNLpp2lu_7nDamCNsariYyJk',
        'Chet Greene'            => '1dI5632WTsEqvPTNPzfn2gyo-ghzSHYIu',
        'Jon Ray'                => '1XkWgkZvGAOHUP1ecKgT_8C_yaLdriYg_',
        'David Williams'         => '1-g-Wva4H0PnTSqUorfuQVO4Sria_1YJS',
        'Derrick Davis'          => '1syjgbFr8TwdteoLGa_3P8Va6Vlt8VmIt',
        'Dimitri Boylan'         => '1Mg-nBcIW1TXl6Xdw3cvVUTaGQR_fumvW',
        'Elżbieta Deja'          => '1OqrnhiAF8Q7_OSHqaTTH-hrh87zF9jDa',
        'Eric Famanas'           => '1fQ9BAPq_T2Fv4y2ZnSr7Hw17OgpGEg04',
        'Ezekiel Barclay Pajibo' => '1GAfcRRL5H7siLuPO_UNw1Xc2f_DvPN4d',
        'Fabrizio Degni'         => '1nMMoU12Y1PLKjSJ_41FJVtudxVYs88IN',
        'Frederic Emane'         => '1n4ca6xtdCT_WFSuNlAqwEQq6Vy9O6ahx',
        'Frédéric Jallat'        => '1epYzKtFFB3OaNoL_0ixNiZdlvOoUc-1M',
        'Fredrik Kocon'          => '1vOatVDrTH8Z_owSDgO_SLChIsBBhxkq_',
        'Giorgio Bortoli'        => '1bDv60ObDt9lGiypGJPa7FZQWFkNm4Dbt',
        'Harsh Vardhan'          => '1sSdStWC1tNMBUPxYo9g4j6exagDVpBGu',
        'Helen Pino Vera'        => '1I95xNJGwfRPbbjiCQjEe62upw6SkWV0y',
        'Ivana Brutenic'         => '14lb9ia2FO8_tB_49fyRnd0O7mcmi1q-0',
        'Ivica Srncevic'         => '1NeXZtBfVQI26Ml7agWqGoQPF2SMBDm9P',
        'Yogesh Gupta'           => '1wsj8fAHwG1dpTf2rbPvXN6SXCEPFzLxd',
        'Hesti Aryani'           => '1p0263_Aj_9E0DsiuNjG_oU2aB9PWVzGD',
        'Joanne Sweeney'         => '1wczbXBqIW6YU5JJ-2xmpnVTqB19wS4yV',
        'Wilhelm Loderer'        => '1PbCckXhARJ7e4PADBGoNVgtpigxQc9Rq',
        'Wolfgang Pinegger'      => '1yBoTx68RtSsSb4Xds6GfsH7FhAQB7XEe',
        'Labeeqah Schuurman'     => '12HEUXq0dZddyUTdgd5sE2lSeCn3Bzzmj',
        'Kunal Panchamia'        => '1KowTgqfhiHXyTCJOLM4YGZoKWf3ajbt0',
        'Gladwin Mendez'         => '1o1KCwHhHIlpk1rXvCQ7ZF6tv7-IOxeAy',
        'Vinod Sood'             => '10XQm31fsipnjA1kFl4eJWokKh02EK5Tx',
        'Kiyoshi Adachi'         => '1x0_ABknGtz-WIqMndhHhbcG6B7JRf_Pb',
        'Kenechi Okeleke'        => '1EOKYjtyV1FSzmo2rHrDM8YputSiu-mdi',
        'Kashif Ul Haq'          => '15H7cKiug8kVLXAfqEti25V9a0aOWqSAZ',
        'Andrei Dumitrica'       => '1f-2RiakHHq-dQCw6N2VR9rQEbMwUlPYX',
        'Vuk Vegezzi'            => '1ezIKk1XOYL0Y-QDxhByeWArsBNFf6RpO',

        /* Added from the master video collection: these speakers' videos were
         * uploaded after the first mapping run. */
        'Marisa Boller' => '1slHhM9eBvXVo1TPOQLbhAASrYAAZLwIK',
        'Marybelle Cherfan' => '1QK5CtS_tJfJcuOBA8Z0W6NUI_RvcY2_S',
        'Michael Che Bugembe' => '1bPft4HorHRtFkwb7kzICNZKo_OkfqOgt',
        'Mikael Loefstrand' => '1XJW1jIxdvDf38mprllekwMsZCb38121_',
        'Muhammad Jawad Ali' => '1bFaWi-OH6RvU1Z42ALvS1Tol74OI-jZK',
        'Nagesh Sharma' => '1pQhFcKVC2OQEYh1cTuVkJuYl3bj0abGV',
        'Nathalie Simille' => '1IYtT21EioWUbZrijkp3AnILsKmvCvB_d',
        'Nelson T. Ajulo' => '1vTjG5QIHL5FHk85mtgt1WrlOlGI6XL8z',
        'Nidhi' => '1bZtF7Tef43EbF9guqyfGskVM170a4Utz',
        'Nobuyuki Ota' => '1lOQrN-g4K42tYFiBHCB5JMk62stO9-RY',
        'Tianze Zhang' => '10OOWgpcO-YYZ5c0BXdDmJADgEEABtwnj',
        'Tijn van der Zant' => '1k2pOjmfqk_9vcnuNXsgsds1VYNfRIRtP',
        'Tola Yusuf' => '1qV9T6Ql2kJDLO4r5OTH8sxqS13c5TC8m',
        'Valerie Pelton' => '1nAqLIqG1sgeihyAjmPSNCI3J5Noylb9c',
        'Velynne Ji' => '1JWwzxdRulI3XGJSvh7jm7sRAQwI8KgGs',
        'Veronika Stoka' => '1XOZ-W1PqpjRdte5RNEY8vp4XPDEPgTXu',
        'Vibhav Mithal' => '1tjZ8ZhAFLCKZjPXDh4EqRl3H1nfyGqwJ',
        'Orsolya Kneitner' => '1Bfxubimn0XdfJI-UO_rY5DQbSalrKhid',
        'Osama Abdalla' => '1WsKgketpkEBCljkCX5o9Ysx443vs0fm2',
        'Oyedayo Otokiti' => '1V7P9gM0Rx85PR0knwvCzPguwtH6bL7Mw',
        'Tan James Anthony' => '1BFv4NcLD0o8E0244ZBJE1D-BP5dZjp2P',
        'Paola' => '14WdRorEIr6ZnmT58-kOQMa4EShQc7-3K',
        'Sven Soomuste' => '110-VdXK6AErK71XdjhHVZretshVXCpJ-',
        'Pascal Hetzscholdt' => '10Giire1VsfdE8hgiiru_zgnZGKHe_UWi',
        'Patricia Krall' => '1v9kAbZInS2vz2rU93E8FY1hF3cMXpvro',
        'Patrick Vlačič' => '11ZMNS9NVfsM3dNHneF5kvNtrHEMVyMkA',
        'Peter Ruggle' => '1iC6CfnjmzNN3C89EW5QaolYQA2FUZ3MC',
        'Suhail Nigar' => '15Z9Vihxl9cxgjfrftY5o8Qwhi25v_lG4',
        'Philip Leaper' => '1rXfBKMwhtzujgX2kX8TEDu27MWrxbsrj',
        'Rahul Ghatalia' => '1AL3jmR6IjcHUDGpCZZXONFkxWTg7xFuf',
        'Raja Gopalan Balasubramanyan' => '1iisySc12UHEKM5-v0FakYbPC8lC5wMPb',
        'Rene Kok' => '1GfSzdupBFxcN3A8qikJG2E64xiPPNBo8',
        'Rory Macmillan' => '1dN_Of0msL2T7mAkLsv5K_F3K4Qscj0wt',
        'Rui Alexandre Castanho' => '1wpDabUdOKIuGQFwhwjcQPzTF1HaMvpBY',
        'Ruth Gafni' => '1AL_h1a1E4zEMRifPBHvR8bF3aEE40jsh',
        'Sheila Jagannathan' => '1UCBLxgqUjji-ou6gQC1EpUOzVLE2Q8e6',
        'Shiva Kumar' => '1jzb2twIXgObAwYJAf7ZE8YxbMK8XcyR9',
        'Sönke Lund' => '1Gcl_lkr4nsVe_WaXNyaoh7F_Hth7yOkG',
        'Sreeraj PM' => '1fE_b-3Q-DRel6iaZSLsg17jjpgdQstxg',
        'Stephen Cornish' => '1KT90xSm3_R_5GLV8Qm7GGF4f3y4kAzus',
        'Sundara Rajan Aravamuthan' => '11RtwSL_X1bBi3mpOQRBWh9n-XKPpwPDW',
        'Susana Falcão' => '1951BlPGbf-npV1PZ4Li6bzLKQizVOrep',
        'Sakthikumar Ramachandran' => '1ONpoLftAtXqjk4MV0fVeCV2nVy8snJu7',
    ];
}

$action = $_GET['action'] ?? '';

/* ══ 1. Create Payment Intent ══ */
if ($action === 'createIntent') {
    $buyerName   = trim($_GET['buyerName']   ?? '');
    $buyerEmail  = trim($_GET['buyerEmail']  ?? '');
    $speakerName = trim($_GET['speakerName'] ?? '');
    $pkg         = trim($_GET['pkg']         ?? '');
    $amount      = intval($_GET['amount']    ?? 0);

    if (!$amount || !$buyerEmail) {
        echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
    }

    /* One package per speaker — enforced server-side so a stale/tampered
     * front-end can never start a second payment for an already-sold speaker. */
    if ($speakerName && speaker_already_sold($speakerName)) {
        echo json_encode(['ok'=>false,'error'=>'This speaker\'s package has already been purchased.']); exit;
    }

    /* Media must actually exist before it can be sold — no pre-orders.
     * Enforced server-side (not just greyed out in the UI) so a tampered
     * browser can't buy a package for media that isn't uploaded yet. */
    $info = pkg_info($pkg);
    if ($info) {
        $needsPhotos = ($info['photos'] === 'all' || $info['photos'] === 'limited');
        $needsVideo  = $info['video'];
        if ($needsPhotos && empty(speaker_folders()[$speakerName])) {
            echo json_encode(['ok'=>false,'error'=>'Photos for this speaker are not available yet.']); exit;
        }
        if ($needsVideo && empty(speaker_video_folders()[$speakerName])) {
            echo json_encode(['ok'=>false,'error'=>'Video for this speaker is not available yet.']); exit;
        }
    }

    $result = stripe_call('POST', '/v1/payment_intents', [
        'amount'                => $amount * 100,
        'currency'              => 'eur',
        'receipt_email'         => $buyerEmail,
        'description'           => $pkg . ' — AIFOD Geneva Summit 2026 — Speaker: ' . $speakerName,
        'metadata[buyerName]'   => $buyerName,
        'metadata[buyerEmail]'  => $buyerEmail,
        'metadata[speakerName]' => $speakerName,
        'metadata[package]'     => $pkg,
        'metadata[price]'       => $amount,
        /* Marks this payment as belonging to the media-package flow. The same
         * Stripe account takes other AIFOD payments (registrations and so on),
         * and reconcileOrders must never mistake one of those for a media
         * order and email that buyer a gallery link. */
        'metadata[source]'      => 'aifod-media',
    ]);

    if (!empty($result['client_secret'])) {
        echo json_encode(['ok'=>true, 'clientSecret'=>$result['client_secret'], 'piId'=>$result['id']]);
    } else {
        echo json_encode(['ok'=>false, 'error'=>$result['error']['message'] ?? 'Stripe error']);
    }
    exit;
}

/* ══ 2. Record Order ══ */
if ($action === 'recordOrder') {
    $piId        = trim($_GET['piId']        ?? '');
    $buyerName   = trim($_GET['buyerName']   ?? '');
    $buyerEmail  = trim($_GET['buyerEmail']  ?? '');
    $speakerName = trim($_GET['speakerName'] ?? '');
    $pkg         = trim($_GET['pkg']         ?? '');
    $amount      = trim($_GET['amount']      ?? '');

    if (!$piId) { echo json_encode(['ok'=>false,'error'=>'No piId']); exit; }

    $pi = stripe_call('GET', '/v1/payment_intents/' . urlencode($piId));
    if (($pi['status'] ?? '') !== 'succeeded') {
        echo json_encode(['ok'=>false,'error'=>'Not paid: '.($pi['status']??'unknown')]); exit;
    }

    $result = record_paid_order($piId, $pi['metadata'] ?? [], $buyerName, $buyerEmail, $speakerName, $pkg, $amount);
    echo json_encode(['ok'=>true,'speaker'=>$result['speakerName']]);
    exit;
}

/* ══ 3. Get Sold Speakers ══ */
if ($action === 'getSold') {
    $sold = [];
    if (file_exists(ORDERS_FILE)) {
        $fp = fopen(ORDERS_FILE, 'r');
        fgetcsv($fp);
        while (($row = fgetcsv($fp)) !== false) {
            if (isset($row[3]) && $row[3] !== '' && ($row[4]??'') === 'Paid') $sold[] = $row[3];
        }
        fclose($fp);
    }
    echo json_encode(['sold'=>$sold]);
    exit;
}

/* ══ 3b. Which build is live? ══
 * stripe-proxy.php?action=version — no key needed, returns only a build id and
 * the directory this file runs from, which is all you need to confirm an upload
 * actually replaced the file the site is serving. */
if ($action === 'version') {
    echo json_encode([
        'ok'      => true,
        'version' => AIFOD_PROXY_VERSION,
        'dir'     => __DIR__,
        'php'     => PHP_VERSION,
        'fileTime'=> date('Y-m-d H:i:s', (int)@filemtime(__FILE__)),
    ]);
    exit;
}

/* ══ 3c. Can this server encode at all? ══
 * stripe-proxy.php?action=videoSelfTest&key=YOUR_ADMIN_KEY
 * Encodes one second of generated colour with the same settings the real clips
 * use — no Drive, no speaker media. If this fails, the problem is ffmpeg or the
 * environment; if it passes but a clip still fails, the problem is the source
 * or the download. Also reports free disk space, since a build needs room for
 * the source plus the clip. */
if ($action === 'videoSelfTest' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    @set_time_limit(0);
    $bin = video_ffmpeg_bin();
    $dir = video_cache_dir();
    $out = ['ok' => true, 'version' => AIFOD_PROXY_VERSION, 'ffmpeg' => $bin ?: false,
            'freeDiskMB' => ($f = @disk_free_space($dir)) !== false ? (int)round($f / 1048576) : null];

    if (!$bin) {
        $out['ok'] = false;
        $out['error'] = 'No ffmpeg — run action=videoDiag';
        echo json_encode($out); exit;
    }

    $tmp = $dir . 'selftest-' . getmypid() . '.mp4';
    @unlink($tmp);
    $err = '';
    video_run([
        $bin, '-v', 'error', '-y',
        '-f', 'lavfi', '-i', 'color=c=navy:s=1280x720:d=1',
        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '30',
        '-profile:v', 'main', '-level', '4.0', '-pix_fmt', 'yuv420p',
        '-movflags', '+faststart', '-f', 'mp4', $tmp,
    ], 60, null, $err);

    clearstatcache(true, $tmp);
    $bytes = is_file($tmp) ? (int)filesize($tmp) : 0;
    @unlink($tmp);

    $out['encoded']  = $bytes > 0 ? $bytes . ' bytes' : false;
    $out['ffmpegSaid'] = $err !== '' ? $err : null;
    if (!$bytes) {
        $out['ok'] = false;
        $out['error'] = 'ffmpeg ran but produced nothing — see ffmpegSaid';
    }
    echo json_encode($out);
    exit;
}

/* ══ 4. List Drive Folder Files ══
 * Photos and videos now live in two SEPARATE per-speaker folder trees, so this
 * resolves both for the given speaker and merges the results. `folderId` is
 * kept as a fallback (photos only) for any old caller that still uses it. */
if ($action === 'listFolder') {
    $speaker  = trim($_GET['speaker']  ?? '');
    $folderId = $_GET['folderId'] ?? '';

    $images = []; $videos = [];

    if ($speaker !== '') {
        $photoId = speaker_folders()[$speaker] ?? '';
        $videoId = speaker_video_folders()[$speaker] ?? '';
        if (!$photoId && !$videoId) { echo json_encode(['ok'=>false,'error'=>'No folder for speaker']); exit; }

        $cacheKey = 'lf_' . md5($photoId . '|' . $videoId);
        $cached   = list_cache_get($cacheKey);
        if ($cached) {
            $images = $cached['images']; $videos = $cached['videos'];
        } else {
            $lists = drive_list_deep_multi([$photoId, $videoId]);
            foreach ($lists[$photoId] ?? [] as $f) {
                if (strpos($f['mimeType'] ?? '', 'image/') === 0) $images[] = $f['id'];
            }
            $vidRows = [];
            foreach ($lists[$videoId] ?? [] as $f) {
                if (strpos($f['mimeType'] ?? '', 'video/') === 0) {
                    $vidRows[] = ['id' => $f['id'], 'name' => (string)($f['name'] ?? '')];
                }
            }
            /* Highlight first. The one-minute highlight is cut at full quality
             * while the long speech preview is deliberately small, so whichever
             * opens first is what the speaker judges the whole thing by — and
             * Drive's own order put the long one first. */
            usort($vidRows, function ($a, $b) {
                $ah = stripos($a['name'], 'highlight') !== false ? 0 : 1;
                $bh = stripos($b['name'], 'highlight') !== false ? 0 : 1;
                return $ah === $bh ? strcasecmp($a['name'], $b['name']) : $ah - $bh;
            });
            $videos = array_column($vidRows, 'id');
            list_cache_put($cacheKey, ['images'=>$images,'videos'=>$videos]);
        }
    } elseif ($folderId) {
        /* Legacy caller path. Only folders that belong to a mapped speaker are
         * allowed: otherwise this is an open "list any Drive folder you can
         * name" service, which is a strange thing for a payment proxy to be. */
        $known = array_merge(array_values(speaker_folders()), array_values(speaker_video_folders()));
        if (!in_array($folderId, $known, true)) {
            echo json_encode(['ok'=>false,'error'=>'Unknown folder']); exit;
        }
        foreach (drive_list($folderId) as $f) {
            $mime = $f['mimeType'] ?? '';
            if (strpos($mime, 'image/') === 0)      $images[] = $f['id'];
            elseif (strpos($mime, 'video/') === 0)  $videos[] = $f['id'];
        }
    } else {
        echo json_encode(['ok'=>false,'error'=>'No speaker or folderId']); exit;
    }

    /* Per-video duration + how much of it the preview plays, so the sidebar can
     * show a real "2:14" badge instead of a bare icon. */
    echo json_encode(['ok'=>true,'images'=>$images,'videos'=>$videos,
                      'videoInfo'=>video_preview_info($videos)]);
    exit;
}

/* ══ 5. Get Image Thumbnail ══ */
if ($action === 'getThumb') {
    require_af_referer_or_die();
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId) { http_response_code(400); exit; }
    $small = ($_GET['size'] ?? '') === 'small';

    /* Disk cache — same file+size is requested over and over (sidebar
     * rebuilds, repeat gallery visits, multiple buyers of the same speaker).
     * Serving from disk skips Google Drive entirely and is what actually
     * fixes the "images take forever" slowness.
     * "_wm2" cache-version suffix: bumping this instantly invalidates every
     * previously-cached file without needing a manual clearThumbCache call —
     * used here so the switch to server-burned watermarks takes effect for
     * every image on first request, not just new ones. */
    $cacheDir = __DIR__ . '/thumb-cache/';
    ensure_private_dir_($cacheDir);
    $cacheKey  = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileId) . ($small ? '_s' : '_l') . '_wm2';
    $cacheBin  = $cacheDir . $cacheKey . '.bin';
    $cacheType = $cacheDir . $cacheKey . '.ctype';

    if (is_file($cacheBin) && is_file($cacheType)) {
        header('Content-Type: ' . trim((string)@file_get_contents($cacheType)));
        header('Cache-Control: public, max-age=604800, immutable');
        header('Access-Control-Allow-Origin: *');
        readfile($cacheBin);
        exit;
    }

    /* Small (sidebar/grid thumbnails) only needs a small size — one fast
     * request instead of cascading through every size. Large (main preview
     * image) still tries a smaller fallback if the biggest size is slow/down. */
    $sizes = $small ? ['w400', 'w200'] : ['w1200', 'w800'];
    $imgData = null; $ctype = 'image/jpeg';

    /* Video ids: prefer a frame from a few seconds in (see video_poster_frame)
     * so each clip gets a distinguishable tile instead of the shared title
     * slate Drive returns. Silently falls through to Drive when ffmpeg is not
     * available on this server. */
    if (($_GET['kind'] ?? '') === 'video') {
        $frame = video_poster_frame($fileId, $small ? 400 : 1200);
        if ($frame) { $imgData = $frame; $ctype = 'image/jpeg'; }
    }
    foreach (($imgData ? [] : $sizes) as $sz) {
        $url = "https://drive.google.com/thumbnail?id={$fileId}&sz={$sz}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data && $code === 200 && strpos($data, '<!DOCTYPE') === false && strlen($data) > 200) {
            $imgData = $data;
            $ctype   = $ct ?: 'image/jpeg';
            break;
        }
    }

    /* Fallback: Drive API media (works for files the API key can read).
     * NEVER for a video: alt=media on a video pulls the ENTIRE file into memory
     * to try and fail to read it as an image — the single worst thing this
     * endpoint can do to a request. Video ids get their poster frame from the
     * drive.google.com/thumbnail attempts above or nothing at all (the sidebar
     * falls back to its own play-icon tile). Callers that already know pass
     * &kind=video so we don't even spend a metadata call to find out. */
    $isVideo = (($_GET['kind'] ?? '') === 'video');
    if (!$imgData && !$isVideo) {
        $m = drive_file_meta($fileId);
        if (strpos((string)($m['mimeType'] ?? ''), 'video/') === 0) $isVideo = true;
    }
    if (!$imgData && !$isVideo) {
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&key=" . DRIVE_API_KEY;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data && $code === 200 && strpos($ct, 'image/') === 0) {
            $imgData = $data; $ctype = $ct;
        }
    }

    if (!$imgData) {
        /* Drive has no poster frame for this file (still processing, or it is a
         * format Drive won't thumbnail). Say so quickly — the page has its own
         * placeholder — instead of leaving the request hanging. */
        http_response_code($isVideo ? 404 : 502);
        echo json_encode(['error'=>$isVideo ? 'No poster frame yet' : 'Could not fetch image']);
        exit;
    }

    /* SECURITY: burn the watermark into the actual pixels, server-side, before
     * this ever leaves the server. The old approach drew the watermark onto a
     * <canvas> in the browser — that only changed what rendered on screen, the
     * network response underneath was the clean, full-resolution original, so
     * anyone opening DevTools (or just calling this URL directly with a known
     * fileId) could grab the unwatermarked file straight from the network/
     * cache. Watermarking here means the bytes sent over the wire are never
     * clean, however they're fetched. */
    $watermarked = watermark_image_gd($imgData);
    if ($watermarked) { $imgData = $watermarked; $ctype = 'image/jpeg'; }

    /* Best-effort cache write — a failure here must never break the response. */
    @file_put_contents($cacheBin, $imgData);
    @file_put_contents($cacheType, $ctype);

    header('Content-Type: ' . $ctype);
    header('Cache-Control: public, max-age=604800, immutable');
    header('Access-Control-Allow-Origin: *');
    echo $imgData;
    exit;
}

/* ══ 6. Stream Video (preview clip — valid, small, seekable) ══
 * Serves a short preview clip built from the front of the speaker's video (see
 * video_clip_path). The clip is a complete MP4, so the browser plays it like
 * any other file: no truncated-container errors, seeking works, and it is a few
 * MB instead of 145 — which is what fixes both "could not load video" and
 * "videos load slow". It is cached on disk, so only the very first viewer of a
 * clip waits for it to be cut.
 * If ffmpeg is not available the original file is served instead, still with
 * correct range handling, and the opening blocks are cached locally. */
if ($action === 'streamVideo') {
    require_af_referer_or_die();
    $fileId = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['fileId'] ?? '');
    if (!$fileId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No fileId']); exit; }

    /* ── The good path: a real preview clip ── */
    $clip = video_clip_path($fileId);
    if ($clip) { video_serve_local_file($clip, 'video/mp4'); exit; }

    /* ── Fallback: the original, range-correct ── */
    $meta = drive_file_meta($fileId);
    $mime = (string)($meta['mimeType'] ?? '');
    if (strpos($mime, 'video/') !== 0) $mime = 'video/mp4';
    $size = (int)($meta['size'] ?? 0);

    $reqStart = 0; $reqEnd = null;
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $rm)) {
        if ($rm[1] !== '') $reqStart = (int)$rm[1];
        if ($rm[2] !== '') $reqEnd   = (int)$rm[2];
    }
    if ($reqStart < 0) $reqStart = 0;

    /* No metadata at all (file not shared publicly, bad id, Drive down): stream
     * what we can and let the player decide. */
    if ($size <= 0) {
        $end = ($reqEnd !== null) ? $reqEnd : ($reqStart + VIDEO_PREVIEW_BLOCK_BYTES - 1);
        video_stream_passthrough($fileId, $mime, $reqStart, $end, $end + 1);
        exit;
    }
    if ($reqStart >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }

    $block = VIDEO_PREVIEW_BLOCK_BYTES;
    if ($reqStart < VIDEO_PREVIEW_HEAD_BYTES) {
        /* Opening stretch — the part everyone watches. Serve it out of the local
         * block cache so repeat views never touch Drive. */
        $idx        = intdiv($reqStart, $block);
        $blockStart = $idx * $block;
        $blockEnd   = min($blockStart + $block, $size) - 1;
        $data = video_block($fileId, $idx, $blockStart, $blockEnd);
        if ($data !== null) {
            $blockEnd = $blockStart + strlen($data) - 1;
            $end = ($reqEnd !== null) ? min($reqEnd, $blockEnd) : $blockEnd;
            $len = $end - $reqStart + 1;
            if ($len > 0) {
                while (ob_get_level() > 0) ob_end_clean();
                http_response_code(206);
                header('Content-Type: ' . $mime);
                header('Accept-Ranges: bytes');
                header('Content-Length: ' . $len);
                header('Content-Range: bytes ' . $reqStart . '-' . $end . '/' . $size);
                header('Cache-Control: private, max-age=86400');
                echo substr($data, $reqStart - $blockStart, $len);
                exit;
            }
        }
    }

    /* Past the cached opening, or the cache could not be written: straight
     * through from Drive. */
    $end = ($reqEnd !== null) ? min($reqEnd, $size - 1) : ($size - 1);
    video_stream_passthrough($fileId, $mime, $reqStart, $end, $size);
    exit;
}

/* ══ 6b. Video diagnostics (ADMIN) ══
 * stripe-proxy.php?action=videoDiag&key=YOUR_ADMIN_KEY[&fileId=...]
 * Says in one place whether previews can be built on this server: is ffmpeg
 * reachable, is the cache writable, and what state a given video is in. */
if ($action === 'videoDiag' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    $dir = video_cache_dir();
    /* Why can't clips be built? Answer it here rather than leaving someone to
     * guess from a bare "false" — on a panel-managed host it is almost always
     * proc_open sitting in disable_functions, or no binary anywhere PHP may
     * reach. */
    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    $spawner  = video_spawner();
    $bin      = video_ffmpeg_bin();

    $candidates = [];
    foreach (video_ffmpeg_candidates() as $cand) {
        $candidates[$cand] = (strpos($cand, '/') === 0) ? (is_file($cand) ? 'exists' : 'not found')
                                                        : 'looked up on PATH';
    }

    $why = null;
    if (!$bin) {
        if (VIDEO_FFMPEG === 'off') $why = "VIDEO_FFMPEG is set to 'off' in stripe-proxy.php";
        elseif (!$spawner)          $why = 'PHP cannot start any process: proc_open, exec and shell_exec are '
                                         . 'all in disable_functions. Remove ONE of them (proc_open is the '
                                         . 'safest) in the panel PHP settings, then reload PHP.';
        else                        $why = 'No ffmpeg binary found. Put a static build at '
                                         . __DIR__ . '/bin/ffmpeg (chmod +x), or set VIDEO_FFMPEG to its path.';
    }

    $out = [
        'ok'              => true,
        'version'         => AIFOD_PROXY_VERSION,
        'ffmpeg'          => $bin ?: false,
        'mode'            => $bin
            ? 'preview clip (' . (VIDEO_CLIP_SECONDS > 0 ? VIDEO_CLIP_SECONDS . ' s' : 'full length')
              . ', ' . VIDEO_CLIP_HEIGHT . 'p, watermarked)'
            : 'full file — previews play, but unclipped and unwatermarked',
        'whyNoClips'      => $why,
        'canStartProcesses' => $spawner ?: false,
        'disabledFunctions' => array_values(array_intersect($disabled,
                                 ['proc_open', 'exec', 'shell_exec', 'passthru', 'popen', 'system'])),
        'openBasedir'     => ini_get('open_basedir') ?: false,
        'ffmpegCandidates'=> $candidates,
        'cacheDir'        => $dir,
        'cacheWritable'   => is_dir($dir) && is_writable($dir),
        'clipSeconds'     => VIDEO_CLIP_SECONDS,
        'cachedClips'     => count(glob($dir . '*_clip.mp4') ?: []),
        'cachedBlocks'    => count(glob($dir . '*_b*.bin') ?: []),
    ];
    $fileId = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['fileId'] ?? '');
    if ($fileId) {
        $m = drive_file_meta($fileId);
        $out['file'] = [
            'id'         => $fileId,
            'name'       => $m['name'] ?? null,
            'size'       => (int)($m['size'] ?? 0),
            'durationMs' => (int)($m['durationMs'] ?? 0),
            'clipCached' => is_file($dir . $fileId . '_clip.mp4')
                              ? (int)filesize($dir . $fileId . '_clip.mp4') : false,
            'lastFailure'=> is_file($dir . $fileId . '.noclip')
                              ? trim((string)@file_get_contents($dir . $fileId . '.noclip')) : false,
            'sourceCached'=> is_file($dir . $fileId . '_src.bin')
                              ? (int)filesize($dir . $fileId . '_src.bin') : false,
        ];
    }
    echo json_encode($out);
    exit;
}

/* ══ 7. Get AIFOD Logo ══ */
if ($action === 'getLogo') {
    $url = 'https://af.net/wp-content/uploads/cropped-aifod-logo-version-2-1320x528.png';
    $ch  = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false]);
    $data = curl_exec($ch);
    $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($data) {
        header('Content-Type: ' . ($ct ?: 'image/png'));
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');
        echo $data;
    } else { http_response_code(404); }
    exit;
}

/* ══ 8. View Orders ══ */
if ($action === 'viewOrders' && ($_GET['key']??'') === ADMIN_KEY) {
    header('Content-Type: text/html');
    if (!file_exists(ORDERS_FILE)) { echo '<p>No orders yet.</p>'; exit; }
    echo '<table border="1" cellpadding="6" style="border-collapse:collapse;font-family:monospace;font-size:13px;">';
    $fp = fopen(ORDERS_FILE,'r'); $first=true;
    while(($row=fgetcsv($fp))!==false){
        echo '<tr'.($first?' style="background:#0d1b3a;color:#fff;font-weight:bold;"':'').'>';
        foreach($row as $c) echo '<td>'.htmlspecialchars($c).'</td>';
        echo '</tr>'; $first=false;
    }
    fclose($fp); echo '</table>';
    exit;
}

/* ══ 8a. Clear the thumbnail + folder-listing cache (ADMIN) ══
 * Visit: stripe-proxy.php?action=clearThumbCache&key=YOUR_ADMIN_KEY
 * Needed if a photo/video is replaced under the same file ID, or if new
 * media was just uploaded and you don't want to wait out the 10-min
 * folder-listing cache. */
if ($action === 'clearThumbCache' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    $n = 0;
    foreach ([__DIR__ . '/thumb-cache/', __DIR__ . '/list-cache/', __DIR__ . '/video-cache/'] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . '*') as $f) { @unlink($f); $n++; }
        }
    }
    echo json_encode(['ok'=>true,'cleared'=>$n]);
    exit;
}

/* ══ 8c. Pre-build the video previews (ADMIN) ══
 * Visit: stripe-proxy.php?action=prewarmVideos&key=YOUR_ADMIN_KEY
 *        &speaker=Harsh%20Vardhan     (one speaker; omit for every speaker)
 *        &max=10                      (speakers per run, default 10)
 * Cuts the preview clip for every mapped video ahead of time, so the first
 * buyer to open a preview gets the same instant playback as the tenth. Safe to
 * re-run — videos that already have a clip are skipped. Run it after Siphiwe
 * adds new videos, a few speakers at a time if your host has a short execution
 * limit. Returns which videos are ready and which could not be built. */
if ($action === 'prewarmVideos' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    @set_time_limit(0);
    $speaker = trim($_GET['speaker'] ?? '');
    $max     = max(1, min(200, (int)($_GET['max'] ?? 10)));
    $map     = speaker_video_folders();
    $targets = ($speaker !== '')
        ? (isset($map[$speaker]) ? [$speaker => $map[$speaker]] : [])
        : $map;

    if (!video_ffmpeg_bin()) {
        /* Nothing to pre-build without ffmpeg — say so once instead of
         * reporting a failure per video. */
        echo json_encode(['ok' => false, 'ffmpeg' => false,
            'error' => 'No preview clips can be built on this server yet — run '
                     . 'action=videoDiag for the reason. Previews still play in the meantime.']);
        exit;
    }

    $detail = []; $speakersDone = 0; $built = 0; $failed = 0;

    foreach ($targets as $spk => $folder) {
        if ($speakersDone >= $max) break;
        $ids = [];
        foreach (drive_list_deep($folder, 200) as $f) {
            if (strpos($f['mimeType'] ?? '', 'video/') === 0) $ids[] = $f['id'];
        }
        $ready = 0; $bad = 0; $errors = [];
        foreach ($ids as $id) {
            $why = null;
            if (video_clip_path($id, $why, true)) { $ready++; $built++; }
            else { $bad++; $failed++; $errors[$id] = $why ?: 'unknown'; }
        }
        $detail[$spk] = ['videos' => count($ids), 'ready' => $ready, 'failed' => $bad];
        if ($errors) $detail[$spk]['errors'] = $errors;
        $speakersDone++;
    }

    echo json_encode(['ok' => true, 'speakers' => $speakersDone,
                      'clipsReady' => $built, 'clipsFailed' => $failed,
                      'ffmpeg' => video_ffmpeg_bin() ?: false, 'detail' => $detail]);
    exit;
}

/* ══ 8b. Reset ALL previous data (ADMIN — testing only) ══
 * Visit: stripe-proxy.php?action=resetOrders&key=YOUR_ADMIN_KEY&confirm=yes
 * Clears the orders CSV, every gallery token, the fail log, the Google Sheet
 * rows, and (reversibly — moved to Drive trash, not deleted) every test
 * delivery folder. After this nothing is sold-out and the delivery folder is
 * empty. Needs the latest Apps Script deployed for the sheet/deliveries part. */
if ($action === 'resetOrders' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    if (($_GET['confirm'] ?? '') !== 'yes') {
        echo json_encode(['ok'=>false,'error'=>'Add &confirm=yes to the URL to proceed']); exit;
    }
    $out = ['orders'=>false, 'tokens'=>0, 'sheet'=>false, 'deliveries'=>0];

    if (file_exists(ORDERS_FILE)) { @unlink(ORDERS_FILE); $out['orders'] = true; }
    if (file_exists(FAIL_LOG))    { @unlink(FAIL_LOG); }

    $tokenDir = __DIR__ . '/gallery-tokens/';
    if (is_dir($tokenDir)) {
        foreach (glob($tokenDir . '*.json') as $tf) { @unlink($tf); $out['tokens']++; }
    }

    /* Clear the Google Sheet rows. */
    $r = apps_script_call(['action'=>'clearOrders', 'key'=>ADMIN_KEY]);
    $out['sheet'] = !empty($r['ok']);

    /* Trash every test delivery folder (reversible — Drive trash, not gone). */
    $r2 = apps_script_call(['action'=>'clearDeliveries', 'key'=>ADMIN_KEY]);
    $out['deliveries'] = $r2['cleared'] ?? 0;

    echo json_encode(['ok'=>true, 'cleared'=>$out]);
    exit;
}

/* ══ 8d. Reconcile paid orders against Stripe (ADMIN) ══
 * Visit: stripe-proxy.php?action=reconcileOrders&key=YOUR_ADMIN_KEY[&days=30]
 *        add &apply=yes to actually record what it finds
 *
 * Asks Stripe for its own list of succeeded media-package payments (other AIFOD
 * payments on the same account are ignored) and reports any that this server
 * never recorded. That is the gap the webhook exists to close: when a
 * buyer's tab dies in the seconds between Stripe confirming payment and the
 * browser calling recordOrder, Stripe has the money and nothing here knows
 * about it — no CSV row, no Sheet row, no gallery email. Until
 * STRIPE_WEBHOOK_SECRET is configured, this is the net; afterwards it is still
 * the way to audit that nothing slipped through.
 *
 * Without &apply=yes it only reports (safe to run any time). With it, each
 * missing order is recorded exactly as a normal purchase would be — CSV row,
 * Sheet row, gallery token, and the gallery link emailed to the buyer. All of
 * that is idempotent, so an order already handled is left alone. */
if ($action === 'reconcileOrders' && ($_GET['key'] ?? '') === ADMIN_KEY) {
    @set_time_limit(0);
    $apply = (($_GET['apply'] ?? '') === 'yes');
    $days  = max(1, min(365, (int)($_GET['days'] ?? 30)));
    $since = time() - ($days * 86400);

    $resp = stripe_call('GET', '/v1/payment_intents?limit=100&created%5Bgte%5D=' . $since);
    if (isset($resp['error'])) {
        echo json_encode(['ok'=>false,'error'=>$resp['error']['message'] ?? 'Stripe call failed']);
        exit;
    }

    $alreadyRecorded = 0; $skippedNonMedia = 0; $missing = []; $recovered = [];
    foreach ($resp['data'] ?? [] as $pi) {
        if (($pi['status'] ?? '') !== 'succeeded') continue;
        $piId = $pi['id'] ?? '';
        if (!$piId) continue;

        $meta = $pi['metadata'] ?? [];

        /* Only media-package payments. This Stripe account also takes other
         * AIFOD payments, which carry none of this metadata — recording one of
         * those as a media order would put a bogus row in the Sheet and email
         * that person a gallery link for media they never bought. Payments made
         * before the source marker existed are matched on their speaker +
         * package metadata instead. */
        $isMedia = (($meta['source'] ?? '') === 'aifod-media')
                   || (!empty($meta['speakerName']) && pkg_info($meta['package'] ?? ''));
        if (!$isMedia) { $skippedNonMedia++; continue; }

        $row  = [
            'piId'    => $piId,
            'paidAt'  => isset($pi['created']) ? date('Y-m-d H:i', (int)$pi['created']) : '',
            'amount'  => isset($pi['amount']) ? ($pi['amount'] / 100) : '',
            'speaker' => $meta['speakerName'] ?? '',
            'package' => $meta['package']     ?? '',
            'buyer'   => trim(($meta['buyerName'] ?? '') . ' <' . ($meta['buyerEmail'] ?? '') . '>'),
        ];

        if (order_already_recorded($piId)) { $alreadyRecorded++; continue; }

        $missing[] = $row;
        if ($apply) {
            record_paid_order($piId, $meta);
            $token = create_gallery_token($piId, $meta);
            $row['galleryUrl'] = $token['galleryUrl'] ?? null;
            $recovered[] = $row;
        }
    }

    echo json_encode([
        'ok'              => true,
        'mode'            => $apply ? 'applied' : 'report only — add &apply=yes to record these',
        'daysChecked'     => $days,
        'alreadyRecorded' => $alreadyRecorded,
        'skippedNotMediaOrders' => $skippedNonMedia,
        'missingCount'    => count($missing),
        'missing'         => $missing,
        'recovered'       => $recovered,
        'webhookConfigured' => !(!STRIPE_WEBHOOK_SECRET || strpos(STRIPE_WEBHOOK_SECRET, 'whsec_REPLACE_WITH') === 0),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ══ 9. Generate secure gallery token ══ */
if ($action === 'generateGalleryToken') {
    $piId        = $_GET['piId']        ?? '';
    $speakerName = $_GET['speakerName'] ?? '';
    $pkg         = $_GET['pkg']         ?? '';
    if (!$piId) { echo json_encode(['ok'=>false,'error'=>'No piId']); exit; }
    $pi = stripe_call('GET', '/v1/payment_intents/' . urlencode($piId));
    if (($pi['status'] ?? '') !== 'succeeded') { echo json_encode(['ok'=>false,'error'=>'Payment not confirmed']); exit; }

    $result = create_gallery_token($piId, $pi['metadata'] ?? [], $speakerName, $pkg);
    echo json_encode(['ok'=>true,'token'=>$result['token'],'galleryUrl'=>$result['galleryUrl'],'alreadyExisted'=>$result['alreadyExisted']]);
    exit;
}

/* ══ 9b. Stripe webhook — the safety net for lost orders ══
 * SETUP (one-time): Stripe Dashboard ▸ Developers ▸ Webhooks ▸ Add endpoint
 *   URL: https://af.net/stripe-proxy.php?action=stripeWebhook
 *   Event: payment_intent.succeeded
 * Then copy the "Signing secret" shown there into STRIPE_WEBHOOK_SECRET above.
 *
 * WHY THIS EXISTS: recordOrder + generateGalleryToken normally run from the
 * browser right after Stripe confirms payment. If the buyer's tab crashes,
 * they close it, or their connection drops in that exact window, Stripe has
 * already taken the money but our system never finds out — no Sheet row, no
 * delivery, nothing. This listens for Stripe's own payment_intent.succeeded
 * event directly, so the order gets recorded and the gallery link gets sent
 * regardless of what the buyer's browser did. record_paid_order() and
 * create_gallery_token() are both idempotent (check for the piId first), so
 * it's safe for this to run even when the normal client-side path already
 * completed — it just becomes a no-op. */
if ($action === 'stripeWebhook') {
    $payload   = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    /* An unconfigured signing secret makes every event fail verification, which
     * shows up in Stripe as a generic signature error and looks like a bug in
     * the signing code. Name the real cause, and leave a line in the fail log
     * so it is visible from the server too. */
    if (!STRIPE_WEBHOOK_SECRET || strpos(STRIPE_WEBHOOK_SECRET, 'whsec_REPLACE_WITH') === 0) {
        @file_put_contents(FAIL_LOG,
            date('c') . " stripeWebhook rejected: STRIPE_WEBHOOK_SECRET is not set in stripe-proxy.php\n",
            FILE_APPEND);
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'STRIPE_WEBHOOK_SECRET is not configured on the server']);
        exit;
    }

    if (!verify_stripe_webhook_signature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Signature verification failed']);
        exit;
    }

    $event = json_decode($payload, true);
    if (($event['type'] ?? '') === 'payment_intent.succeeded') {
        $pi   = $event['data']['object'] ?? [];
        $piId = $pi['id'] ?? '';
        $meta = $pi['metadata'] ?? [];
        if ($piId) {
            record_paid_order($piId, $meta);
            create_gallery_token($piId, $meta);
        }
    }

    /* Stripe only cares about the HTTP status — always 200 once verified,
     * even for event types we don't act on, so Stripe doesn't retry forever. */
    http_response_code(200);
    echo json_encode(['ok'=>true,'received'=>true]);
    exit;
}

/* ══ 10. Verify gallery token ══ */
if ($action === 'verifyToken') {
    $token = $_GET['token'] ?? '';
    if (!$token || strlen($token) !== 48) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); exit; }
    $f = __DIR__ . '/gallery-tokens/' . $token . '.json';
    if (!file_exists($f)) { echo json_encode(['ok'=>false,'error'=>'Token not found']); exit; }
    echo json_encode(['ok'=>true,'data'=>json_decode(file_get_contents($f),true)]);
    exit;
}

/* ══ 11. Get gallery files for token ══ */
if ($action === 'getGalleryFiles') {
    $token = $_GET['token'] ?? '';
    if (!$token) { echo json_encode(['ok'=>false,'error'=>'No token']); exit; }
    $f = __DIR__ . '/gallery-tokens/' . $token . '.json';
    if (!file_exists($f)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); exit; }
    $data = json_decode(file_get_contents($f), true);
    $spk  = $data['speakerName'] ?? '';
    $photoId = speaker_folders()[$spk] ?? '';
    $videoId = speaker_video_folders()[$spk] ?? '';
    if (!$photoId && !$videoId) { echo json_encode(['ok'=>false,'error'=>'No folder for speaker']); exit; }

    $images = []; $videos = [];
    $cacheKey = 'gf_' . md5($photoId . '|' . $videoId);
    $cached   = list_cache_get($cacheKey);
    if ($cached) {
        $images = $cached['images']; $videos = $cached['videos'];
    } else {
        $lists = drive_list_deep_multi([$photoId, $videoId], 200);
        foreach ($lists[$photoId] ?? [] as $fi) {
            if (strpos($fi['mimeType'] ?? '', 'image/') === 0) $images[] = ['id'=>$fi['id'],'name'=>$fi['name']];
        }
        foreach ($lists[$videoId] ?? [] as $fi) {
            if (strpos($fi['mimeType'] ?? '', 'video/') === 0) $videos[] = ['id'=>$fi['id'],'name'=>$fi['name']];
        }
        list_cache_put($cacheKey, ['images'=>$images,'videos'=>$videos]);
    }
    $info  = pkg_info($data['pkg'] ?? '');
    $limit = $info ? $info['limit'] : null;
    $videoIds = array_map(function ($v) { return $v['id']; }, $videos);
    echo json_encode(['ok'=>true,'images'=>$images,'videos'=>$videos,'pkg'=>$data['pkg']??'',
        'videoInfo'=>video_preview_info($videoIds),
        'limit'=>$limit,'speaker'=>$spk,'selection'=>$data['selection']??[],'confirmed'=>isset($data['selectionConfirmed'])]);
    exit;
}

/* ══ 12. Save photo selection ══ */
if ($action === 'saveSelection') {
    $token     = $_GET['token']     ?? '';
    $selection = $_GET['selection'] ?? '';
    if (!$token) { echo json_encode(['ok'=>false,'error'=>'No token']); exit; }
    $f = __DIR__ . '/gallery-tokens/' . $token . '.json';
    if (!file_exists($f)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); exit; }
    $data = json_decode(file_get_contents($f), true);

    /* Already confirmed → lock it, don't re-run delivery. */
    if (isset($data['selectionConfirmed'])) {
        echo json_encode(['ok'=>true,'saved'=>count($data['selection']??[]),'locked'=>true]); exit;
    }

    /* Verify the payment first. */
    $pi = stripe_call('GET', '/v1/payment_intents/' . urlencode($data['piId']));
    if (($pi['status'] ?? '') !== 'succeeded') {
        echo json_encode(['ok'=>false,'error'=>'Payment not verified']); exit;
    }

    /* Resolve the limit from the ORDER'S package, never from the request. */
    $info  = pkg_info($data['pkg'] ?? '');
    $limit = $info ? $info['limit'] : null;
    $ids   = array_values(array_filter(array_map('trim', explode(',', $selection))));

    if ($limit !== null) {
        /* Photo-selection packages (25 / 50): validate the chosen photos. */
        if (count($ids) > $limit) {
            echo json_encode(['ok'=>false,'error'=>'Exceeds limit of '.$limit]); exit;
        }
        /* SECURITY (spec check #3): every submitted photo id must belong to this
         * speaker's own master folder — otherwise a tampered browser could turn a
         * €299 order into the full collection or another speaker's photos. */
        $folders  = speaker_folders();
        $folderId = $folders[$data['speakerName'] ?? ''] ?? '';
        if (!$folderId) { echo json_encode(['ok'=>false,'error'=>'No folder for speaker']); exit; }
        $allowed = [];
        foreach (drive_list_deep($folderId, 300) as $fi) {
            if (strpos($fi['mimeType'] ?? '', 'image/') === 0) $allowed[$fi['id']] = true;
        }
        foreach ($ids as $id) {
            if (!isset($allowed[$id])) {
                echo json_encode(['ok'=>false,'error'=>'Invalid photo in selection']); exit;
            }
        }
    } else {
        /* All Photos / Video / Bundle: nothing to select — confirming delivers
         * everything the package entitles. Ignore any submitted ids. */
        $ids = [];
    }

    $data['selection']          = $ids;
    $data['selectionConfirmed'] = time();
    file_put_contents($f, json_encode($data));

    /* Kick off delivery for this selection (idempotent on the Apps Script side). */
    if (empty($data['deliveryTriggered'])) {
        trigger_delivery($data, $ids);
        $data['deliveryTriggered'] = time();
        file_put_contents($f, json_encode($data));
    }

    echo json_encode(['ok'=>true,'saved'=>count($ids),'limit'=>$limit]);
    exit;
}


echo json_encode(['ok'=>false,'error'=>'Unknown action: '.$action]);

/* ═══════════════════════════ HELPERS ═══════════════════════════ */

/* ── One-package-per-speaker check: any 'Paid' row for this speaker in the
 *    orders CSV? Used by createIntent to block a duplicate purchase. ── */
function speaker_already_sold($speakerName) {
    if ($speakerName === '' || !file_exists(ORDERS_FILE)) return false;
    $fp = fopen(ORDERS_FILE, 'r');
    fgetcsv($fp); // header
    while (($row = fgetcsv($fp)) !== false) {
        if (isset($row[3]) && trim($row[3]) === $speakerName && ($row[4] ?? '') === 'Paid') {
            fclose($fp);
            return true;
        }
    }
    fclose($fp);
    return false;
}

/* ── Stripe ── */
function stripe_call($method, $path, $data=[]) {
    $ch = curl_init('https://api.stripe.com' . $path);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_USERPWD=>STRIPE_SECRET.':',
        CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,
    ]);
    if ($method==='POST') {
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
    }
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp?:'{}',true)??[];
}

/* ── Burn a tiled "AIFOD PREVIEW" watermark into an image's actual pixels
 * (server-side, via GD) and return the re-encoded JPEG bytes, or null if GD
 * isn't available or the image can't be decoded — callers must keep serving
 * the original in that case rather than break the response. Doing this here
 * instead of in client-side JS means the network response itself is never
 * a clean copy, regardless of how it's fetched. ── */
/* ── Fetch the real AIFOD logo, downscale it to a small watermark tile with
 * reduced opacity baked into its alpha channel, and cache the PROCESSED tile
 * to disk (not just the raw logo) so the resize + per-pixel alpha pass only
 * ever runs once, not on every getThumb request. Returns a GD image handle
 * (caller must imagedestroy it), or null if the logo can't be fetched/decoded
 * — callers must fall back to the text watermark in that case. ── */
function get_watermark_logo_gd() {
    $dir = ensure_private_dir_(__DIR__ . '/thumb-cache/', 0775);
    $processedFile = $dir . '_watermark_logo_tile.png';

    if (is_file($processedFile)) {
        $cached = @imagecreatefrompng($processedFile);
        if ($cached) return $cached;
    }

    $ch = curl_init('https://af.net/wp-content/uploads/cropped-aifod-logo-version-2-1320x528.png');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>false]);
    $data = curl_exec($ch);
    curl_close($ch);
    if (!$data) return null;

    $logo = @imagecreatefromstring($data);
    if (!$logo) return null;

    $srcW = imagesx($logo); $srcH = imagesy($logo);
    $tileW = 220;
    $tileH = max(1, intval($tileW * $srcH / $srcW));
    $tile = imagecreatetruecolor($tileW, $tileH);
    imagealphablending($tile, false);
    imagesavealpha($tile, true);
    $transparent = imagecolorallocatealpha($tile, 0, 0, 0, 127);
    imagefill($tile, 0, 0, $transparent);
    imagecopyresampled($tile, $logo, 0, 0, 0, 0, $tileW, $tileH, $srcW, $srcH);
    imagedestroy($logo);

    /* Bake ~35% opacity into the tile's own alpha channel so a plain
     * imagecopy (with alpha blending on) composites it correctly later. */
    imagealphablending($tile, false);
    for ($y = 0; $y < $tileH; $y++) {
        for ($x = 0; $x < $tileW; $x++) {
            $rgba = imagecolorat($tile, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F; /* 0 = opaque .. 127 = fully transparent */
            $newAlpha = 127 - intval((127 - $alpha) * 0.35);
            $r = ($rgba >> 16) & 0xFF; $g = ($rgba >> 8) & 0xFF; $b = $rgba & 0xFF;
            imagesetpixel($tile, $x, $y, imagecolorallocatealpha($tile, $r, $g, $b, $newAlpha));
        }
    }

    imagesavealpha($tile, true);
    @imagepng($tile, $processedFile);
    return $tile;
}

/* ── Burn a tiled watermark into an image's actual pixels (server-side, via
 * GD) and return the re-encoded JPEG bytes, or null if GD isn't available or
 * the image can't be decoded — callers must keep serving the original in
 * that case rather than break the response. Uses the real AIFOD logo when it
 * can be fetched, falling back to repeated text otherwise (mirrors the old
 * client-side apDrawWM logo-or-text behavior). Doing this here instead of in
 * client-side JS means the network response itself is never a clean copy,
 * regardless of how it's fetched. ── */
function watermark_image_gd($imgData) {
    if (!function_exists('imagecreatefromstring')) return null;
    $img = @imagecreatefromstring($imgData);
    if (!$img) return null;

    imagesavealpha($img, true);
    imagealphablending($img, true);

    $w = imagesx($img);
    $h = imagesy($img);

    $logo = get_watermark_logo_gd();
    if ($logo) {
        $lw = imagesx($logo); $lh = imagesy($logo);
        $stepX = $lw + 50; $stepY = $lh + 60;
        $row = 0;
        for ($y = -$stepY; $y < $h + $stepY; $y += $stepY) {
            $offset = ($row % 2 === 0) ? 0 : intval($stepX / 2);
            for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
                imagecopy($img, $logo, $x + $offset, $y, 0, 0, $lw, $lh);
            }
            $row++;
        }
        imagedestroy($logo);
    } else {
        $text = 'AIFOD PREVIEW';
        $font = 5; /* largest built-in GD font — no external .ttf dependency */
        $textW = imagefontwidth($font) * strlen($text);
        $textH = imagefontheight($font);
        $color = imagecolorallocatealpha($img, 255, 255, 255, 55); /* semi-transparent white */
        $stepX = $textW + 60;
        $stepY = $textH + 70;
        $row = 0;
        for ($y = -$stepY; $y < $h + $stepY; $y += $stepY) {
            $offset = ($row % 2 === 0) ? 0 : intval($stepX / 2);
            for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
                imagestring($img, $font, $x + $offset, $y, $text, $color);
            }
            $row++;
        }
    }

    ob_start();
    imagejpeg($img, null, 85);
    $out = ob_get_clean();
    imagedestroy($img);
    return $out ?: null;
}

/* ── Drive: list a folder's files (id, name, mimeType) ── */
function drive_list($folderId, $pageSize = 100) {
    $q   = urlencode("'" . $folderId . "' in parents and trashed=false");
    $url = "https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id,name,mimeType)&pageSize={$pageSize}&key=" . DRIVE_API_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp, true)['files'] ?? [];
}

/* ── Drive: list a folder's files, plus one level into any subfolders.
 * Some speaker folders hold their photos inside one extra numbered
 * subfolder (e.g. a "61" folder) instead of directly — drive_list() alone
 * would see only that subfolder and report zero images/videos. ── */
function drive_list_deep($folderId, $pageSize = 200) {
    $out = [];
    foreach (drive_list($folderId, $pageSize) as $it) {
        if (($it['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
            foreach (drive_list($it['id'], $pageSize) as $sub) $out[] = $sub;
        } else {
            $out[] = $it;
        }
    }
    return $out;
}

/* ── Drive: list several folders CONCURRENTLY (curl_multi) instead of one
 * request after another. Preview/gallery need both a speaker's photo folder
 * AND their separate video folder — fetching them one at a time doubled the
 * wait before anything showed up. Returns [folderId => files[]]. ── */
function drive_list_multi($folderIds, $pageSize = 200) {
    $folderIds = array_values(array_unique(array_filter($folderIds)));
    $out = [];
    if (!$folderIds) return $out;
    $mh = curl_multi_init();
    $handles = [];
    foreach ($folderIds as $fid) {
        $q   = urlencode("'" . $fid . "' in parents and trashed=false");
        $url = "https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id,name,mimeType)&pageSize={$pageSize}&key=" . DRIVE_API_KEY;
        $ch  = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
        curl_multi_add_handle($mh, $ch);
        $handles[$fid] = $ch;
    }
    $running = null;
    do { curl_multi_exec($mh, $running); if ($running > 0) curl_multi_select($mh); } while ($running > 0);
    foreach ($handles as $fid => $ch) {
        $resp = curl_multi_getcontent($ch);
        $out[$fid] = json_decode($resp, true)['files'] ?? [];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/* ── Same one-level subfolder recursion as drive_list_deep(), but for several
 * top-level folders at once, batched into at most 2 concurrent round trips
 * total (one for the top-level folders, one for any subfolders found)
 * instead of 2 sequential round trips PER folder. ── */
function drive_list_deep_multi($folderIds, $pageSize = 200) {
    $direct = drive_list_multi($folderIds, $pageSize);
    $subIds = [];
    foreach ($direct as $items) {
        foreach ($items as $it) {
            if (($it['mimeType'] ?? '') === 'application/vnd.google-apps.folder') $subIds[] = $it['id'];
        }
    }
    $deep = $subIds ? drive_list_multi($subIds, $pageSize) : [];
    $out = [];
    foreach ($folderIds as $fid) {
        $files = [];
        foreach ($direct[$fid] ?? [] as $it) {
            if (($it['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                foreach ($deep[$it['id']] ?? [] as $sub) $files[] = $sub;
            } else {
                $files[] = $it;
            }
        }
        $out[$fid] = $files;
    }
    return $out;
}

/* ═══════════════ VIDEO PREVIEW HELPERS (used by streamVideo) ═══════════════ */

function video_cache_dir() {
    return ensure_private_dir_(__DIR__ . '/video-cache/');
}

/* ── Drive file metadata (size + mime + duration + dimensions), cached on disk.
 * streamVideo needs the real byte size to answer ranges correctly, and the
 * sales-page sidebar needs durationMillis for the "2:14" badge on each video
 * thumbnail. One cheap metadata call serves both. ── */
function drive_file_meta($fileId, $ttl = 86400) {
    $fileId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$fileId);
    if (!$fileId) return [];
    $cache = video_cache_dir() . $fileId . '.meta.json';
    if (is_file($cache) && (time() - filemtime($cache)) < $ttl) {
        $d = json_decode((string)@file_get_contents($cache), true);
        if (is_array($d) && isset($d['id'])) return $d;
    }
    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}"
         . "?fields=id,name,size,mimeType,videoMediaMetadata&supportsAllDrives=true&key=" . DRIVE_API_KEY;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$resp, true);
    if (!is_array($d) || empty($d['id'])) {
        /* Drive unreachable — a stale cache entry still beats nothing. */
        if (is_file($cache)) {
            $old = json_decode((string)@file_get_contents($cache), true);
            if (is_array($old) && isset($old['id'])) return $old;
        }
        return [];
    }
    $vm  = $d['videoMediaMetadata'] ?? [];
    $out = [
        'id'         => $d['id'],
        'name'       => $d['name'] ?? '',
        'size'       => (string)($d['size'] ?? '0'),
        'mimeType'   => $d['mimeType'] ?? '',
        'durationMs' => (int)($vm['durationMillis'] ?? 0),
        'w'          => (int)($vm['width'] ?? 0),
        'h'          => (int)($vm['height'] ?? 0),
    ];
    @file_put_contents($cache, json_encode($out));
    return $out;
}

/* ── Same as drive_file_meta() for a whole list of ids, fetching every cache
 * miss CONCURRENTLY. The preview sidebar needs duration for every video of a
 * speaker at once — one round trip for all of them, not one each. ── */
function drive_file_meta_multi($fileIds, $ttl = 86400) {
    $out = []; $missing = [];
    foreach (array_unique(array_filter((array)$fileIds)) as $id) {
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$id);
        if (!$id) continue;
        $cache = video_cache_dir() . $id . '.meta.json';
        if (is_file($cache) && (time() - filemtime($cache)) < $ttl) {
            $d = json_decode((string)@file_get_contents($cache), true);
            if (is_array($d) && isset($d['id'])) { $out[$id] = $d; continue; }
        }
        $missing[] = $id;
    }
    if ($missing) {
        $mh = curl_multi_init(); $handles = [];
        foreach ($missing as $id) {
            $url = "https://www.googleapis.com/drive/v3/files/{$id}"
                 . "?fields=id,name,size,mimeType,videoMediaMetadata&supportsAllDrives=true&key=" . DRIVE_API_KEY;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = $ch;
        }
        $running = null;
        do { curl_multi_exec($mh, $running); if ($running > 0) curl_multi_select($mh); } while ($running > 0);
        foreach ($handles as $id => $ch) {
            $d = json_decode((string)curl_multi_getcontent($ch), true);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if (!is_array($d) || empty($d['id'])) continue;
            $vm = $d['videoMediaMetadata'] ?? [];
            $out[$id] = [
                'id'         => $d['id'],
                'name'       => $d['name'] ?? '',
                'size'       => (string)($d['size'] ?? '0'),
                'mimeType'   => $d['mimeType'] ?? '',
                'durationMs' => (int)($vm['durationMillis'] ?? 0),
                'w'          => (int)($vm['width'] ?? 0),
                'h'          => (int)($vm['height'] ?? 0),
            ];
            @file_put_contents(video_cache_dir() . $id . '.meta.json', json_encode($out[$id]));
        }
        curl_multi_close($mh);
    }
    return $out;
}

/* ── What the sales page / gallery needs per video: the full running time for
 * the thumbnail badge, and how much of it the preview actually plays so the
 * page can say "preview ends here" instead of looking broken. previewMs 0
 * means the preview is not length-limited on this server (no ffmpeg). ── */
function video_preview_info($fileIds) {
    $metas   = drive_file_meta_multi($fileIds);
    $canClip = (bool)video_ffmpeg_bin();
    $clipMs  = VIDEO_CLIP_SECONDS * 1000;
    $info    = [];
    foreach ($metas as $id => $m) {
        $dur = (int)($m['durationMs'] ?? 0);
        $info[$id] = [
            'name'       => $m['name'] ?? '',
            'size'       => (int)($m['size'] ?? 0),
            'durationMs' => $dur,
            'previewMs'  => $canClip ? ($dur > 0 ? min($clipMs, $dur) : $clipMs) : 0,
            'w'          => (int)($m['w'] ?? 0),
            'h'          => (int)($m['h'] ?? 0),
        ];
    }
    return $info;
}

/* ── Fetch one byte range of a Drive file. Retries once on a transport error,
 * and once more with acknowledgeAbuse for files Drive flags on the way out. ── */
function drive_range_fetch($fileId, $start, $end, &$driveSaid = null) {
    $base = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&supportsAllDrives=true&key=" . DRIVE_API_KEY;
    $attempts = [$base, $base, $base . '&acknowledgeAbuse=true'];
    foreach ($attempts as $i => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Range: bytes=' . $start . '-' . $end, 'User-Agent: AIFOD-Proxy/1.0'],
        ]);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (($code === 206 || $code === 200) && is_string($data) && $data !== '') return $data;

        /* Keep what Drive actually said. A refusal here is nearly always one of
         * a handful of specific things — the file not being shared, the abuse
         * gate, or a download quota tripping on a file this large — and each
         * needs a different answer, so the message is worth far more than
         * "could not fetch". */
        $said = '';
        if (is_string($data) && $data !== '' && strlen($data) < 4000) {
            $json = json_decode($data, true);
            $said = $json['error']['message'] ?? trim(preg_replace('/\s+/', ' ', strip_tags($data)));
            if (strlen($said) > 200) $said = substr($said, 0, 200) . '…';
        }
        $driveSaid = 'HTTP ' . $code . ($said !== '' ? ': ' . $said : '');

        /* 403 is usually the abusive-file gate — the next attempt adds
         * acknowledgeAbuse. Anything else just gets one plain retry. */
        if ($code === 404 || $code === 401) return null;
    }
    return null;
}

/* ── Keep the on-disk window cache under VIDEO_CACHE_BUDGET_BYTES, deleting the
 * least recently served windows first. ── */
function video_cache_prune() {
    $files = glob(video_cache_dir() . '*.bin');
    if (!$files) return;
    $total = 0; $rows = [];
    foreach ($files as $f) {
        $sz = (int)@filesize($f);
        $total += $sz;
        $rows[] = ['f' => $f, 'sz' => $sz, 'm' => (int)@filemtime($f)];
    }
    if ($total <= VIDEO_CACHE_BUDGET_BYTES) return;
    usort($rows, function ($a, $b) { return $a['m'] <=> $b['m']; });
    foreach ($rows as $r) {
        if ($total <= VIDEO_CACHE_BUDGET_BYTES) break;
        @unlink($r['f']);
        $total -= $r['sz'];
    }
}

/* ── Return one cached preview block, fetching it from Drive on a miss.
 * Blocks are small enough that a miss costs one short round trip (so the first
 * frame appears quickly) and cheap enough to keep, so a second viewer of the
 * same clip never touches Drive at all. Returns the raw bytes, or null if the
 * block could not be read. ── */
function video_block($fileId, $idx, $blockStart, $blockEnd) {
    $dir  = video_cache_dir();
    $bin  = $dir . $fileId . '_b' . $idx . '.bin';

    if (is_file($bin)) {
        $data = @file_get_contents($bin);
        if (is_string($data) && $data !== '') {
            @touch($bin);   /* keep it warm for the LRU prune */
            return $data;
        }
        @unlink($bin);
    }

    $data = drive_range_fetch($fileId, $blockStart, $blockEnd);
    if ($data === null || $data === '') return null;

    /* Write via a temp file so a killed request can never leave a half block
     * behind for the next reader to serve as if it were complete. */
    $tmp = $bin . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $data) !== false) {
        if (!@rename($tmp, $bin)) @unlink($tmp);
        video_cache_prune();
    }
    return $data;
}

/* ── Is there an ffmpeg we are allowed to run? Result is cached on disk so a
 * missing binary costs one probe a day, not a process per thumbnail. ── */
function video_ffmpeg_bin() {
    if (VIDEO_FFMPEG === 'off') return null;
    if (!video_spawner()) return null;

    if (VIDEO_FFMPEG !== '') return VIDEO_FFMPEG;

    /* No leading dot in the name: glob() skips dotfiles, so a hidden probe file
     * would survive clearThumbCache and keep reporting "no ffmpeg" long after
     * one had been installed. A negative result is also only trusted for a few
     * minutes — installing ffmpeg should take effect without waiting a day. */
    $probe = video_cache_dir() . 'ffmpeg-probe.txt';
    if (is_file($probe)) {
        $age = time() - filemtime($probe);
        $v   = trim((string)@file_get_contents($probe));
        if ($v !== '' && $age < 86400) return $v;
        if ($v === '' && $age < 300)   return null;
    }
    $found = '';
    /* __DIR__/bin/ffmpeg first: on a panel-managed host (open_basedir set, no
     * root) dropping a static ffmpeg build next to this script is usually the
     * only way to get one, and it is the only path PHP is certain to be allowed
     * to reach. */
    foreach (video_ffmpeg_candidates() as $cand) {
        $out = video_capture([$cand, '-version'], 10);
        if ($out !== null && stripos($out, 'ffmpeg version') !== false) { $found = $cand; break; }
    }
    /* A binary kept inside the site directory would otherwise be downloadable
     * over HTTP — tens of megabytes of bandwidth for anyone who guesses the
     * path. Same deny rule as the cache directories get. */
    if ($found !== '' && strpos($found, __DIR__) === 0) protect_dir_(dirname($found));
    @file_put_contents($probe, $found);
    return $found !== '' ? $found : null;
}

/* ── Which function may we use to start a process? Managed hosting panels put
 * some or all of these in disable_functions — often proc_open specifically —
 * so try them in order of preference instead of giving up on the first. ── */
function video_spawner() {
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    foreach (['proc_open', 'exec', 'shell_exec'] as $fn) {
        if (function_exists($fn) && !in_array($fn, $disabled, true)) return $fn;
    }
    return null;
}

/* Where to look for an ffmpeg binary, best bet first. */
function video_ffmpeg_candidates() {
    return [
        __DIR__ . '/bin/ffmpeg',
        'ffmpeg',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/usr/local/ffmpeg/bin/ffmpeg',
        '/opt/ffmpeg/bin/ffmpeg',
    ];
}

/* ── Run a command, sending its stdout to $stdoutFile (or discarding it).
 * Returns true if the command actually ran. Output goes to a FILE rather than
 * a pipe on purpose: pipes need proc_open, and on a locked-down host that is
 * exactly the function that has been taken away — writing to a file lets the
 * same code work through exec/shell_exec as well. ── */
function video_run($argv, $timeoutSec, $stdoutFile = null, &$stderrOut = null) {
    $spawn = video_spawner();
    if (!$spawn) { $stderrOut = 'PHP is not allowed to start processes'; return false; }

    if ($spawn === 'proc_open') {
        /* Both streams go to files inside the cache directory, never to
         * /dev/null. With open_basedir in force (every managed panel sets it)
         * PHP opens these paths itself, so /dev/null is outside the allowed
         * list and proc_open fails outright — which looks exactly like "no
         * ffmpeg on this server". Writing inside the cache directory is always
         * permitted, since that is where this script already works. */
        $scratch = video_cache_dir() . 'run-' . getmypid();
        $outPath = $stdoutFile ?: ($scratch . '.out');
        $errPath = $scratch . '.err';
        $desc = [
            1 => ['file', $outPath, 'w'],
            2 => ['file', $errPath, 'w'],
        ];
        $proc = @proc_open($argv, $desc, $pipes);
        if (!is_resource($proc)) { @unlink($errPath); if (!$stdoutFile) @unlink($outPath); return false; }
        $deadline = microtime(true) + $timeoutSec;
        while (true) {
            $st = proc_get_status($proc);
            if (!$st['running']) break;
            if (microtime(true) > $deadline) { proc_terminate($proc, 9); break; }
            usleep(50000);
        }
        proc_close($proc);
        $stderrOut = video_tail_file($errPath);
        @unlink($errPath);
        if (!$stdoutFile) @unlink($outPath);
        return true;
    }

    /* exec/shell_exec path: every argument is escaped individually, and the
     * `timeout` utility caps the run when it is available. */
    $scratch = video_cache_dir() . 'run-' . getmypid();
    $errPath = $scratch . '.err';
    $parts = array_map('escapeshellarg', $argv);
    $cmd   = implode(' ', $parts)
           . ' > ' . escapeshellarg($stdoutFile ?: $scratch . '.out')
           . ' 2> ' . escapeshellarg($errPath);
    if (is_file('/usr/bin/timeout')) {
        $cmd = '/usr/bin/timeout ' . (int)$timeoutSec . ' ' . $cmd;
    }
    if ($spawn === 'exec') { @exec($cmd); }
    else                   { @shell_exec($cmd); }
    $stderrOut = video_tail_file($errPath);
    @unlink($errPath);
    if (!$stdoutFile) @unlink($scratch . '.out');
    return true;
}

/* Last line or two of a log file — enough to say what went wrong, short enough
 * to put in a JSON response. */
function video_tail_file($path, $max = 400) {
    if (!is_file($path)) return '';
    $txt = (string)@file_get_contents($path);
    $txt = trim(preg_replace('/\s+/', ' ', $txt));
    return (strlen($txt) > $max) ? '…' . substr($txt, -$max) : $txt;
}

/* ── Same, but hand back what the command printed (used for `ffmpeg -version`
 * and for the poster frame). ── */
function video_capture($argv, $timeoutSec, &$stderrOut = null) {
    $tmp = video_cache_dir() . 'capture-' . getmypid() . '.tmp';
    if (!video_run($argv, $timeoutSec, $tmp, $stderrOut)) { @unlink($tmp); return null; }
    $out = is_file($tmp) ? (string)@file_get_contents($tmp) : '';
    @unlink($tmp);
    return $out;
}

/* ── Build (once) and return the path of a speaker video's preview clip.
 * The clip is cut straight off the front of the source with -c copy: no
 * re-encode, so it costs a couple of seconds of CPU and keeps the original
 * quality for the seconds it contains. The result is a complete little MP4 —
 * which is the whole point, because a browser will not play a partial one.
 * Returns null when no clip can be made (no ffmpeg, or a build that failed);
 * the caller then serves the original instead. ── */
function video_clip_path($fileId, &$why = null, $retryFailed = false) {
    $dir  = video_cache_dir();
    $clip = $dir . $fileId . '_clip.mp4';

    if (is_file($clip) && filesize($clip) > 100000) { @touch($clip); return $clip; }

    $bin = video_ffmpeg_bin();
    if (!$bin) { $why = 'No ffmpeg available — see action=videoDiag'; return null; }

    /* Remember a failed build for a while instead of re-trying it on every
     * single request, and keep the reason in the marker so it can be reported
     * instead of a bare "failed". */
    $failed = $dir . $fileId . '.noclip';
    if (is_file($failed)) {
        /* $retryFailed: prewarmVideos is someone deliberately asking for these
         * clips now, so it must actually try — reporting "a previous build
         * failed" about a failure from before the server could even run ffmpeg
         * is no use to anyone. Ordinary page requests still respect the marker,
         * so a genuinely broken video is not retried on every single view. */
        if ($retryFailed) {
            @unlink($failed);
        } elseif ((time() - filemtime($failed)) < 3600) {
            $why = trim((string)@file_get_contents($failed)) ?: 'A previous build failed';
            return null;
        }
    }

    /* One builder at a time per video. A second request waits for the first to
     * finish rather than doing the same work again. */
    $lockPath = $dir . $fileId . '.clip.lock';
    $lock = @fopen($lockPath, 'c');
    if (!$lock) return null;
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        for ($i = 0; $i < 60; $i++) {          /* up to ~30 s */
            usleep(500000);
            clearstatcache(true, $clip);
            if (is_file($clip) && filesize($clip) > 100000) return $clip;
        }
        return null;
    }

    @set_time_limit(0);
    $built = video_build_clip($fileId, $clip, $bin, $why);
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockPath);

    if (!$built) {
        @file_put_contents($failed, (string)$why);
        return null;
    }
    return $clip;
}

/* ── Cut the clip. Pulls only as much of the source as the clip needs, straight
 * into one file, hands it to ffmpeg, and keeps that source around until the
 * clip is finished — a run killed by a web-server timeout then resumes from the
 * encode instead of re-downloading a hundred megabytes. Returns true, or false
 * with the reason in $why. ── */
function video_build_clip($fileId, $clipPath, $bin, &$why = null) {
    $meta = drive_file_meta($fileId);
    if (strpos((string)($meta['mimeType'] ?? ''), 'video/') !== 0) {
        $why = 'Not a video, or Drive would not describe the file'; return false;
    }
    $size = (int)($meta['size'] ?? 0);
    $dur  = (int)($meta['durationMs'] ?? 0);
    if ($size <= 0) { $why = 'Drive reported no size for this file'; return false; }

    $seconds = VIDEO_CLIP_SECONDS;

    /* How much of the source the clip actually needs, plus a margin so the cut
     * is not starved. No length limit means all of it. */
    if ($seconds > 0 && $dur > 0) {
        $needBytes = (int)ceil($size * (min($seconds + 3, $dur / 1000) / ($dur / 1000)));
    } else {
        $needBytes = $size;
    }
    $needBytes = min($needBytes, $size, VIDEO_CLIP_MAX_BLOCKS * VIDEO_PREVIEW_BLOCK_BYTES);

    /* Refuse to start a build that cannot finish — a half-written source and a
     * full disk is a worse outcome than a clear message. */
    $free = @disk_free_space(video_cache_dir());
    if ($free !== false && $free < ($needBytes * 1.4)) {
        $why = 'Not enough free disk space: needs about ' . round($needBytes * 1.4 / 1048576)
             . ' MB, ' . round($free / 1048576) . ' MB free';
        return false;
    }

    /* The source, fetched once and reused until the clip exists. */
    $src = video_cache_dir() . $fileId . '_src.bin';
    $have = is_file($src) ? (int)filesize($src) : 0;
    if ($have > $needBytes) { @unlink($src); $have = 0; }   /* stale, from a different setting */

    if ($have < $needBytes) {
        $fh = @fopen($src, $have ? 'ab' : 'wb');
        if (!$fh) { $why = 'Could not write to ' . video_cache_dir(); return false; }
        $block = VIDEO_PREVIEW_BLOCK_BYTES;
        while ($have < $needBytes) {
            $end   = min($have + $block, $needBytes) - 1;
            $driveSaid = null;
            $chunk = drive_range_fetch($fileId, $have, $end, $driveSaid);
            if ($chunk === null || $chunk === '') {
                fclose($fh);
                $why = 'Drive refused bytes ' . $have . '-' . $end
                     . ($driveSaid ? ' — ' . $driveSaid : ' (no reply)');
                return false;
            }
            fwrite($fh, $chunk);
            $have += strlen($chunk);
        }
        fclose($fh);
    }

    $tmpOut = $clipPath . '.' . getmypid() . '.tmp.mp4';

    /* Watermark, best first: a full-frame sheet of repeated logos (what the
     * photos get), then a single centred logo, then none at all — a preview
     * with a weaker watermark still beats no preview. */
    $attempts = [];
    if ($sheet = video_watermark_sheet(VIDEO_CLIP_HEIGHT)) $attempts[] = ['sheet', $sheet];
    if ($logo  = video_logo_png())                         $attempts[] = ['logo',  $logo];
    $attempts[] = [null, null];

    $err = '';
    foreach ($attempts as $attempt) {
        list($kind, $wm) = $attempt;
        $cmd = [$bin, '-v', 'error', '-y', '-i', $src];
        if ($wm) { $cmd[] = '-i'; $cmd[] = $wm; }
        if ($seconds > 0) { $cmd[] = '-t'; $cmd[] = (string)$seconds; }
        if ($kind === 'sheet') {
            /* The sheet is already the right height and deliberately too wide;
             * overlay crops it to the frame. */
            $cmd[] = '-filter_complex';
            $cmd[] = '[0:v]scale=-2:' . (int)VIDEO_CLIP_HEIGHT . ',setsar=1[base];'
                   . '[base][1:v]overlay=0:0:format=auto';
        } elseif ($kind === 'logo') {
            $cmd[] = '-filter_complex';
            $cmd[] = '[0:v]scale=-2:' . (int)VIDEO_CLIP_HEIGHT . ',setsar=1[base];'
                   . '[1:v]format=rgba,colorchannelmixer=aa=0.22,scale=360:-1[wm];'
                   . '[base][wm]overlay=(W-w)/2:(H-h)/2';
        } else {
            $cmd[] = '-vf'; $cmd[] = 'scale=-2:' . (int)VIDEO_CLIP_HEIGHT;
        }
        array_push($cmd,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '30',
            /* main/4.0 plays on everything a buyer might open this on,
             * including older phones. */
            '-profile:v', 'main', '-level', '4.0',
            '-maxrate', VIDEO_CLIP_MAX_KBPS . 'k', '-bufsize', (VIDEO_CLIP_MAX_KBPS * 2) . 'k',
            '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            '-movflags', '+faststart', '-f', 'mp4', $tmpOut);

        video_run($cmd, 900, null, $err);
        clearstatcache(true, $tmpOut);
        if (is_file($tmpOut) && filesize($tmpOut) > 100000) break;
        @unlink($tmpOut);
    }

    clearstatcache(true, $tmpOut);
    if (is_file($tmpOut) && filesize($tmpOut) > 100000) {
        if (!@rename($tmpOut, $clipPath)) { @unlink($tmpOut); $why = 'Could not save the finished clip'; return false; }
        @unlink($src);              /* scratch material, no longer needed */
        video_cache_prune();
        return true;
    }
    @unlink($tmpOut);
    $why = 'ffmpeg did not produce a clip' . ($err !== '' ? ': ' . $err : ' (no error output)');
    return false;
}

/* ── A full-frame sheet of repeated AIFOD logos, for burning into preview clips.
 * One overlay of a pre-tiled sheet is far cheaper than asking ffmpeg to repeat
 * a logo, and it reuses the very same tile the photo watermark uses, so a
 * preview video and a preview photo look like they came from the same place.
 * The sheet is made wider than any frame it will cover — overlay crops the
 * excess — so it works whatever aspect ratio the source turns out to be. ── */
function video_watermark_sheet($height = 720, $width = 2400) {
    $path = video_cache_dir() . 'wm-sheet-' . (int)$width . 'x' . (int)$height . '.png';
    if (is_file($path) && filesize($path) > 500) return $path;

    if (!function_exists('imagecreatetruecolor')) return null;
    $tile = get_watermark_logo_gd();
    if (!$tile) return null;

    $tw = imagesx($tile); $th = imagesy($tile);
    $sheet = imagecreatetruecolor($width, $height);
    imagealphablending($sheet, false);
    imagesavealpha($sheet, true);
    imagefill($sheet, 0, 0, imagecolorallocatealpha($sheet, 0, 0, 0, 127));
    imagealphablending($sheet, true);

    /* Same spacing and every-other-row offset as watermark_image_gd(), so the
     * pattern reads as one house style rather than two. */
    $stepX = $tw + 60;
    $stepY = $th + 70;
    $row = 0;
    for ($y = -$stepY; $y < $height + $stepY; $y += $stepY) {
        $offset = ($row % 2 === 0) ? 0 : intval($stepX / 2);
        for ($x = -$stepX; $x < $width + $stepX; $x += $stepX) {
            imagecopy($sheet, $tile, $x + $offset, $y, 0, 0, $tw, $th);
        }
        $row++;
    }
    imagedestroy($tile);

    imagesavealpha($sheet, true);
    $ok = @imagepng($sheet, $path);
    imagedestroy($sheet);
    return $ok ? $path : null;
}

/* ── The AIFOD logo as a local PNG, for burning into preview clips. Fetched
 * once from the site and kept next to the cache; null if it cannot be had (the
 * clip is then built without a watermark). ── */
function video_logo_png() {
    $path = video_cache_dir() . 'aifod-logo.png';
    if (is_file($path) && filesize($path) > 500) return $path;

    $url = 'https://af.net/wp-content/uploads/cropped-aifod-logo-version-2-1320x528.png';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !is_string($data) || strlen($data) < 500) return null;
    if (substr($data, 1, 3) !== 'PNG') return null;
    return (@file_put_contents($path, $data) !== false) ? $path : null;
}

/* ── A poster frame from a few seconds in, taken from the preview clip we
 * already built (or, failing that, from the first cached block). Drive's own
 * video thumbnail is always frame 0, which for Summit footage is the shared
 * AIFOD title slate — every video would get the identical tile. ── */
function video_poster_frame($fileId, $width) {
    $bin = video_ffmpeg_bin();
    if (!$bin) return null;

    $src = video_clip_path($fileId);
    $limitSeconds = VIDEO_CLIP_SECONDS;

    if (!$src) {
        /* No clip on this server — fall back to the first cached block, which
         * only covers the opening few seconds at Summit bitrates. */
        $meta = drive_file_meta($fileId);
        $size = (int)($meta['size'] ?? 0);
        $dur  = (int)($meta['durationMs'] ?? 0);
        if ($size <= 0) return null;
        $block = VIDEO_PREVIEW_BLOCK_BYTES;
        if (video_block($fileId, 0, 0, min($block, $size) - 1) === null) return null;
        $src = video_cache_dir() . $fileId . '_b0.bin';
        $limitSeconds = ($dur > 0) ? (($dur / 1000) * (min($block, $size) / $size)) : 4;
    }
    if (!is_file($src)) return null;

    /* Stay inside what the source actually contains. */
    $seek = max(1, (int)floor(min(VIDEO_POSTER_SECOND, $limitSeconds * 0.7)));

    $out = video_cache_dir() . 'poster-' . getmypid() . '.jpg';
    @unlink($out);
    video_run([
        $bin, '-v', 'error', '-y', '-ss', (string)$seek, '-i', $src,
        '-frames:v', '1', '-vf', 'scale=' . (int)$width . ':-2',
        '-f', 'image2', '-vcodec', 'mjpeg', '-q:v', '4', $out,
    ], 30);
    $jpeg = is_file($out) ? (string)@file_get_contents($out) : '';
    @unlink($out);

    /* Sanity-check it really is a JPEG before handing it on. */
    if (is_string($jpeg) && strlen($jpeg) > 1000 && substr($jpeg, 0, 2) === "\xFF\xD8") return $jpeg;
    return null;
}

/* ── Serve a local file with proper Range support, so the <video> element can
 * seek in it and the browser can resume a dropped connection. ── */
function video_serve_local_file($path, $mime) {
    $size = (int)@filesize($path);
    if ($size <= 0) { http_response_code(502); echo json_encode(['ok'=>false,'error'=>'Empty preview']); return; }

    /* Readable, not merely present. A clip unzipped as root is 'there' as far
     * as filesize() is concerned — stat only needs the directory — while PHP
     * running as the web user cannot open a byte of it. Answering that with
     * headers and an empty body is the worst of both worlds: the browser says
     * the video could not be loaded, and a proxy in front turns the
     * length-mismatch into a 520 of its own, so nothing anywhere names the
     * cause. Say it plainly instead. */
    if (!is_readable($path)) {
        http_response_code(500);
        header('Content-Type: application/json');
        $owner = function_exists('posix_getpwuid')
            ? (@posix_getpwuid(@fileowner($path))['name'] ?? '?') : '?';
        echo json_encode(['ok' => false,
            'error' => 'The preview file exists but PHP cannot read it — check ownership '
                     . 'and permissions on video-cache (chown -R to the web user, chmod 644 '
                     . 'on the clips).',
            'file'  => basename($path),
            'mode'  => substr(sprintf('%o', @fileperms($path)), -4),
            'owner' => $owner,
            'phpRunsAs' => function_exists('posix_geteuid')
                ? (@posix_getpwuid(posix_geteuid())['name'] ?? '?') : '?']);
        return;
    }

    $start = 0; $end = $size - 1; $partial = false;
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $partial = true;
        if ($m[1] !== '') $start = (int)$m[1];
        if ($m[2] !== '') $end   = min((int)$m[2], $size - 1);
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }
    }

    /* Compression and buffering have to go before a byte is sent. A host with
     * zlib.output_compression on will happily gzip a video while we advertise
     * the raw byte count, and the length no longer matches what arrives —
     * nginx answers 502, Cloudflare turns that into its own 520, and the page
     * says "This video could not be loaded" about a file that is perfectly
     * fine. Same for nginx buffering a multi-megabyte response it does not
     * need to hold. */
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    while (ob_get_level() > 0) ob_end_clean();
    header_remove('Content-Encoding');
    header_remove('Vary');

    http_response_code($partial ? 206 : 200);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    header('X-Accel-Buffering: no');       /* nginx: stream it, do not buffer */
    if ($partial) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Cache-Control: private, max-age=86400');

    $fp = fopen($path, 'rb');
    if (!$fp) return;                     /* headers are out; nothing useful left to say */
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(262144, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
}

/* ── Last-resort streaming path: hand a bounded slice straight through from
 * Drive without caching it (used when metadata or the cache is unavailable).
 * $logicalSize is what we advertise as the file's total length. ── */
function video_stream_passthrough($fileId, $mime, $start, $end, $logicalSize) {
    @ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    while (ob_get_level() > 0) ob_end_clean();
    header_remove('Content-Encoding');
    $sent = false;
    $url  = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&supportsAllDrives=true&key=" . DRIVE_API_KEY;
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Range: bytes=' . $start . '-' . $end, 'User-Agent: AIFOD-Proxy/1.0'],
        CURLOPT_WRITEFUNCTION  => function ($curl, $chunk) use (&$sent, $mime, $start, $end, $logicalSize) {
            if (!$sent) {
                $sent = true;
                http_response_code(206);
                header('Content-Type: ' . $mime);
                header('Accept-Ranges: bytes');
                header('Content-Length: ' . ($end - $start + 1));
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $logicalSize);
                header('Cache-Control: private, max-age=3600');
            }
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    if (!$sent) http_response_code(502);
}

/* ── Short-lived disk cache for a speaker's photo+video file-ID lists.
 * The Drive round trip (even parallelized) still costs real network time on
 * every preview open; caching it means re-opening the same speaker (very
 * common while browsing/testing) is instant. 10 min is long enough to help,
 * short enough that newly-uploaded media shows up without a manual clear. ── */
function list_cache_get($key) {
    $f = __DIR__ . '/list-cache/' . $key . '.json';
    if (!file_exists($f)) return null;
    if (time() - filemtime($f) > 600) return null;
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : null;
}
function list_cache_put($key, $data) {
    $dir = ensure_private_dir_(__DIR__ . '/list-cache/', 0775);
    @file_put_contents($dir . $key . '.json', json_encode($data));
}

/* ── Shared, idempotent order recording — used by both the recordOrder
 * action (client-side callback, the common path) and stripeWebhook (the
 * safety net for when the buyer's tab closes/crashes right after paying and
 * that client-side callback never runs). Checks the CSV for an existing row
 * with this piId first so calling it twice for the same order is a no-op,
 * not a duplicate. ── */
function order_already_recorded($piId) {
    if (!file_exists(ORDERS_FILE)) return false;
    $fp = fopen(ORDERS_FILE, 'r');
    fgetcsv($fp); /* header */
    while (($row = fgetcsv($fp)) !== false) {
        if (($row[7] ?? '') === $piId) { fclose($fp); return true; }
    }
    fclose($fp);
    return false;
}

function record_paid_order($piId, $meta, $fallbackName = '', $fallbackEmail = '', $fallbackSpeaker = '', $fallbackPkg = '', $fallbackAmount = '') {
    $finalName = $meta['buyerName']   ?? $fallbackName;
    $finalEmail= $meta['buyerEmail']  ?? $fallbackEmail;
    $finalSpk  = $meta['speakerName'] ?? $fallbackSpeaker;
    $finalPkg  = $meta['package']     ?? $fallbackPkg;
    $finalAmt  = $meta['price']       ?? $fallbackAmount;

    if (order_already_recorded($piId)) {
        return ['speakerName' => $finalSpk, 'alreadyRecorded' => true];
    }

    /* Save to CSV (reliable local record of truth) */
    $csvDir = dirname(ORDERS_FILE);
    ensure_private_dir_($csvDir);
    $isNew = !file_exists(ORDERS_FILE);
    $fp = fopen(ORDERS_FILE, 'a');
    if ($isNew) fputcsv($fp, ['Timestamp','Buyer Name','Buyer Email','Speaker','Status','Package','Amount','Payment Intent ID']);
    fputcsv($fp, [date('Y-m-d H:i:s'), $finalName, $finalEmail, $finalSpk, 'Paid', $finalPkg, $finalAmt, $piId]);
    fclose($fp);

    /* Record into the Google Sheet — retried + logged so it stops silently dropping */
    apps_script_call([
        'action'=>'recordOrder','piId'=>$piId,
        'buyerName'=>$finalName,'buyerEmail'=>$finalEmail,
        'speakerName'=>$finalSpk,'pkg'=>$finalPkg,'amount'=>$finalAmt,
    ]);

    return ['speakerName' => $finalSpk, 'alreadyRecorded' => false];
}

/* ── Shared, idempotent gallery-token creation — used by generateGalleryToken
 * (client-side callback) and stripeWebhook (safety net). Scans existing
 * tokens for this piId first so a webhook firing after the client already
 * succeeded doesn't email the buyer a second, redundant gallery link. ── */
function gallery_token_for_piid($piId) {
    $dir = __DIR__ . '/gallery-tokens/';
    if (!is_dir($dir)) return null;
    foreach (glob($dir . '*.json') as $f) {
        $data = json_decode(file_get_contents($f), true);
        if (($data['piId'] ?? '') === $piId) {
            return ['token' => basename($f, '.json'), 'data' => $data];
        }
    }
    return null;
}

function create_gallery_token($piId, $meta, $fallbackSpeaker = '', $fallbackPkg = '') {
    $existing = gallery_token_for_piid($piId);
    if ($existing) {
        return [
            'token' => $existing['token'],
            'galleryUrl' => 'https://af.net/gallery-2/?token=' . $existing['token'],
            'alreadyExisted' => true,
        ];
    }

    $speakerName = $meta['speakerName'] ?? $fallbackSpeaker;
    $pkg         = $meta['package']     ?? $fallbackPkg;

    $token    = bin2hex(random_bytes(24));
    $tokenDir = __DIR__ . '/gallery-tokens/';
    ensure_private_dir_($tokenDir);
    $record = [
        'piId'        => $piId,
        'speakerName' => $speakerName,
        'pkg'         => $pkg,
        'buyerName'   => $meta['buyerName']  ?? '',
        'buyerEmail'  => $meta['buyerEmail'] ?? '',
        'created'     => time(),
    ];
    file_put_contents($tokenDir . $token . '.json', json_encode($record));

    /* Email the buyer their secure gallery link so they can review + confirm.
     * Delivery of the final files happens later, when they confirm in the
     * gallery (for every package type). */
    $galleryUrl = 'https://af.net/gallery-2/?token=' . $token;
    apps_script_call([
        'action'      => 'sendGalleryLink',
        'buyerEmail'  => $record['buyerEmail'],
        'buyerName'   => $record['buyerName'],
        'speakerName' => $record['speakerName'],
        'pkg'         => $record['pkg'],
        'galleryUrl'  => $galleryUrl,
    ]);

    return ['token' => $token, 'galleryUrl' => $galleryUrl, 'alreadyExisted' => false];
}

/* ── Verify a Stripe webhook request actually came from Stripe. Implements
 * Stripe's documented signature scheme by hand (no SDK dependency): the
 * Stripe-Signature header looks like "t=<timestamp>,v1=<hex hmac>", and the
 * expected signature is HMAC-SHA256 of "<timestamp>.<raw body>" keyed with
 * the webhook's signing secret. Also rejects stale signatures (>5 min old)
 * to block replaying a captured request. Fails closed: any error here means
 * the event is NOT trusted. ── */
function verify_stripe_webhook_signature($payload, $sigHeader, $secret, $tolerance = 300) {
    if (!$secret || strpos($secret, 'REPLACE_WITH') === 0) return false;
    if (!$sigHeader) return false;

    $timestamp = null; $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't') $timestamp = $kv[1];
        elseif ($kv[0] === 'v1') $signatures[] = $kv[1];
    }
    if (!$timestamp || !$signatures) return false;
    if (abs(time() - intval($timestamp)) > $tolerance) return false;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

/* ── Apps Script call with retry + failure log (fixes orders not reaching the
 *    Google Sheet). CSV already has the order, so a logged failure is never a
 *    lost sale — it can be replayed from apps-script-fails.log. ── */
function apps_script_call($params, $retries = 3) {
    $qs = http_build_query($params);
    for ($i = 0; $i < $retries; $i++) {
        $ch = curl_init(APPS_SCRIPT . '?' . $qs);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>25, CURLOPT_SSL_VERIFYPEER=>false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($resp ?: '', true);
        if ($code === 200 && is_array($decoded) && !empty($decoded['ok'])) {
            return $decoded;
        }
        if ($i < $retries - 1) usleep((int)(pow(2, $i) * 400000)); // 0.4s, 0.8s
    }
    /* Persist the failure so nothing is silently dropped. */
    @file_put_contents(FAIL_LOG,
        date('c') . "\t" . $qs . "\t" . substr((string)$resp, 0, 500) . "\n",
        FILE_APPEND);
    return ['ok'=>false];
}

/* ── Trigger automated delivery in Apps Script. Fire-and-forget with logging;
 *    the Apps Script side is idempotent (one folder per piId). ── */
function trigger_delivery($record, $ids) {
    apps_script_call([
        'action'      => 'deliver',
        'piId'        => $record['piId']        ?? '',
        'speakerName' => $record['speakerName'] ?? '',
        'pkg'         => $record['pkg']         ?? '',
        'buyerName'   => $record['buyerName']   ?? '',
        'buyerEmail'  => $record['buyerEmail']  ?? '',
        'selection'   => implode(',', $ids),
    ]);
}
