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
        'Cyrille C. Catel' => '1Kh5oTsUpMykqoQuhbxbsUuJK1IYIDyhe',
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
        'Oliver Ropke' => '1sEYWDQOUlwiTqIoBsvi37OzDwIyqTwEj',
        'Chirag Dhirajlal Lakhani' => '1FnZu6QoIBiDHRuXcla3IY7rxIduqjM9H',
        'Joanne Sweeney' => '1nG_1TUckC5N8pmZgtEnoT4ewMPd_6OGL',
        'John Hemery' => '1WJkObWieZR-gFk-Jwzbwi42TJEHigCeN',
        'Marybelle Cherfan' => '1BLEriugIP_YLCETwR1tE2EP7vbOyVldV',
        'Alexandria Cogdill' => '1NX5bDjlumW9mJfP2n5u5IXWJmvDZaCqq',
        'Jon-Hans Coetzer' => '1wtqG_y6u8V5LUlGEIUDlrU_kKSFIWSRx',
        'Ivica Srncevic' => '1dG1Ss2ewJRdpGnOHKU11QpKarbj2aBZw',
        'Vibhav Mithal' => '17LNGOG2i2RM-fMShocRAI5SwINOVwpd2',
        'Georg Zangl' => '1y8XQqRvmpDQfZ_fP7q0s3g4xjUEyoWp8',
        'Manohar Kosuru' => '1xm8fnSmHiY4H9tkIQ5ygafupQB-YFKt1',
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
            foreach ($lists[$videoId] ?? [] as $f) {
                if (strpos($f['mimeType'] ?? '', 'video/') === 0) $videos[] = $f['id'];
            }
            list_cache_put($cacheKey, ['images'=>$images,'videos'=>$videos]);
        }
    } elseif ($folderId) {
        foreach (drive_list($folderId) as $f) {
            $mime = $f['mimeType'] ?? '';
            if (strpos($mime, 'image/') === 0)      $images[] = $f['id'];
            elseif (strpos($mime, 'video/') === 0)  $videos[] = $f['id'];
        }
    } else {
        echo json_encode(['ok'=>false,'error'=>'No speaker or folderId']); exit;
    }

    echo json_encode(['ok'=>true,'images'=>$images,'videos'=>$videos]);
    exit;
}

/* ══ 5. Get Image Thumbnail ══ */
if ($action === 'getThumb') {
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId) { http_response_code(400); exit; }
    $small = ($_GET['size'] ?? '') === 'small';

    /* Disk cache — same file+size is requested over and over (sidebar
     * rebuilds, repeat gallery visits, multiple buyers of the same speaker).
     * Serving from disk skips Google Drive entirely and is what actually
     * fixes the "images take forever" slowness. */
    $cacheDir = __DIR__ . '/thumb-cache/';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheKey  = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileId) . ($small ? '_s' : '_l');
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
    foreach ($sizes as $sz) {
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

    /* Best-effort cache write — a failure here must never break the response. */
    @file_put_contents($cacheBin, $imgData);
    @file_put_contents($cacheType, $ctype);

    header('Content-Type: ' . $ctype);
    header('Cache-Control: public, max-age=604800, immutable');
    header('Access-Control-Allow-Origin: *');
    echo $imgData;
    exit;
}

/* ══ 6. Stream Video ══ */
if ($action === 'streamVideo') {
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId) { http_response_code(400); exit; }

    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&key=" . DRIVE_API_KEY;

    /* Forward the browser's Range request so <video> can seek and start
     * playback before the whole file arrives — without this, browsers get
     * an unseekable, all-or-nothing response and often refuse to play it. */
    $reqHeaders = ['User-Agent: Mozilla/5.0 AIFOD-Proxy/1.0'];
    if (!empty($_SERVER['HTTP_RANGE'])) $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];

    $statusCode   = 200;
    $contentType  = 'video/mp4';
    $contentLen   = null;
    $contentRange = null;
    $headersSent  = false;

    while (ob_get_level() > 0) ob_end_clean(); /* disable output buffering so flush() really streams */

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false, /* stream straight through instead of buffering the whole video in memory */
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 0, /* no cap on total transfer time — large videos stream progressively */
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $reqHeaders,
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$statusCode, &$contentType, &$contentLen, &$contentRange) {
            $t = trim($header);
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $t, $m))       $statusCode   = (int)$m[1];
            elseif (stripos($t, 'content-type:') === 0)          $contentType  = trim(substr($t, 13));
            elseif (stripos($t, 'content-length:') === 0)        $contentLen   = trim(substr($t, 15));
            elseif (stripos($t, 'content-range:') === 0)          $contentRange = trim(substr($t, 14));
            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$headersSent, &$statusCode, &$contentType, &$contentLen, &$contentRange) {
            if (!$headersSent) {
                $headersSent = true;
                http_response_code($statusCode === 206 ? 206 : 200);
                header('Content-Type: ' . $contentType);
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=86400');
                if ($contentLen)   header('Content-Length: ' . $contentLen);
                if ($contentRange) header('Content-Range: ' . $contentRange);
            }
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    if (!$headersSent) http_response_code(502);
    curl_close($ch);
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

/* ══ 8a. Clear the thumbnail + folder-listing cache (ADMIN) ══
 * Visit: stripe-proxy.php?action=clearThumbCache&key=aifod2026
 * Needed if a photo/video is replaced under the same file ID, or if new
 * media was just uploaded and you don't want to wait out the 10-min
 * folder-listing cache. */
if ($action === 'clearThumbCache' && ($_GET['key'] ?? '') === 'aifod2026') {
    $n = 0;
    foreach ([__DIR__ . '/thumb-cache/', __DIR__ . '/list-cache/'] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . '*') as $f) { @unlink($f); $n++; }
        }
    }
    echo json_encode(['ok'=>true,'cleared'=>$n]);
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
    $dir = __DIR__ . '/list-cache/';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . $key . '.json', json_encode($data));
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
