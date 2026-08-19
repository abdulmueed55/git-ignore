/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  AIFOD Geneva Summit 2026 — Media Package Google Apps Script
 *  Deploy as: Web app  ·  Execute as: Me  ·  Who has access: Anyone
 * ───────────────────────────────────────────────────────────────────────────
 *  Actions (called by stripe-proxy.php via ?action=...):
 *    recordOrder   → append the paid order to the Google Sheet
 *    getSold       → return the list of speakers already sold
 *    deliver       → build the per-order delivery folder, copy the entitled
 *                    files in, share it, and email the buyer + the team
 *    ping          → health check
 *
 *  After ANY edit here you MUST redeploy:
 *    Deploy ▸ Manage deployments ▸ (edit) ▸ Version: New version ▸ Deploy
 *  Re-using the same deployment keeps the /exec URL stable (the one already in
 *  stripe-proxy.php + the HTML), so you never have to touch those files again.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ── CONFIG — fill the three TODO values, then redeploy ────────────────────── */
var CONFIG = {
  SHEET_ID:  '1QGYSBawEBefEgw-SrU9qgjXfSqy1rnZDGbxy2SkLF6I',
  SHEET_TAB: 'Orders',            // tab name; created automatically if missing

  // Parent folder "AIFOD Media Deliveries 2026" — per-order folders go in here.
  DELIVERY_PARENT_ID: '1wysRT7lAZXch2oJzaKbv7V3u2brRb6QB',

  NOTIFY:     ['z@af.net', 'abdul@af.net'],   // team notification recipients
  FROM_NAME:  'AIFOD Geneva Summit 2026',
  DELIVER_BY: '25 August 2026'
};

/* ── SPEAKER → SOURCE (MASTER) DRIVE FOLDER ─────────────────────────────────
 * Each speaker's master folder holds ALL their photos + videos.
 * We only ever COPY out of these — the master is never shared or moved.
 * Keep this list in sync with stripe-proxy.php speaker_folders() and the
 * AP_FOLDERS map in the sales-page HTML.
 * TODO: add every speaker as  'Speaker Name': 'DRIVE_FOLDER_ID'
 */
var SPEAKER_FOLDERS = {
  'Tianze Zhang': '1j_MWqd6dS7qhAyReOWq-K2swFt4FRj7U'
  // 'Fredrik Kocon': '...',
  // 'Valerie Pelton': '...',
};

/* ── PACKAGE ENTITLEMENTS (single source of truth, mirrors the spec) ─────────
 *   limit  : selection_limit — a number means "buyer selects", null means
 *            skip selection and deliver everything they're entitled to.
 *   photos : 'all' | 'limited' | 'none'
 *   video  : true | false
 */
var PACKAGES = {
  'Select 25 Photos':     { limit: 25,   photos: 'limited', video: false },
  'Select 50 Photos':     { limit: 50,   photos: 'limited', video: false },
  'All Photos Super XL':  { limit: null, photos: 'all',     video: false },
  'Video Package':        { limit: null, photos: 'none',    video: true  },
  'Complete Media Bundle':{ limit: null, photos: 'all',     video: true  }
};

/* ═══════════════════════════ ROUTER ═══════════════════════════ */
function doGet(e)  { return handle_(e); }
function doPost(e) { return handle_(e); }

function handle_(e) {
  var p = (e && e.parameter) || {};
  var action = p.action || '';
  try {
    if (action === 'recordOrder') return json_(recordOrder_(p));
    if (action === 'getSold')     return json_(getSold_());
    if (action === 'deliver')     return json_(deliver_(p));
    if (action === 'ping')        return json_({ ok: true, pong: true });
    return json_({ ok: false, error: 'Unknown action: ' + action });
  } catch (err) {
    // Never throw an HTML error page back to the proxy — always return JSON.
    return json_({ ok: false, error: String(err && err.message || err) });
  }
}

function json_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/* ═══════════════════════════ 1. RECORD ORDER ═══════════════════════════ */
function recordOrder_(p) {
  var sheet = getSheet_();
  var piId = String(p.piId || '').trim();

  // Idempotent: if this payment intent is already logged, don't duplicate it.
  if (piId) {
    var ids = sheet.getRange(1, 8, Math.max(sheet.getLastRow(), 1), 1).getValues();
    for (var i = 0; i < ids.length; i++) {
      if (String(ids[i][0]).trim() === piId) {
        return { ok: true, duplicate: true };
      }
    }
  }

  sheet.appendRow([
    new Date(),
    p.buyerName  || '',
    p.buyerEmail || '',
    p.speakerName|| '',
    'Paid',
    p.pkg        || '',
    p.amount     || '',
    piId
  ]);
  return { ok: true };
}

function getSheet_() {
  var ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
  var sheet = ss.getSheetByName(CONFIG.SHEET_TAB);
  if (!sheet) {
    sheet = ss.insertSheet(CONFIG.SHEET_TAB);
  }
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(['Timestamp', 'Buyer Name', 'Buyer Email', 'Speaker',
                     'Status', 'Package', 'Amount', 'Payment Intent ID']);
  }
  return sheet;
}

/* ═══════════════════════════ 2. GET SOLD ═══════════════════════════ */
function getSold_() {
  var sheet = getSheet_();
  var last = sheet.getLastRow();
  var sold = [];
  if (last > 1) {
    var rows = sheet.getRange(2, 4, last - 1, 2).getValues(); // Speaker, Status
    rows.forEach(function (r) {
      if (r[0] && String(r[1]).trim() === 'Paid') sold.push(String(r[0]).trim());
    });
  }
  return { ok: true, sold: sold };
}

/* ═══════════════════════════ 3. DELIVER ═══════════════════════════
 * Builds:  <DELIVERY_PARENT>/AIFOD-MEDIA-2026-<seq>_<Speaker>/
 *            ├─ Photos/  (entitled photos)
 *            └─ Videos/  (entitled videos)
 * then shares that per-order folder (view-only, link) and emails everyone.
 * Idempotent: if a folder for this piId already exists it is re-used and no
 * duplicate copies/emails are made.
 */
function deliver_(p) {
  var speaker   = String(p.speakerName || '').trim();
  var pkg       = String(p.pkg || '').trim();
  var buyerName = String(p.buyerName || '').trim();
  var buyerEmail= String(p.buyerEmail || '').trim();
  var piId      = String(p.piId || '').trim();
  var selection = String(p.selection || '')
                    .split(',').map(function (s) { return s.trim(); })
                    .filter(function (s) { return s; });

  var ent = PACKAGES[pkg];
  if (!ent) return { ok: false, error: 'Unknown package: ' + pkg };

  if (!CONFIG.DELIVERY_PARENT_ID || CONFIG.DELIVERY_PARENT_ID.indexOf('PASTE') === 0) {
    notifyTeam_('⚠️ Delivery not configured',
      'A paid order for ' + speaker + ' (' + pkg + ') is ready to deliver but ' +
      'DELIVERY_PARENT_ID is not set in the Apps Script config.\nBuyer: ' +
      buyerName + ' <' + buyerEmail + '>');
    return { ok: false, error: 'DELIVERY_PARENT_ID not configured' };
  }

  var sourceId = SPEAKER_FOLDERS[speaker];
  if (!sourceId && (ent.photos === 'all' || ent.photos === 'limited' || ent.video)) {
    notifyTeam_('⚠️ Missing speaker folder',
      'Paid order for "' + speaker + '" (' + pkg + ') cannot be delivered — no ' +
      'source folder mapped in SPEAKER_FOLDERS.\nBuyer: ' + buyerName +
      ' <' + buyerEmail + '>');
    return { ok: false, error: 'No source folder for speaker: ' + speaker };
  }

  var parent = DriveApp.getFolderById(CONFIG.DELIVERY_PARENT_ID);

  // ── Idempotency: one order folder per piId (tagged in the description) ──
  var existing = findOrderFolder_(parent, piId);
  if (existing) {
    return { ok: true, alreadyDelivered: true,
             folderUrl: existing.getUrl(), folderId: existing.getId() };
  }

  var seq = nextOrderNo_();
  var orderName = 'AIFOD-MEDIA-2026-' + seq + '_' + safeName_(speaker);
  var orderFolder = parent.createFolder(orderName);
  orderFolder.setDescription('piId=' + piId + '|pkg=' + pkg + '|buyer=' + buyerEmail);

  var counts = { photos: 0, videos: 0 };

  if (ent.photos !== 'none' || ent.video) {
    var src = DriveApp.getFolderById(sourceId);

    // Photos
    if (ent.photos === 'all') {
      var pf = orderFolder.createFolder('Photos');
      counts.photos = copyByType_(src, pf, 'image/');
    } else if (ent.photos === 'limited') {
      var pf2 = orderFolder.createFolder('Photos');
      counts.photos = copySelected_(selection, sourceId, pf2, ent.limit);
    }

    // Videos
    if (ent.video) {
      var vf = orderFolder.createFolder('Videos');
      counts.videos = copyByType_(src, vf, 'video/');
    }
  }

  // Share the per-order folder only (never the master).
  try {
    orderFolder.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  } catch (e) { /* domain policy may restrict; link still works for the team */ }

  var folderUrl = orderFolder.getUrl();

  if (buyerEmail) {
    sendDeliveryEmail_(buyerEmail, buyerName, speaker, pkg, folderUrl, counts);
  }
  notifyTeam_('✅ Delivery ready: ' + orderName,
    'Package : ' + pkg + '\nSpeaker : ' + speaker +
    '\nBuyer   : ' + buyerName + ' <' + buyerEmail + '>' +
    '\nPhotos  : ' + counts.photos + '\nVideos  : ' + counts.videos +
    '\nFolder  : ' + folderUrl + '\nOrder   : ' + orderName);

  return { ok: true, folderUrl: folderUrl, folderId: orderFolder.getId(),
           order: orderName, photos: counts.photos, videos: counts.videos };
}

/* ── copy every file of a mime prefix from src → dest ── */
function copyByType_(src, dest, mimePrefix) {
  var it = src.getFiles(), n = 0;
  while (it.hasNext()) {
    var f = it.next();
    if (String(f.getMimeType()).indexOf(mimePrefix) === 0) {
      f.makeCopy(f.getName(), dest);
      n++;
    }
  }
  return n;
}

/* ── copy only the selected IDs; SECURITY: each must live in the speaker's
 *    master folder (defence-in-depth — the PHP proxy checks this too) ── */
function copySelected_(ids, sourceId, dest, limit) {
  if (limit != null && ids.length > limit) ids = ids.slice(0, limit);
  var n = 0;
  ids.forEach(function (id) {
    try {
      var f = DriveApp.getFileById(id);
      if (!fileInFolder_(f, sourceId)) return;         // reject foreign IDs
      if (String(f.getMimeType()).indexOf('image/') !== 0) return;
      f.makeCopy(f.getName(), dest);
      n++;
    } catch (e) { /* skip unreadable / deleted id */ }
  });
  return n;
}

function fileInFolder_(file, folderId) {
  var parents = file.getParents();
  while (parents.hasNext()) {
    if (parents.next().getId() === folderId) return true;
  }
  return false;
}

function findOrderFolder_(parent, piId) {
  if (!piId) return null;
  var it = parent.getFolders();
  while (it.hasNext()) {
    var f = it.next();
    if (String(f.getDescription() || '').indexOf('piId=' + piId) !== -1) return f;
  }
  return null;
}

function nextOrderNo_() {
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var props = PropertiesService.getScriptProperties();
    var n = parseInt(props.getProperty('orderSeq') || '100', 10) + 1;
    props.setProperty('orderSeq', String(n));
    return ('00000' + n).slice(-5);   // zero-pad to 5 → 00127
  } finally {
    lock.releaseLock();
  }
}

function safeName_(s) {
  return String(s).replace(/[\\\/:*?"<>|]+/g, ' ').replace(/\s+/g, '_').trim();
}

/* ═══════════════════════════ EMAILS ═══════════════════════════ */
function sendDeliveryEmail_(to, name, speaker, pkg, link, counts) {
  var bits = [];
  if (counts.photos) bits.push(counts.photos + ' photo' + (counts.photos === 1 ? '' : 's'));
  if (counts.videos) bits.push(counts.videos + ' video' + (counts.videos === 1 ? '' : 's'));
  var summary = bits.join(' + ') || 'your media';

  var html =
    '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;">' +
      '<div style="background:#0d1b3a;padding:28px 24px;border-radius:12px 12px 0 0;text-align:center;">' +
        '<div style="color:#fff;font-size:20px;font-weight:800;letter-spacing:-.02em;">AIFOD Geneva Summit 2026</div>' +
        '<div style="color:#ff2f6b;font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin-top:6px;">Your media is ready</div>' +
      '</div>' +
      '<div style="border:1px solid #eee;border-top:0;border-radius:0 0 12px 12px;padding:28px 24px;">' +
        '<p style="font-size:15px;line-height:1.6;margin:0 0 14px;">Hi ' + esc_(name || 'there') + ',</p>' +
        '<p style="font-size:14px;line-height:1.7;margin:0 0 18px;">Your <strong>' + esc_(pkg) +
          '</strong> for <strong>' + esc_(speaker) + '</strong> is ready. It contains <strong>' +
          esc_(summary) + '</strong>.</p>' +
        '<p style="text-align:center;margin:26px 0;">' +
          '<a href="' + link + '" style="display:inline-block;background:#E21E51;color:#fff;text-decoration:none;' +
          'font-size:15px;font-weight:700;padding:14px 32px;border-radius:8px;">Open your media folder</a>' +
        '</p>' +
        '<p style="font-size:12px;color:#6b7280;line-height:1.6;margin:0 0 4px;">Or paste this link into your browser:<br>' +
          '<a href="' + link + '" style="color:#E21E51;word-break:break-all;">' + link + '</a></p>' +
        '<p style="font-size:12px;color:#9ca3af;line-height:1.6;margin:18px 0 0;">The files are yours to download and keep. ' +
          'If the link does not open, reply to this email and we will help.</p>' +
      '</div>' +
      '<p style="text-align:center;font-size:11px;color:#9ca3af;margin:16px 0 0;">AIFOD · Palais des Nations, Geneva</p>' +
    '</div>';

  MailApp.sendEmail({
    to: to,
    cc: CONFIG.NOTIFY.join(','),
    name: CONFIG.FROM_NAME,
    subject: 'Your ' + pkg + ' is ready — AIFOD Geneva Summit 2026',
    htmlBody: html,
    body: 'Hi ' + (name || 'there') + ',\n\nYour ' + pkg + ' for ' + speaker +
          ' is ready (' + summary + ').\n\nOpen your media folder: ' + link +
          '\n\nAIFOD Geneva Summit 2026'
  });
}

function notifyTeam_(subject, body) {
  try {
    MailApp.sendEmail({
      to: CONFIG.NOTIFY.join(','),
      name: CONFIG.FROM_NAME,
      subject: subject,
      body: body
    });
  } catch (e) { /* best-effort */ }
}

function esc_(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ═══════════════════════════ TEST HELPERS ═══════════════════════════
 * Run testDelivery_ manually from the editor (Run ▸ testDelivery_) once you've
 * set DELIVERY_PARENT_ID and a real speaker folder, to confirm copy + email
 * work end-to-end. Uses a fake piId so it's easy to delete afterwards.
 */
function testDelivery_() {
  var out = deliver_({
    action: 'deliver',
    speakerName: 'Tianze Zhang',
    pkg: 'All Photos Super XL',
    buyerName: 'Test Buyer',
    buyerEmail: Session.getActiveUser().getEmail(),
    piId: 'pi_test_' + Date.now()
  });
  Logger.log(JSON.stringify(out, null, 2));
}
