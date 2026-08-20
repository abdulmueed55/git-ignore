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

ob_start(); /* Buffer output to prevent warnings corrupting JSON */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); exit; }

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
        'Test Speaker'  => '1j_MWqd6dS7qhAyReOWq-K2swFt4FRj7U', // TEST — uses Tianze's folder; remove before live
        'Tianze Zhang'  => '1j_MWqd6dS7qhAyReOWq-K2swFt4FRj7U',
        // 'Fredrik Kocon' => '...',
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

    $meta      = $pi['metadata'] ?? [];
    $finalName = $meta['buyerName']   ?? $buyerName;
    $finalEmail= $meta['buyerEmail']  ?? $buyerEmail;
    $finalSpk  = $meta['speakerName'] ?? $speakerName;
    $finalPkg  = $meta['package']     ?? $pkg;
    $finalAmt  = $meta['price']       ?? $amount;

    /* Save to CSV (reliable local record of truth) */
    $csvDir = dirname(ORDERS_FILE);
    if (!is_dir($csvDir)) mkdir($csvDir, 0755, true);
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

    echo json_encode(['ok'=>true,'speaker'=>$finalSpk]);
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

/* ══ 4. List Drive Folder Files ══ */
if ($action === 'listFolder') {
    $folderId = $_GET['folderId'] ?? '';
    if (!$folderId) { echo json_encode(['ok'=>false,'error'=>'No folderId']); exit; }

    $res = drive_list($folderId);
    $images = []; $videos = [];
    foreach ($res as $f) {
        $mime = $f['mimeType'] ?? '';
        if (strpos($mime, 'image/') === 0)      $images[] = $f['id'];
        elseif (strpos($mime, 'video/') === 0)  $videos[] = $f['id'];
    }
    echo json_encode(['ok'=>true,'images'=>$images,'videos'=>$videos]);
    exit;
}

/* ══ 5. Get Image Thumbnail ══ */
if ($action === 'getThumb') {
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId) { http_response_code(400); exit; }

    /* Drive thumbnail — try sizes from largest to smallest */
    $sizes = ['w1200', 'w800', 'w400', 'w200'];
    $imgData = null; $ctype = 'image/jpeg';
    foreach ($sizes as $sz) {
        $url = "https://drive.google.com/thumbnail?id={$fileId}&sz={$sz}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 15,
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

    /* Fallback: Drive API media (works for files the API key can read) */
    if (!$imgData) {
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
        http_response_code(502);
        echo json_encode(['error'=>'Could not fetch image']);
        exit;
    }

    header('Content-Type: ' . $ctype);
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    echo $imgData;
    exit;
}

/* ══ 6. Stream Video ══ */
if ($action === 'streamVideo') {
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId) { http_response_code(400); exit; }

    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&key=" . DRIVE_API_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>60,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_USERAGENT=>'Mozilla/5.0 AIFOD-Proxy/1.0',
    ]);
    $data = curl_exec($ch);
    $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!$data) { http_response_code(502); exit; }
    header('Content-Type: ' . ($ct ?: 'video/mp4'));
    header('Cache-Control: no-store');
    header('Content-Disposition: inline');
    echo $data;
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
if ($action === 'viewOrders' && ($_GET['key']??'') === 'aifod2026') {
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

/* ══ 8b. Reset ALL previous data (ADMIN — testing only) ══
 * Visit: stripe-proxy.php?action=resetOrders&key=aifod2026&confirm=yes
 * Clears the orders CSV, every gallery token, the fail log, the Google Sheet
 * rows, and (reversibly — moved to Drive trash, not deleted) every test
 * delivery folder. After this nothing is sold-out and the delivery folder is
 * empty. Needs the latest Apps Script deployed for the sheet/deliveries part. */
if ($action === 'resetOrders' && ($_GET['key'] ?? '') === 'aifod2026') {
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
    $r = apps_script_call(['action'=>'clearOrders', 'key'=>'aifod2026']);
    $out['sheet'] = !empty($r['ok']);

    /* Trash every test delivery folder (reversible — Drive trash, not gone). */
    $r2 = apps_script_call(['action'=>'clearDeliveries', 'key'=>'aifod2026']);
    $out['deliveries'] = $r2['cleared'] ?? 0;

    echo json_encode(['ok'=>true, 'cleared'=>$out]);
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

    $meta = $pi['metadata'] ?? [];
    /* Trust the payment metadata over the query string. */
    $speakerName = $meta['speakerName'] ?? $speakerName;
    $pkg         = $meta['package']     ?? $pkg;

    $token    = bin2hex(random_bytes(24));
    $tokenDir = __DIR__ . '/gallery-tokens/';
    if (!is_dir($tokenDir)) mkdir($tokenDir, 0755, true);
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

    echo json_encode(['ok'=>true,'token'=>$token,'galleryUrl'=>$galleryUrl]);
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
    $folders  = speaker_folders();
    $folderId = $folders[$spk] ?? '';
    if (!$folderId) { echo json_encode(['ok'=>false,'error'=>'No folder for speaker']); exit; }

    $files  = drive_list($folderId, 200);
    $images = []; $videos = [];
    foreach ($files as $fi) {
        $mime = $fi['mimeType'] ?? '';
        if (strpos($mime,'image/')===0) $images[] = ['id'=>$fi['id'],'name'=>$fi['name']];
        elseif (strpos($mime,'video/')===0) $videos[] = ['id'=>$fi['id'],'name'=>$fi['name']];
    }
    $info  = pkg_info($data['pkg'] ?? '');
    $limit = $info ? $info['limit'] : null;
    echo json_encode(['ok'=>true,'images'=>$images,'videos'=>$videos,'pkg'=>$data['pkg']??'',
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
        foreach (drive_list($folderId, 300) as $fi) {
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

/* ── Drive: list a folder's files (id, name, mimeType) ── */
function drive_list($folderId, $pageSize = 100) {
    $q   = urlencode("'" . $folderId . "' in parents and trashed=false");
    $url = "https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id,name,mimeType)&pageSize={$pageSize}&key=" . DRIVE_API_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp, true)['files'] ?? [];
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
