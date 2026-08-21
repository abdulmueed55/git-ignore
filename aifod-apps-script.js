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
  DELIVER_BY: '25 August 2026',
  ADMIN_KEY:  'aifod2026'                      // guards the clearOrders reset
};

/* ── SPEAKER → SOURCE (MASTER) DRIVE FOLDER ─────────────────────────────────
 * Each speaker's master folder holds ALL their photos + videos.
 * We only ever COPY out of these — the master is never shared or moved.
 * Keep this list in sync with stripe-proxy.php speaker_folders() and the
 * AP_FOLDERS map in the sales-page HTML.
 * TODO: add every speaker as  'Speaker Name': 'DRIVE_FOLDER_ID'
 */
var SPEAKER_FOLDERS = {
  'Test Speaker': '1j_MWqd6dS7qhAyReOWq-K2swFt4FRj7U',
  'Tianze Zhang': '1VF32ITyqbhFDPDRDqQORBys0mXyFtjZ6',
  'Chet Greene': '1I4gHEdPg-6TZrFadxUzqvFsiPZO4d1KO',
  'Tšiu Khathibe': '1fOhPqeFXqatfCgtZsuLQZuc-kxEP7FGz',
  'Muhammadou M.O. Kah': '1JOO13OIyJcGHDpOueeNpNyxpEX-iWw72',
  'Labeeqah Schuurman': '1Ej9dG7twgDQdvYu9imBHQxfzEzNkEymG',
  'Kiyoshi Adachi': '17BYsmIhQx5b9R_MC1TZEzNivG7ERM-Du',
  'Gladwin Mendez': '1e9wDiqhMvQWfq2q7_UEHklMNuY5wh0BH',
  'Sundara Rajan Aravamuthan': '1SpOGbSfBYz78n8tvVwBuzoSEUkyC2pui',
  'Shiva Kumar': '10bsh-LAQBMC0NAPIZqxs7RfGcMUbH7aU',
  'Dimitri Boylan': '1KJvcXn03fMRZlwKqinWIa4I8CiAbAJnt',
  'Giorgio Bortoli': '1-kSgas39Y4xKUzS-eJZ7mO_MLg8LFVT7',
  'Susana Falcão': '1h4ho4GSqiYq357Z3KXszVo4S1TsLCO4o',
  'Tola Yusuf': '11KltZY4WnRImAcq8NLJLZKuP8m5f4QD_',
  'Hesti Aryani': '1jCvpUYEulkqlpSOkPfsThUOD_tYiJ1Qf',
  'Ivana Brutenic': '1vg8K1yB4CqjKK8sjLNEuOQ3Crzw7BnmJ',
  'Hyunwoo Kim': '1ubrdtW2cZuZcTFXnq5UPp5P79PVbtm7Q',
  'Anand Tatavarthi': '1meHEHBckUWpiNlsC8fRDL-q1Djhpe53h',
  'Kunal Panchamia': '1U3J7x5xmr1c76dqkoHzgnTKCGCO9znPf',
  'Pascal Hetzscholdt': '12fNTE573H4lb3zNstPXkhK7yxSieJ_Fe',
  'Abasiama Idaresit': '1a12Ztja663DvrMCfh5MMTYU7_RlC3oEe',
  'Jean-Simon Venne': '1XgM3GAH5QPjGOa9nisWxySDKq2KfcVRc',
  'Boris Grivič': '1REfRDmrvO1_xsGAof3nWRSYTqyZ1bxrw',
  'Andrei Dumitrica': '1EOQPZjUIMu23MM7D-AMraFYGYlhl3aM5',
  'Nagesh Sharma': '1eFi-j7F8YLlMKxi3gjIbHCS7xAxWhm4k',
  'Erin Black': '1wkmLy-XE_UXxnyLXO3Mw8o8RYfwUr4sC',
  'Robert Hortschitz': '1LAXbIkUAbP3Q17PjEWWm02KdLxKnTzos',
  'Patricia Krall': '1-UHqq3ftFd9NWw8_n8ns7Z7qYV11uYcY',
  'Valerie Pelton': '1GNu_g0z7r_l2Qov29W59dItWcUTIBcPm',
  'Veronika Stoka': '1CWMAW35Mp6GEO_ajBkyhFujVuyY7yHTf',
  'Alexander Inchbald': '1iOEgx9wfoJIsJOVmt30ImdTrGWnR_qRl',
  'Adina Suciu': '14PqbLyQv0qyIN49KEPpeCeG2O5d4wc1S',
  'Mikael Loefstrand': '1b62aiqJ01WdNuVumzyjv4Iu8l7mP0Ixd',
  'Craig Gibbs': '1yVgYAwX4EWd9PkiUw0FJ8QLgp2mgu29n',
  'Vuk Vegezzi': '1D-yHPPxlQrg16E6zhP-zav79slqmyQzH',
  'Alejandro Castañeira': '1sQ--vqsYH7KCZXglNs0tP-2iB3CkUWb4',
  'Nelson T. Ajulo': '1VJiMAzGsrCDbeMH6NfBhUSn4qg9uc_gu',
  'Cyrille C. Catel': '1Kh5oTsUpMykqoQuhbxbsUuJK1IYIDyhe',
  'Bernardo Cartoni': '1NO2xH4uJfNAyP788BVGG6gj8Mt8NNYsV',
  'Michael Che Bugembe': '1QAxBMJof-7YWnGpwm8zr1Z86g2rhelre',
  'Rory Macmillan': '1rma31J_TUNmk_datgcmgaKVQQM6ZP7wu',
  'Guillaume Lamothe': '1y1WfMY_J2vXLel7kxSbNpmeCczaFKyZU',
  'Joy-Marie King': '1pSWRCAbaSa5u7d4hgIPGCEMXQpSMtzoK',
  'Patrick Vlačič': '1dgkNu6_i_ZwyMLvpCKYUVO1yj8nBdKHx',
  'Sheila Jagannathan': '1-g_8ya5Zd1YzknS5csHMthcwzi3vU18Q',
  'Frederic Emane': '1-O_gNzOfgOePgTOBPlMugqHQvObNCa4X',
  'Rene Kok': '1vkUjAaZhK23J1zoSfPHhy_70rTS3waTp',
  'Kashif Ul Haq': '1rJ244ijAcXgXesaWJ8CPX1dXlLEOUfDE',
  'Oliver Ropke': '1sEYWDQOUlwiTqIoBsvi37OzDwIyqTwEj',
  'Chirag Dhirajlal Lakhani': '1FnZu6QoIBiDHRuXcla3IY7rxIduqjM9H',
  'Joanne Sweeney': '1nG_1TUckC5N8pmZgtEnoT4ewMPd_6OGL',
  'John Hemery': '1WJkObWieZR-gFk-Jwzbwi42TJEHigCeN',
  'Marybelle Cherfan': '1BLEriugIP_YLCETwR1tE2EP7vbOyVldV',
  'Alexandria Cogdill': '1NX5bDjlumW9mJfP2n5u5IXWJmvDZaCqq',
  'Jon-Hans Coetzer': '1wtqG_y6u8V5LUlGEIUDlrU_kKSFIWSRx',
  'Ivica Srncevic': '1dG1Ss2ewJRdpGnOHKU11QpKarbj2aBZw',
  'Vibhav Mithal': '17LNGOG2i2RM-fMShocRAI5SwINOVwpd2',
  'Georg Zangl': '1y8XQqRvmpDQfZ_fP7q0s3g4xjUEyoWp8',
  'Manohar Kosuru': '1xm8fnSmHiY4H9tkIQ5ygafupQB-YFKt1',
  'Sakthikumar Ramachandran': '1K8xTLzaC9n2OKx8bCCBhTTlY-cHe8UtZ',
  'Vinod Sood': '1T3xr_NAZmrawsroqwzHrkLMMKEBKHatF',
  'Fredrik Kocon': '1kZ8WUdgIvmtVJ5PlVzhnshdl_zQQLsEj',
  'Elżbieta Deja': '1fVUT7rTW-LZO0R3N6BccVyZg3ZfbYw6z',
  'Rosalie Palmer': '14CmII1viKi0NiK69kBHPkO8blWQKL0xi',
  'Deniz Şerifoğlu': '1NhSqt9IopGzcL_ef4EOt0X9twjF8foTG',
  'Kenechi Okeleke': '1K9egsl9cv2KJTlRjoDfmPDErTQlKMThs',
  'Danielle Brault': '1DsYxPcApjR--MA1BdbSqGCKLphLPBRWc',
  'Jeremy Stein': '15gDY1sIn-ty3k5vD66HpIq--xVrMNRpr',
  'Derrick Davis': '1CKt5lJaOJlR_kYcGse8z7hKDj1Rl8RkZ',
  'Dorian Vanhorenbeeck': '1oWMvn9xwOtuwVIQGboxla49LT9NTZM3J',
  'Daniel Ojdanic': '1rAYkUIC5SRQ0QdHTs-7hKWDEEAt7Gefb',
  'David Williams': '1JphI7m7llxJ2t7aEPh9rf9Hx0qAIfhvZ',
  'Eric Famanas': '1Uwdn4RJFgFpnPe1suVA_9VJB76wvEYtK',
  'Nobuyuki Ota': '1OUoLWjhQmG8Jo0mz1zhPEL8TpRzctbS6',
  'Tan James Anthony': '16aPPnZdebl-QdiJJE_PB3UoehxtNBp8G',
  'Sönke Lund': '1Ml0ckn0auetzWzFmH4PBlDQvBDGHTEsn',
  'Sven Soomuste': '1NJA-KAYNNu5tZYs9rDdij_Z3lUx3AUzV',
  'Ruth Gafni': '1bcNayw2HMWscDBBZ0R1iIC6RWmHbcETN',
  'Flavio Bordignon': '1_UCT-VTTcctGqmCBZIS1IB6C8HmfroTo',
  'Pavlo Yalovol': '1nrM9_LSDiYJXQUCa3Aq6DpFj4jmBg03G',
  'Shikhin Agarwal': '14VmsWzUsayJH81RXu305toY4KZcq5pnH',
  'Marisa Boller': '11ueITkok40LoqF3yVfeyCScLCZtozieq',
  'Muhammad Jawad Ali': '1C8Hx7S5-rgPIQceRhDrBtmye8cDkToy0',
  'Bruce Mellado': '1McNUxUmu-6VpeZ6dC6xd9TZYqMpvkyzo',
  'Dip Nandi': '1LKYfmJa4HSMzWgLbhhpEXWQeBgLjIDDV',
  'Ezekiel Barclay Pajibo': '10q1_4ZtjbFweQEkfqXVtwyYzpIMTwuys',
  'Daniel Osuna': '1wZQTcGQSHV1cH4Yu7HRl63rQX1u5Wn6L',
  'Helen Pino Vera': '1bD1KMBY4J0VQqBBMk_3ZUNJpFvbF5EU4',
  'Oyedayo Otokiti': '12QfMVOwcHGD2bQGn_Q1TLGKbOkLFvE1r',
  'Frédéric Jallat': '10CDAHMm-XNYloSyZ-GLMGxj1Sb12YgdF',
  'Nathalie Simille': '12I1sNwHAp4a3ADCZv51jur5OJyM4yCP4',
  'Orsolya Kneitner': '18An29c0vrtsPM56ItKH2jsn2dfKUBUe4',
  'Konrad Sztolc': '1YUs6rhRlcMljMPmGkpLt3o4T4qnfLXro',
  'Fabrizio Degni': '1QGFC3tAebjem5PgM3PQloDq6lH0wnVW5',
  'Rahul Ghatalia': '1uEjgjafig9qc7NPStORebN5H5jM2oPBP',
  'Fiorenzo Manganiello': '1Iu5uuGkE7qtJQUwIhWQ7a9Vb8lscOY2m',
  'Stephen Cornish': '1ajTmxVpee_Uc3Q9x_f63zjtmojSIpGYk',
  'Raja Gopalan Balasubramanyan': '12cpgbJI-R-MizOZqLan64t31hexR2-Gq',
  'Rui Alexandre Castanho': '1LAhFWjmPmEfOkyAbCdM0TYSdoS5tZ_RB',
  'Peter Ruggle': '1pXTdHd7BpZSxAa-y6zGEOgF1cJJ0zSB3',
  'Frederick Tschernutter': '1DxxyBQC__Naz5Mz8ug4aeiH52_ec1wR6',
  'Sreeraj PM': '1v-2FSa04jHEAmWyA7a9RCycXtd8Y4Bnb',
  'Philip Leaper': '1v6AAd9CoHKEJcqkUJ-YWyrIihQtq3TfO',
  'Tijn van der Zant': '1VjKQReqZJUyIjJxx9MkzAY3VuV3C1T6q',
  'Wolfgang Pinegger': '128rQksdJOzppB13Fc7xwWj_sdnQpp0-E',
  'Yogesh Gupta': '121z85HFDEJCXNBpzLO7JFIAWKi3AvyYz',
  'Wilhelm Loderer': '1l87jGNOE5Og-3xgiPFqTOi8vVLcu8Cwe'
};

/* ── SPEAKER → VIDEO DRIVE FOLDER ─────────────────────────────────────────────
 * Separate collection from SPEAKER_FOLDERS above — videos live in their own
 * per-speaker folder tree (Siphiwe's collection), not mixed in with photos.
 * Keep in sync with speaker_video_folders() in stripe-proxy.php and
 * AP_VIDEO_FOLDERS in the sales-page HTML. Run ▸ listVideoFolders to get the
 * full name -> ID list from that collection.
 */
var SPEAKER_VIDEO_FOLDERS = {
  'Ayu Indirawanty': '1D51va7qxHVv_ayq0T6c38hvSuI_fwmg5',
  'Abasiama Idaresit': '15-N1qoiCq1hiQeOhw-U9sskLL-2gXznZ',
  'Adina Suciu': '1LJH3gkDfMQlWfspY5ia4HqE1TZGkigSI',
  'Alejandro Castañeira': '1hFG8d3LsfWyAL9mp_JiIOMPmQp_3hOVH',
  'Alexander Inchbald': '1PflfI9fAese37bEoGcplJw1WDvqIAPDn',
  'Alexandria Cogdill': '1b4l7yFf1TYxTXy2Pc7intaxOJBohuagt',
  'Anand Tatavarthi': '1aWGYCBhCaMSm5wOqIQ5P7oAm3AclVLdl',
  'Bernardo Cartoni': '1xvdaOLKvxhsxZa-ykN-ozo4PHZylH0Qv',
  'Craig Gibbs': '1A86ZG0QZCV_G8rXvNHqtlj8gx76ZmCIU',
  'Deniz Şerifoğlu': '1jhUmR9kuExp69C10iK5-FPMdHl8LbwGv',
  'Boris Grivič': '1zfc7rXYSg4ruAAtVPWedTfcYpznA46sR',
  'Bruce Mellado': '1dLjreAqiM1rdDRCqAndxgS2Ej-0732qC',
  'Chirag Dhirajlal Lakhani': '1fYXy_DHboEXLF78iQTd9n2GsBtAWHE7T',
  'Daniel Ojdanic': '11uXms5LllH9HRSq8MF8sp_e4acSNDQQ7',
  'Daniel Osuna': '1nE9Aq4wGtTMRSMC5aYCY5862bHUhzicd',
  'Danielle Brault': '1XKz79MaGOP7lEEELUry7YZ5bXBRBd0JO',
  'Dip Nandi': '1wxv711N1y-ymiXVZg7FMRrL01lKLMjoO',
  'Dorian Vanhorenbeeck': '1RdrdmsugiwNjwcciNMcFcC4PMXv0FOBz',
  'Erin Black': '1AVGxXwbBXzbGun2j4aapXUlKJGJW4Ab_',
  'Flavio Bordignon': '1fVEbqioBt0NjQH6ZiX2p8z-iSE21CP0B',
  'Frederick Tschernutter': '1s8Xig6VxMep8Td4paz0s_r9nUKGG_ozq',
  'Georg Zangl': '1d7ZoSpBwh0KSp3SqKo8iVkjMLvFexEIe',
  'Guillaume Lamothe': '1Fnr-ZFXhNDf3cmuQIXnRBZVUr4R9Bc6J',
  'Tšiu Khathibe': '1TQnMcKH5_9bqS9LeatQjGdkACGGsIO8i',
  'Hyunwoo Kim': '1PG4_Cj_Evy-5cSA1CcFjXnmH4-Qyvaz-',
  'Jean-Simon Venne': '1PjBmqzmk_NHAvXNMJhsrw6QWj5aTz0Yr',
  'Jeremy Stein': '1Kg18h6_gsf1fQCl3eEUC8r3JG8hfLL1U',
  'John Hemery': '1FT-EcDVVGFc667cGDK0iMFe5Q8iC78CD',
  'Jon-Hans Coetzer': '1Hz1Ply9aOkPO-JvnEXa04myymA0uKQtm',
  'Joy-Marie King': '1ZmFxGGylGNLpp2lu_7nDamCNsariYyJk',
  'Chet Greene': '1dI5632WTsEqvPTNPzfn2gyo-ghzSHYIu',
  'Jon Ray': '1XkWgkZvGAOHUP1ecKgT_8C_yaLdriYg_',
  'David Williams': '1-g-Wva4H0PnTSqUorfuQVO4Sria_1YJS',
  'Derrick Davis': '1syjgbFr8TwdteoLGa_3P8Va6Vlt8VmIt',
  'Dimitri Boylan': '1Mg-nBcIW1TXl6Xdw3cvVUTaGQR_fumvW',
  'Elżbieta Deja': '1OqrnhiAF8Q7_OSHqaTTH-hrh87zF9jDa',
  'Eric Famanas': '1fQ9BAPq_T2Fv4y2ZnSr7Hw17OgpGEg04',
  'Ezekiel Barclay Pajibo': '1GAfcRRL5H7siLuPO_UNw1Xc2f_DvPN4d',
  'Fabrizio Degni': '1nMMoU12Y1PLKjSJ_41FJVtudxVYs88IN',
  'Frederic Emane': '1n4ca6xtdCT_WFSuNlAqwEQq6Vy9O6ahx',
  'Frédéric Jallat': '1epYzKtFFB3OaNoL_0ixNiZdlvOoUc-1M',
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
    if (action === 'sendGalleryLink') return json_(sendGalleryLink_(p));
    if (action === 'clearOrders') return json_(clearOrders_(p));
    if (action === 'clearDeliveries') return json_(clearDeliveries_(p));
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
  /* Use the named tab if it exists, otherwise the existing first sheet — so we
   * record into and clear the SAME sheet the orders already live in (never
   * spawn a second, orphaned tab). */
  var sheet = ss.getSheetByName(CONFIG.SHEET_TAB) || ss.getSheets()[0];
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(['Timestamp', 'Buyer Name', 'Buyer Email', 'Speaker',
                     'Status', 'Package', 'Amount', 'Payment Intent ID']);
  }
  return sheet;
}

/* ═══════════════════════════ RESET (clear the sheet) ═══════════════════════════
 * Wipes all order rows (keeps the header). Guarded by ADMIN_KEY. Called by the
 * PHP resetOrders admin URL during testing.
 */
function clearOrders_(p) {
  if (String(p.key || '') !== CONFIG.ADMIN_KEY) return { ok: false, error: 'Bad key' };
  var sheet = getSheet_();
  var last = sheet.getLastRow();
  if (last > 1) sheet.deleteRows(2, last - 1);
  return { ok: true, cleared: Math.max(0, last - 1) };
}

/* Trashes every per-order delivery folder under DELIVERY_PARENT_ID (reversible —
 * moves to Drive trash, does not permanently delete). Never touches speaker
 * master folders. Guarded by ADMIN_KEY, same as clearOrders_. */
function clearDeliveries_(p) {
  if (String(p.key || '') !== CONFIG.ADMIN_KEY) return { ok: false, error: 'Bad key' };
  if (!CONFIG.DELIVERY_PARENT_ID || CONFIG.DELIVERY_PARENT_ID.indexOf('PASTE') === 0) {
    return { ok: true, cleared: 0 };
  }
  var parent = DriveApp.getFolderById(CONFIG.DELIVERY_PARENT_ID);
  var it = parent.getFolders();
  var n = 0;
  while (it.hasNext()) {
    it.next().setTrashed(true);
    n++;
  }
  return { ok: true, cleared: n };
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

  var sourceId      = SPEAKER_FOLDERS[speaker];        // photos
  var videoSourceId = SPEAKER_VIDEO_FOLDERS[speaker];  // videos — separate collection
  var needsPhotos = (ent.photos === 'all' || ent.photos === 'limited');
  var needsVideo  = ent.video;

  if (needsPhotos && !sourceId) {
    notifyTeam_('⚠️ Missing speaker photo folder',
      'Paid order for "' + speaker + '" (' + pkg + ') cannot be delivered — no ' +
      'photo folder mapped in SPEAKER_FOLDERS.\nBuyer: ' + buyerName +
      ' <' + buyerEmail + '>');
    return { ok: false, error: 'No photo source folder for speaker: ' + speaker };
  }
  if (needsVideo && !videoSourceId) {
    notifyTeam_('⚠️ Missing speaker video folder',
      'Paid order for "' + speaker + '" (' + pkg + ') cannot be delivered — no ' +
      'video folder mapped in SPEAKER_VIDEO_FOLDERS.\nBuyer: ' + buyerName +
      ' <' + buyerEmail + '>');
    return { ok: false, error: 'No video source folder for speaker: ' + speaker };
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

  if (needsPhotos) {
    var src = DriveApp.getFolderById(sourceId);
    if (ent.photos === 'all') {
      var pf = orderFolder.createFolder('Photos');
      counts.photos = copyByType_(src, pf, 'image/');
    } else if (ent.photos === 'limited') {
      var pf2 = orderFolder.createFolder('Photos');
      counts.photos = copySelected_(selection, sourceId, pf2, ent.limit);
    }
  }

  if (needsVideo) {
    var vsrc = DriveApp.getFolderById(videoSourceId);
    var vf = orderFolder.createFolder('Videos');
    counts.videos = copyByType_(vsrc, vf, 'video/');
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
  var n = 0;
  var it = src.getFiles();
  while (it.hasNext()) {
    var f = it.next();
    if (String(f.getMimeType()).indexOf(mimePrefix) === 0) {
      f.makeCopy(f.getName(), dest);
      n++;
    }
  }
  /* Some speaker folders keep their media inside one extra numbered
   * subfolder instead of directly (e.g. a "61" folder) — recurse one level
   * so those aren't silently delivered as empty. */
  var subIt = src.getFolders();
  while (subIt.hasNext()) {
    var subFiles = subIt.next().getFiles();
    while (subFiles.hasNext()) {
      var sf = subFiles.next();
      if (String(sf.getMimeType()).indexOf(mimePrefix) === 0) {
        sf.makeCopy(sf.getName(), dest);
        n++;
      }
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
    var p = parents.next();
    if (p.getId() === folderId) return true;
    /* file lives one level deeper, inside a numbered subfolder under folderId */
    var grandparents = p.getParents();
    while (grandparents.hasNext()) {
      if (grandparents.next().getId() === folderId) return true;
    }
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

/* ── Purchase email: send the buyer their secure gallery link ── */
function sendGalleryLink_(p) {
  var to = String(p.buyerEmail || '').trim();
  if (!to) return { ok: false, error: 'No buyerEmail' };
  var name    = String(p.buyerName || '').trim();
  var speaker = String(p.speakerName || '').trim();
  var pkg     = String(p.pkg || '').trim();
  var url     = String(p.galleryUrl || '').trim();
  var ent     = PACKAGES[pkg] || {};

  var actionLine = (ent.limit != null)
    ? 'Open your gallery to choose your ' + ent.limit + ' photos, then confirm.'
    : 'Open your gallery to review your media, then confirm to receive it.';

  var html =
    '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;">' +
      '<div style="background:#0d1b3a;padding:28px 24px;border-radius:12px 12px 0 0;text-align:center;">' +
        '<div style="color:#fff;font-size:20px;font-weight:800;">AIFOD Geneva Summit 2026</div>' +
        '<div style="color:#ff2f6b;font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin-top:6px;">Payment received</div>' +
      '</div>' +
      '<div style="border:1px solid #eee;border-top:0;border-radius:0 0 12px 12px;padding:28px 24px;">' +
        '<p style="font-size:15px;line-height:1.6;margin:0 0 14px;">Hi ' + esc_(name || 'there') + ',</p>' +
        '<p style="font-size:14px;line-height:1.7;margin:0 0 18px;">Thank you for your <strong>' + esc_(pkg) +
          '</strong> for <strong>' + esc_(speaker) + '</strong>. ' + esc_(actionLine) + '</p>' +
        '<p style="text-align:center;margin:26px 0;">' +
          '<a href="' + url + '" style="display:inline-block;background:#E21E51;color:#fff;text-decoration:none;' +
          'font-size:15px;font-weight:700;padding:14px 32px;border-radius:8px;">Open my gallery</a>' +
        '</p>' +
        '<p style="font-size:12px;color:#6b7280;line-height:1.6;margin:0 0 4px;">Or paste this link into your browser:<br>' +
          '<a href="' + url + '" style="color:#E21E51;word-break:break-all;">' + url + '</a></p>' +
        '<p style="font-size:12px;color:#9ca3af;line-height:1.6;margin:18px 0 0;">After you confirm, we email your ' +
          'final high-resolution files (no watermark). Keep this link private — it is unique to your order.</p>' +
      '</div>' +
      '<p style="text-align:center;font-size:11px;color:#9ca3af;margin:16px 0 0;">AIFOD · Palais des Nations, Geneva</p>' +
    '</div>';

  MailApp.sendEmail({
    to: to,
    name: CONFIG.FROM_NAME,
    subject: 'Select your media — AIFOD Geneva Summit 2026',
    htmlBody: html,
    body: 'Hi ' + (name || 'there') + ',\n\nThank you for your ' + pkg + ' for ' + speaker + '.\n' +
          actionLine + '\n\nOpen your gallery: ' + url +
          '\n\nAfter you confirm, we email your final high-resolution files (no watermark).\n\nAIFOD Geneva Summit 2026'
  });
  return { ok: true };
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
 * Run testDelivery_ manually from the editor (Run ▸ testDelivery) once you've
 * set DELIVERY_PARENT_ID and a real speaker folder, to confirm copy + email
 * work end-to-end. Uses a fake piId so it's easy to delete afterwards.
 */
/* Run this once (Run ▸ listMasterFolders) to get every speaker folder name +
 * ID from the master photo collection, ready to paste into SPEAKER_FOLDERS
 * here, speaker_folders() in stripe-proxy.php, and AP_FOLDERS in the HTML.
 * View the output in Executions (View ▸ Executions ▸ click this run ▸ Logs).
 */
function listMasterFolders() {
  var MASTER_ID = '1qoC8gjSQknjOIiRxGjaqWaLv9FN36Od3'; // the shared master PHOTO folder
  var parent = DriveApp.getFolderById(MASTER_ID);
  var it = parent.getFolders();
  var rows = [];
  while (it.hasNext()) {
    var f = it.next();
    rows.push({ name: f.getName(), id: f.getId() });
  }
  rows.sort(function (a, b) { return a.name.localeCompare(b.name); });

  Logger.log('Found ' + rows.length + ' folders:\n');
  rows.forEach(function (r) {
    // Strip the leading "001 " / "001_" numbering to get just the speaker name
    var speaker = r.name.replace(/^\d+[\s_]+/, '');
    Logger.log("  '" + speaker + "': '" + r.id + "',");
  });
}

/* Same as listMasterFolders but for Siphiwe's VIDEO collection (separate
 * folder tree from the photo one — each speaker's video folder lives here).
 * Run ▸ listVideoFolders, then View ▸ Executions ▸ open the run ▸ Logs.
 */
function listVideoFolders() {
  var MASTER_ID = '11DVsziW0hM_6jJL82OZKCMjRFVjl3DQm'; // the shared master VIDEO folder
  var parent = DriveApp.getFolderById(MASTER_ID);
  var it = parent.getFolders();
  var rows = [];
  while (it.hasNext()) {
    var f = it.next();
    rows.push({ name: f.getName(), id: f.getId() });
  }
  rows.sort(function (a, b) { return a.name.localeCompare(b.name); });

  Logger.log('Found ' + rows.length + ' video folders:\n');
  rows.forEach(function (r) {
    // Strip the leading "018_" / "018 " numbering to get just the speaker name
    var speaker = r.name.replace(/^\d+[\s_]+/, '');
    Logger.log("  '" + speaker + "': '" + r.id + "',");
  });
}

function testDelivery() {
  var out = deliver_({
    action: 'deliver',
    speakerName: 'Test Speaker',
    pkg: 'All Photos Super XL',
    buyerName: 'Test Buyer',
    buyerEmail: Session.getActiveUser().getEmail(),
    piId: 'pi_test_' + Date.now()
  });
  Logger.log(JSON.stringify(out, null, 2));
}
