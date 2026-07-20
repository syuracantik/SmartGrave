<?php
// ============================================================
// admin_dashboard.php
// SmartGrave - Sistem Pengurusan Tanah Perkuburan
// ============================================================
session_start();
require_once 'db.php';

// Check if user is logged in and is admin / pentadbir
if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'pentadbir' && strtolower($_SESSION['role'] ?? '') !== 'admin')) {
    header("Location: login.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Admin';

// ---------------------------------------------------------------
// Tindakan POST: Lulus / Tolak / Assign Lot
// ---------------------------------------------------------------

$action = '';
$tempahan_id = 0;
$initial = !empty($nama_user) ? strtoupper(substr($nama_user, 0, 1)) : "A";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action']       ?? '';
    $tempahan_id  = (int)($_POST['tempahan_id'] ?? 0);
    $admin_id     = $_SESSION['user_id']   ?? null;

    if ($action === 'lulus' && $tempahan_id) {

    // Kemas kini status tempahan
    $stmt = $pdo->prepare("
        UPDATE tempahan
        SET status_proses = 'Lulus', updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$tempahan_id]);

    // Log aktiviti
    $stmt = $pdo->prepare("
        INSERT INTO log_aktiviti (user_id, aktiviti, ip_address)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $admin_id,
        "Meluluskan tempahan #$tempahan_id",
        $_SERVER['REMOTE_ADDR']
    ]);

    // Notifikasi waris
    $stmt = $pdo->prepare("SELECT user_id FROM tempahan WHERE id = ?");
    $stmt->execute([$tempahan_id]);
    $waris_id = $stmt->fetchColumn();

    if ($waris_id) {
        $stmt = $pdo->prepare("
            INSERT INTO notifikasi (user_id, mesej)
            VALUES (?, ?)
        ");

        $msg = "Permohonan tempahan #$tempahan_id anda telah DILULUSKAN dan sedang diproses untuk penetapan lot.";

        $stmt->execute([$waris_id, $msg]);
    }

    header("Location: susun_lot.php?tempahan_id=$tempahan_id");
    exit;
}

    if ($action === 'tolak' && $tempahan_id) {
        $sebab_pilihan = $_POST['sebab_pilihan'] ?? '';
        $sebab_lain    = trim($_POST['sebab_lain'] ?? '');
        $ulasan_final  = $sebab_pilihan === 'Lain-lain' ? $sebab_lain : $sebab_pilihan;

        // Ambil jumlah bayaran untuk proses auto-refund sebelum dipadam
        $stmt_get_pay = $pdo->prepare("SELECT jumlah FROM bayaran WHERE tempahan_id = ?");
        $stmt_get_pay->execute([$tempahan_id]);
        $jumlah_bayar = $stmt_get_pay->fetchColumn();
        
        $refund_note = "";
        $log_aktiviti_msg = "Menolak tempahan #$tempahan_id. Sebab: $ulasan_final";
        
        if ($jumlah_bayar && floatval($jumlah_bayar) > 0) {
            // Padam rekod bayaran (Simulasi Auto Refund)
            $stmt_del_pay = $pdo->prepare("DELETE FROM bayaran WHERE tempahan_id = ?");
            $stmt_del_pay->execute([$tempahan_id]);
            
            $refund_note = " Bayaran RM " . number_format($jumlah_bayar, 2) . " telah dikembalikan secara automatik (auto-refund) ke akaun anda.";
            $log_aktiviti_msg .= " (Yuran RM " . number_format($jumlah_bayar, 2) . " di-refund secara automatik)";
        }

        $stmt = $pdo->prepare("
            UPDATE tempahan
            SET status_proses = 'Tolak', ulasan_admin = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$ulasan_final, $tempahan_id]);

        // Log aktiviti
        $stmt = $pdo->prepare("
            INSERT INTO log_aktiviti (user_id, aktiviti, ip_address)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$admin_id, $log_aktiviti_msg, $_SERVER['REMOTE_ADDR']]);

        // Notifikasi waris
        $stmt = $pdo->prepare("SELECT user_id FROM tempahan WHERE id = ?");
        $stmt->execute([$tempahan_id]);
        $waris_id = $stmt->fetchColumn();
        if ($waris_id) {
            $stmt = $pdo->prepare("
                INSERT INTO notifikasi (user_id, mesej) VALUES (?, ?)
            ");
            $msg = "Permohonan tempahan #$tempahan_id anda telah DITOLAK. Sebab: $ulasan_final." . $refund_note;
            $stmt->execute([$waris_id, $msg]);
        }

        header("Location: admin_dashboard.php?berjaya=tolak&tempahan_id=$tempahan_id");
        exit;
    }
}


// ---------------------------------------------------------------
// Query Statistik
// ---------------------------------------------------------------
$stmt = $pdo->query("SELECT COUNT(*) FROM lot_pusara WHERE status_lot = 'Penuh'");
$lotPenuh = (int)$stmt->fetchColumn();

// Kapasiti keseluruhan kubur ialah 830
$lotJumlah = 830;
$lotTersedia = max(0, $lotJumlah - $lotPenuh);

$peratusGuna = round(($lotPenuh / $lotJumlah) * 100);

$stmt = $pdo->query("SELECT COUNT(*) FROM tempahan WHERE status_proses IN ('Pending', 'Bayaran Berjaya')");
$tempahanPending = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM daftar_khairat WHERE status_yuran = 'Dibayar'");
$ahliAktif = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) FROM bayaran
    WHERE EXTRACT(MONTH FROM tarikh_transaksi) = EXTRACT(MONTH FROM NOW())
    AND EXTRACT(YEAR FROM tarikh_transaksi) = EXTRACT(YEAR FROM NOW())
");
$jumlahBayaran = $stmt->fetchColumn();

// ---------------------------------------------------------------
// Senarai semua tempahan (dengan pagination mudah)
// ---------------------------------------------------------------
$filter = $_GET['filter'] ?? 'semua';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$whereClause = "WHERE NOT (t.status_proses = 'Tolak' AND t.updated_at < NOW() - INTERVAL '24 hours') AND NOT (t.status_proses = 'Lulus' AND lp.no_lot IS NOT NULL AND t.updated_at < NOW() - INTERVAL '24 hours')";
$params      = [];
if ($filter === 'pending') {
    $whereClause = "WHERE t.status_proses IN ('Pending', 'Bayaran Berjaya')";
} elseif ($filter === 'lulus') {
    $whereClause = "WHERE t.status_proses = 'Lulus' AND (lp.no_lot IS NULL OR t.updated_at >= NOW() - INTERVAL '24 hours')";
} elseif ($filter === 'tolak') {
    $whereClause = "WHERE t.status_proses = 'Tolak' AND t.updated_at >= NOW() - INTERVAL '24 hours'";
}

$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tempahan t
    JOIN maklumat_jenazah j ON j.id = t.jenazah_id
    LEFT JOIN lot_pusara lp ON lp.jenazah_id = j.id
    $whereClause
");
$stmtCount->execute($params);
$totalRows  = $stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.status_proses,
        t.tarikh_mohon,
        t.ulasan_admin,
        t.hub_waris,
        t.catatan,
        t.no_tel_alt,
        t.bukti_sijil,
        t.permit_polis,
        u.full_name    AS waris_nama,
        u.ic_number    AS waris_ic,
        u.no_telefon   AS waris_tel,
        u.email        AS waris_email,
        u.alamat       AS waris_alamat,
        j.id           AS jenazah_id,
        j.nama_jenazah,
        j.no_ic        AS jenazah_ic,
        j.jantina,
        j.tarikh_wafat,
        j.masa_wafat,
        j.lokasi_wafat,
        lp.no_lot      AS lot_ditetapkan
    FROM tempahan t
    JOIN users u ON u.id = t.user_id
    JOIN maklumat_jenazah j ON j.id = t.jenazah_id
    LEFT JOIN lot_pusara lp ON lp.jenazah_id = j.id
    $whereClause
    ORDER BY
        CASE t.status_proses WHEN 'Bayaran Berjaya' THEN 0 ELSE 1 END,
        t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$semuaTempahan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lot tersedia untuk dropdown assign
$stmtLot = $pdo->query("SELECT no_lot FROM lot_pusara WHERE status_lot = 'Tersedia' ORDER BY no_lot");
$lotTersediaList = $stmtLot->fetchAll(PDO::FETCH_COLUMN);

$title = "Dashboard Admin";
require_once 'header.php';
?>
    <style>
        :root {
            --font-display: 'Inter', sans-serif;
            --font-body   : 'Inter', sans-serif;
            --emerald-900 : #064e3b;
            --emerald-800 : #065f46;
            --emerald-700 : #047857;
            --emerald-600 : #059669;
            --emerald-500 : #10b981;
            --emerald-100 : #d1fae5;
            --emerald-50  : #ecfdf5;
            --slate-50    : #f8fafc;
            --slate-100   : #f1f5f9;
            --slate-200   : #e2e8f0;
            --slate-400   : #94a3b8;
            --slate-500   : #64748b;
            --slate-700   : #334155;
            --slate-900   : #0f172a;
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            min-height: 100vh;
            color: var(--slate-700);
            background-color: #f8fafc;
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
            position: relative;
            overflow-x: hidden;
        }

/* Glow background removed to match booking.php clean texture */

.main-content {
    flex: 1;
    padding: 2rem 2.5rem;
    overflow-x: hidden;
    position: relative;
}

        h1,h2,h3,h4,h5 { font-family: var(--font-display); }

        .dashboard-layout { display: flex; min-height: calc(100vh - 64px); }
        .main-content { flex: 1; padding: 2rem 2.5rem; overflow-x: hidden; }

        /* ── Stat Cards ── */
        .stat-card {
            background   : #fff;
            border-radius: 1.25rem;
            padding      : 1.5rem;
            border       : 1px solid var(--slate-200);
            position     : relative;
            overflow     : hidden;
            transition   : transform .2s ease, box-shadow .2s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(6,95,70,.10); }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 3px; border-radius: 1.25rem 1.25rem 0 0;
        }
        .stat-card.emerald::before { background: linear-gradient(90deg,#059669,#34d399); }
        .stat-card.blue::before    { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
        .stat-card.amber::before   { background: linear-gradient(90deg,#f59e0b,#fcd34d); }
        .stat-card.rose::before    { background: linear-gradient(90deg,#f43f5e,#fb7185); }

        .stat-icon { width:48px;height:48px;border-radius:.875rem;display:flex;align-items:center;justify-content:center;font-size:1.125rem; }
        .stat-icon.emerald { background:var(--emerald-50);color:var(--emerald-700); }
        .stat-icon.blue    { background:#eff6ff;color:#3b82f6; }
        .stat-icon.amber   { background:#fffbeb;color:#d97706; }
        .stat-icon.rose    { background:#fff1f2;color:#f43f5e; }

        .stat-value { font-family:var(--font-display);font-size:2rem;font-weight:800;color:var(--slate-900);line-height:1;margin:.5rem 0 .25rem; }
        .stat-label { font-size:.75rem;font-weight:600;color:var(--slate-500);text-transform:uppercase;letter-spacing:.06em; }

        .progress-bar { height:6px;border-radius:999px;background:var(--slate-100);overflow:hidden;margin-top:.875rem; }
        .progress-fill { height:100%;border-radius:999px;background:linear-gradient(90deg,#059669,#34d399);transition:width 1s cubic-bezier(.4,0,.2,1); }

        /* ── Panel / Table ── */
        .panel { background:#fff;border-radius:1.25rem;border:1px solid var(--slate-200);overflow:hidden; }
        .panel-header { padding:1.25rem 1.5rem;border-bottom:1px solid var(--slate-100);display:flex;align-items:center;justify-content:space-between; }
        .panel-title { font-family:var(--font-display);font-size:.9375rem;font-weight:700;color:var(--slate-900); }

        .data-table { width:100%;border-collapse:collapse; }
        .data-table th { font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--slate-500);padding:.75rem 1rem;border-bottom:1px solid var(--slate-100);text-align:left;white-space:nowrap; }
        .data-table td { padding:.875rem 1rem;font-size:.8125rem;color:var(--slate-700);border-bottom:1px solid var(--slate-50);vertical-align:middle; }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:var(--slate-50); }

        /* ── Badge ── */
        .badge { display:inline-flex;align-items:center;font-size:.6875rem;font-weight:700;padding:.25rem .625rem;border-radius:999px;gap:.3rem; }
        .badge-pending  { background:#fffbeb;color:#b45309; }
        .badge-lulus    { background:var(--emerald-50);color:var(--emerald-700); }
        .badge-tolak    { background:#fff1f2;color:#be123c; }
        .badge-bayaran  { background:#eff6ff;color:#1d4ed8; }

        /* ── Buttons ── */
        .btn-xs { font-size:.6875rem;font-weight:700;padding:.375rem .875rem;border-radius:.625rem;cursor:pointer;transition:.15s ease;border:none;display:inline-flex;align-items:center;gap:.3rem; }
        .btn-sm { font-size:.8125rem;font-weight:600;padding:.5rem 1.125rem;border-radius:.75rem;cursor:pointer;transition:.15s ease;border:none;display:inline-flex;align-items:center;gap:.4rem; }
        .btn-emerald { background:var(--emerald-800);color:#fff; }
        .btn-emerald:hover { background:var(--emerald-700); }
        .btn-ghost { background:var(--slate-100);color:var(--slate-700); }
        .btn-ghost:hover { background:var(--slate-200); }
        .btn-rose { background:#fee2e2;color:#be123c; }
        .btn-rose:hover { background:#fecaca; }
        .btn-green { background:var(--emerald-50);color:var(--emerald-700); }
        .btn-green:hover { background:var(--emerald-100); }

        /* ── Action icon buttons in table ── */
        .icon-btn { width:32px;height:32px;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;border:none;transition:.15s; }
        .icon-btn-view  { background:#f1f5f9;color:#475569; }
        .icon-btn-view:hover { background:#e2e8f0; }
        .icon-btn-lulus { background:var(--emerald-50);color:var(--emerald-700); }
        .icon-btn-lulus:hover { background:var(--emerald-100); }
        .icon-btn-tolak { background:#fff1f2;color:#be123c; }
        .icon-btn-tolak:hover { background:#fee2e2; }

        /* ── Filter tabs ── */
        .filter-tab { font-size:.75rem;font-weight:700;padding:.375rem .875rem;border-radius:.625rem;cursor:pointer;border:none;transition:.15s; }
        .filter-tab.active { background:var(--emerald-800);color:#fff; }
        .filter-tab:not(.active) { background:var(--slate-100);color:var(--slate-600); }
        .filter-tab:not(.active):hover { background:var(--slate-200); }

        /* ── Page heading ── */
        .page-heading { font-family:var(--font-display);font-size:1.75rem;font-weight:800;color:var(--slate-900); }

        /* ── Modal Overlay ── */
        .modal-overlay {
            position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(4px);
            z-index:900;display:none;align-items:center;justify-content:center;padding:1rem;
        }
        .modal-overlay.open { display:flex; }

        .modal-box {
            background:#fff;border-radius:1.5rem;width:100%;max-width:640px;
            max-height:90vh;overflow-y:auto;box-shadow:0 32px 80px rgba(0,0,0,.18);
            animation:modalIn .25s ease;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(12px)} to{opacity:1;transform:scale(1) translateY(0)} }

        .modal-header { padding:1.5rem;border-bottom:1px solid var(--slate-100);display:flex;align-items:center;justify-content:space-between; }
        .modal-body   { padding:1.5rem; }
        .modal-footer { padding:1rem 1.5rem;border-top:1px solid var(--slate-100);display:flex;justify-content:flex-end;gap:.75rem; }

        .modal-close { width:36px;height:36px;border-radius:.625rem;background:var(--slate-100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--slate-500); }
        .modal-close:hover { background:var(--slate-200); }

        /* ── Detail grid inside modal ── */
        .detail-section { margin-bottom:1.25rem; }
        .detail-section-title { font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--slate-400);margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem; }
        .detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:.625rem; }
        .detail-item label { font-size:.6875rem;font-weight:600;color:var(--slate-400);display:block;margin-bottom:.125rem; }
        .detail-item span  { font-size:.8125rem;font-weight:600;color:var(--slate-800); }
        .detail-item.full  { grid-column:1/-1; }

        /* ── Form elements in modal ── */
        .form-label { font-size:.75rem;font-weight:700;color:var(--slate-600);display:block;margin-bottom:.375rem; }
        .form-select, .form-input, .form-textarea {
            width:100%;font-family:var(--font-body);font-size:.8125rem;color:var(--slate-800);
            background:var(--slate-50);border:1.5px solid var(--slate-200);border-radius:.75rem;
            padding:.625rem .875rem;outline:none;transition:.15s;
        }
        .form-select:focus, .form-input:focus, .form-textarea:focus { border-color:var(--emerald-500);background:#fff; }
        .form-textarea { resize:vertical;min-height:80px; }

        .reason-option {
            display:flex;align-items:center;gap:.625rem;padding:.625rem .875rem;border-radius:.75rem;
            border:1.5px solid var(--slate-200);cursor:pointer;transition:.15s;font-size:.8125rem;
        }
        .reason-option:hover { border-color:var(--emerald-400);background:var(--emerald-50); }
        .reason-option input[type=radio] { accent-color:var(--emerald-600); }
        .reason-option.selected { border-color:var(--emerald-500);background:var(--emerald-50); }

        /* ── Toast ── */
        .toast {
            position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
            background:#fff;border:1.5px solid var(--slate-200);border-radius:1rem;
            padding:.875rem 1.25rem;box-shadow:0 12px 40px rgba(0,0,0,.12);
            display:flex;align-items:center;gap:.75rem;font-size:.8125rem;font-weight:600;
            animation:toastIn .3s ease;
        }
        @keyframes toastIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

        /* ── Animations ── */
        @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        .animate-up { animation:fadeUp .45s ease forwards; }
        .delay-1 { animation-delay:.05s; }
        .delay-2 { animation-delay:.10s; }
        .delay-3 { animation-delay:.15s; }
        .delay-4 { animation-delay:.20s; }

        /* ── Empty state ── */
        .empty-state { padding:3.5rem;text-align:center;color:var(--slate-400); }
        .empty-state i { font-size:2rem;margin-bottom:.875rem;display:block;opacity:.4; }
        .empty-state p { font-size:.8125rem;font-weight:500; }

        /* ── Pagination ── */
        .pag-btn { width:34px;height:34px;border-radius:.625rem;border:1.5px solid var(--slate-200);display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--slate-600);text-decoration:none;transition:.15s; }
        .pag-btn:hover { border-color:var(--emerald-500);color:var(--emerald-700); }
        .pag-btn.active { background:var(--emerald-800);border-color:var(--emerald-800);color:#fff; }
        .pag-btn.disabled { opacity:.35;pointer-events:none; }
    </style>
    <?php require_once 'sidebar2.php'; ?>

    <main class="main-content">

        <!-- ── Toast Notifikasi ── -->
        <?php if (isset($_GET['berjaya'])): 
            $toast_wa_url = '';
            if (isset($_GET['tempahan_id'])) {
                $toast_tid = intval($_GET['tempahan_id']);
                try {
                    $stmt_toast = $pdo->prepare("
                        SELECT t.id, t.status_proses, t.ulasan_admin, u.no_telefon AS waris_tel, j.nama_jenazah, lp.no_lot AS lot_ditetapkan
                        FROM tempahan t
                        JOIN users u ON u.id = t.user_id
                        JOIN maklumat_jenazah j ON j.id = t.jenazah_id
                        LEFT JOIN lot_pusara lp ON lp.jenazah_id = j.id
                        WHERE t.id = ?
                    ");
                    $stmt_toast->execute([$toast_tid]);
                    $toast_row = $stmt_toast->fetch(PDO::FETCH_ASSOC);
                    if ($toast_row) {
                        $toast_tel = trim($toast_row['waris_tel'] ?? '');
                        $toast_clean = preg_replace('/[^0-9]/', '', $toast_tel);
                        if (strpos($toast_clean, '0') === 0) {
                            $toast_wa = '6' . $toast_clean;
                        } else if (strpos($toast_clean, '60') === 0) {
                            $toast_wa = $toast_clean;
                        } else {
                            $toast_wa = '60' . $toast_clean;
                        }
                        
                        if ($_GET['berjaya'] === 'lulus') {
                            $toast_lot = !empty($toast_row['lot_ditetapkan']) ? $toast_row['lot_ditetapkan'] : '—';
                            $toast_msg = "Assalamualaikum / Salam Sejahtera. Permohonan tempahan lot kubur #" . $toast_row['id'] . " bagi arwah " . $toast_row['nama_jenazah'] . " telah DILULUSKAN. Lot yang ditetapkan: " . $toast_lot . ". Anda kini boleh menyemak panduan navigasi di SmartGrave. Terima kasih.";
                        } else {
                            $toast_sebab = !empty($toast_row['ulasan_admin']) ? $toast_row['ulasan_admin'] : 'Sebab-sebab tertentu';
                            $toast_msg = "Assalamualaikum / Salam Sejahtera. Permohonan tempahan lot kubur #" . $toast_row['id'] . " bagi arwah " . $toast_row['nama_jenazah'] . " terpaksa DITOLAK. Sebab: " . $toast_sebab . ". Sila hubungi kami jika ada pertanyaan. Terima kasih.";
                        }
                        $toast_wa_url = "https://wa.me/" . $toast_wa . "?text=" . urlencode($toast_msg);
                    }
                } catch (Exception $ex) {}
            }
        ?>
        <div class="toast" id="toastMsg" style="max-width: 400px; display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 0.75rem; width: 100%;">
                <?php if ($_GET['berjaya'] === 'lulus'): ?>
                    <i class="fas fa-circle-check text-emerald-600 text-lg"></i>
                    <span>Tempahan berjaya <strong>diluluskan</strong> dan lot telah ditetapkan.</span>
                <?php else: ?>
                    <i class="fas fa-circle-xmark text-rose-500 text-lg"></i>
                    <span>Tempahan telah <strong>ditolak</strong> dan waris telah dimaklumkan.</span>
                <?php endif; ?>
                <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;margin-left:auto;"><i class="fas fa-xmark"></i></button>
            </div>
            <?php if (!empty($toast_wa_url)): ?>
            <div style="width: 100%; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; display: flex; justify-content: flex-end;">
                <a href="<?= $toast_wa_url ?>" target="_blank" class="btn-xs flex items-center gap-1.5" style="background: #25d366; color: white; border-radius: 0.5rem; padding: 0.35rem 0.75rem; text-decoration: none; font-size: 11px;">
                    <i class="fab fa-whatsapp"></i> Maklumkan Waris via WhatsApp
                </a>
            </div>
            <?php endif; ?>
        </div>
        <script>setTimeout(()=>{ const t=document.getElementById('toastMsg'); if(t) t.style.opacity='0', setTimeout(()=>t.remove(),400); }, 8000);</script>
        <?php endif; ?>


        <!-- ── Tajuk Halaman ── -->
        <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950">Senarai Permohonan Lot Kubur</h1>
            <p class="text-emerald-700 font-medium tracking-tight">Urus tempahan lot kubur</p>
        </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center space-x-4">

            
            <div class="relative group">
                <button class="profile-dropdown-btn w-14 h-14 bg-white rounded-2xl shadow-sm border border-emerald-100 flex items-center justify-center text-emerald-800 font-bold text-xl border-b-4 border-b-emerald-700 hover:bg-emerald-50 transition-all cursor-pointer focus:outline-none">
                    <?php echo $initial; ?>
                </button>
                <div class="profile-dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-200 transform origin-top-right group-hover:translate-y-0 translate-y-2">
                    <div class="px-4 py-2 border-b border-gray-50 mb-2">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tetapan Akaun</p>
                    </div>
                    <a href="edit_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors">
                        <i class="fas fa-user-edit mr-3 opacity-50 w-4"></i> Edit Profil
                    </a>
                    <a href="tukar_password.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors">
                        <i class="fas fa-key mr-3 opacity-50 w-4"></i> Tukar Password
                    </a>
                </div>
            </div>
        </div>


</div>
        </div>

        <!-- ── Stat Cards ── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">

            <div class="stat-card emerald animate-up delay-1">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="stat-label">Lot Tersedia</p>
                        <p class="stat-value"><?= number_format($lotTersedia) ?></p>
                        <p class="text-xs text-slate-400 font-medium mt-1">daripada <?= number_format($lotJumlah) ?> lot keseluruhan</p>
                    </div>
                    <span class="stat-icon emerald"><i class="fas fa-th-large"></i></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $lotJumlah > 0 ? round(($lotTersedia/$lotJumlah)*100) : 0 ?>%"></div>
                </div>
            </div>

            <div class="stat-card rose animate-up delay-2">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="stat-label">Lot Terisi</p>
                        <p class="stat-value"><?= number_format($lotPenuh) ?></p>
                        <p class="text-xs text-slate-400 font-medium mt-1"><?= $peratusGuna ?>% kapasiti digunakan</p>
                    </div>
                    <span class="stat-icon rose"><i class="fas fa-circle-dot"></i></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $peratusGuna ?>%;background:linear-gradient(90deg,#f43f5e,#fb7185);"></div>
                </div>
            </div>

            <div class="stat-card blue animate-up delay-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="stat-label">Kutipan Bulan Ini</p>
                        <p class="stat-value text-2xl">RM <?= number_format($jumlahBayaran, 2) ?></p>
                        <p class="text-xs text-slate-400 font-medium mt-1"><?= $ahliAktif ?> ahli khairat aktif</p>
                    </div>
                    <span class="stat-icon blue"><i class="fas fa-wallet"></i></span>
                </div>
            </div>

        </div>

        <!-- ── Jadual Tempahan ── -->
        <div class="panel animate-up delay-2">
            <div class="panel-header">
                <h3 class="panel-title"><i class="fas fa-list-check text-emerald-600 mr-2"></i> Pengurusan Tempahan</h3>
                <!-- Filter Tabs -->
                <div class="flex gap-2 flex-wrap">
                    <?php
                    $tabs = ['semua'=>'Semua','pending'=>'Belum Diluluskan','lulus'=>'Diluluskan','tolak'=>'Ditolak'];
                    foreach ($tabs as $val => $label): ?>
                    <a href="?filter=<?= $val ?>" class="filter-tab <?= $filter === $val ? 'active' : '' ?>">
                        <?= $label ?>
                        <?php if ($val === 'pending' && $tempahanPending > 0): ?>
                        <span style="background:#f59e0b;color:#fff;border-radius:999px;padding:0 5px;font-size:.6rem;margin-left:.2rem;"><?= $tempahanPending ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Waris</th>
                            <th>Nama Jenazah</th>
                            <th>Tarikh Mohon</th>
                            <th>Lot Ditetapkan</th>
                            <th>Status</th>
                            <th style="text-align:center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($semuaTempahan)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Tiada rekod tempahan ditemui</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($semuaTempahan as $row): ?>
                        <tr>
                            <td class="font-bold text-slate-400 text-xs">#<?= $row['id'] ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-700 text-xs font-bold flex-shrink-0">
                                        <?= strtoupper(substr($row['waris_nama'], 0, 1)) ?>
                                    </span>
                                    <div>
                                        <span class="font-semibold text-slate-800 text-xs block truncate max-w-[110px]"><?= htmlspecialchars($row['waris_nama']) ?></span>
                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($row['waris_ic'] ?? '') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="font-semibold text-slate-800 text-xs block"><?= htmlspecialchars($row['nama_jenazah']) ?></span>
                                <span class="text-[10px] text-slate-400"><?= htmlspecialchars($row['jenazah_ic'] ?? '') ?></span>
                            </td>
                            <td class="text-xs text-slate-500"><?= date('d M Y', strtotime($row['tarikh_mohon'])) ?></td>
                            <td>
                                <?php if ($row['lot_ditetapkan']): ?>
                                    <span class="font-bold text-emerald-700 text-xs"><i class="fas fa-location-dot mr-1"></i><?= htmlspecialchars($row['lot_ditetapkan']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $s = $row['status_proses'];
                                $badgeMap = ['Pending'=>'badge-pending','Lulus'=>'badge-lulus','Tolak'=>'badge-tolak','Bayaran Berjaya'=>'badge-bayaran'];
                                $bc = $badgeMap[$s] ?? 'badge-pending';
                                $s_display = ($s === 'Pending') ? 'Belum Diluluskan' : $s;
                                ?>
                                <span class="badge <?= $bc ?>">● <?= htmlspecialchars($s_display) ?></span>
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- View -->
                                    <button
                                        class="icon-btn icon-btn-view"
                                        title="Lihat Maklumat"
                                        onclick="bukaModal('view', <?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>

                                    <!-- WhatsApp -->
                                    <?php
                                    $raw_tel = trim($row['waris_tel'] ?? '');
                                    $clean_tel = preg_replace('/[^0-9]/', '', $raw_tel);
                                    if (strpos($clean_tel, '0') === 0) {
                                        $wa_tel = '6' . $clean_tel;
                                    } else if (strpos($clean_tel, '60') === 0) {
                                        $wa_tel = $clean_tel;
                                    } else {
                                        $wa_tel = '60' . $clean_tel;
                                    }
                                    
                                    // Custom message based on status
                                    $s = $row['status_proses'];
                                    if ($s === 'Lulus') {
                                        $lot = !empty($row['lot_ditetapkan']) ? $row['lot_ditetapkan'] : '—';
                                        $wa_msg = "Assalamualaikum. Permohonan tempahan lot kubur #" . $row['id'] . " bagi arwah " . $row['nama_jenazah'] . " telah DILULUSKAN. Lot yang ditetapkan: " . $lot . ". Anda kini boleh menyemak panduan navigasi di SmartGrave. Terima kasih.";
                                    } else if ($s === 'Tolak') {
                                        $sebab = !empty($row['ulasan_admin']) ? $row['ulasan_admin'] : 'sebab-sebab tertentu';
                                        $wa_msg = "Assalamualaikum. Permohonan tempahan lot kubur #" . $row['id'] . " bagi arwah " . $row['nama_jenazah'] . " terpaksa DITOLAK atas sebab: " . $sebab . ". Sila hubungi pihak kami jika ada sebarang kemusykilan. Terima kasih.";
                                    } else {
                                        $wa_msg = "Assalamualaikum. Saya admin dari SmartGrave. Ingin bertanya lanjut berkenaan permohonan tempahan lot kubur #" . $row['id'] . " bagi arwah " . $row['nama_jenazah'] . ". Terima kasih.";
                                    }
                                    
                                    $wa_url = "https://wa.me/" . $wa_tel . "?text=" . urlencode($wa_msg);
                                    ?>
                                    <a
                                        href="<?= $wa_url ?>"
                                        target="_blank"
                                        class="icon-btn flex items-center justify-center"
                                        style="background: #25d366; color: white;"
                                        title="WhatsApp Waris">
                                        <i class="fab fa-whatsapp text-sm"></i>
                                    </a>

                                    <?php if (in_array($s, ['Pending', 'Bayaran Berjaya'])): ?>
                                    <!-- Lulus -->
                                    <button
                                        class="icon-btn icon-btn-lulus"
                                        title="Lulus & Tetapkan Lot"
                                        onclick="bukaModal('lulus', <?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                        <i class="fas fa-check text-xs"></i>
                                    </button>
                                    


                                    <!-- Tolak -->
                                    <button
                                        class="icon-btn icon-btn-tolak"
                                        title="Tolak Permohonan"
                                        onclick="bukaModal('tolak', <?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                        <i class="fas fa-xmark text-xs"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($s === 'Lulus' && empty($row['lot_ditetapkan'])): ?>
                                    <!-- Tetapkan Lot Semula -->
                                    <a
                                        href="susun_lot.php?tempahan_id=<?= $row['id'] ?>"
                                        class="icon-btn flex items-center justify-center"
                                        style="background: #f59e0b; color: white;"
                                        title="Tetapkan Lot Kubur">
                                        <i class="fas fa-location-dot text-xs"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-50">
                <p class="text-xs text-slate-400 font-medium">
                    Menunjukkan <?= $offset+1 ?>–<?= min($offset+$perPage, $totalRows) ?> daripada <?= $totalRows ?> rekod
                </p>
                <div class="flex gap-1.5">
                    <a href="?filter=<?= $filter ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $p ?>" class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <footer class="text-center text-slate-400 text-[10px] mt-10 tracking-widest uppercase pb-6">
        &copy; 2026 SmartGrave Bangi Lama. Sistem Pengurusan Jenazah Digital Patuh Syariah.
    </footer>
    </main>
    
</div>

<!-- ═══════════════════════════════════════
     MODAL: LIHAT MAKLUMAT
═══════════════════════════════════════ -->
<div class="modal-overlay" id="modalView">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <div>
                <h3 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:800;color:var(--slate-900);">
                    <i class="fas fa-file-lines text-emerald-600 mr-2"></i>Maklumat Tempahan
                </h3>
                <p class="text-xs text-slate-400 mt-0.5" id="viewModalSubtitle"></p>
            </div>
            <button class="modal-close" onclick="tutupModal('view')"><i class="fas fa-xmark text-sm"></i></button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <!-- Diisi oleh JS -->
        </div>
        <div class="modal-footer">
            <button class="btn-sm btn-ghost" onclick="tutupModal('view')">Tutup</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: TERIMA PERMOHONAN
═══════════════════════════════════════ -->
<div class="modal-overlay" id="modalLulus">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div>
                <h3 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:800;color:var(--slate-900);">
                    <i class="fas fa-circle-check text-emerald-600 mr-2"></i>Terima Permohonan
                </h3>
                <p class="text-xs text-slate-400 mt-0.5" id="lulusModalSubtitle"></p>
            </div>

            <button class="modal-close" onclick="tutupModal('lulus')">
                <i class="fas fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="lulus">
            <input type="hidden" name="tempahan_id" id="lulusTempahanId">

            <div class="modal-body">

                <div style="background:var(--emerald-50);border:1.5px solid var(--emerald-100);border-radius:.875rem;padding:1rem;margin-bottom:1.25rem;">
                    <p class="text-xs font-bold text-emerald-700 mb-1 uppercase tracking-widest">
                        Ringkasan Permohonan
                    </p>

                    <p class="text-sm text-slate-700" id="lulusRingkasan"></p>
                </div>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.875rem;padding:1rem;">
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Permohonan ini akan diterima dan anda akan dibawa ke halaman
                        <strong>susun lot</strong> untuk menetapkan lokasi pusara.
                    </p>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-sm btn-ghost" onclick="tutupModal('lulus')">
                    Batal
                </button>

                <button type="submit" class="btn-sm btn-emerald">
                    <i class="fas fa-check"></i> Terima Permohonan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: TOLAK PERMOHONAN
═══════════════════════════════════════ -->
<div class="modal-overlay" id="modalTolak">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3 style="font-family:var(--font-display);font-size:1.0625rem;font-weight:800;color:var(--slate-900);">
                    <i class="fas fa-circle-xmark text-rose-500 mr-2"></i>Tolak Permohonan
                </h3>
                <p class="text-xs text-slate-400 mt-0.5" id="tolakModalSubtitle"></p>
            </div>
            <button class="modal-close" onclick="tutupModal('tolak')"><i class="fas fa-xmark text-sm"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="tolak">
            <input type="hidden" name="tempahan_id" id="tolakTempahanId">
            <div class="modal-body">

                <!-- Amaran -->
                <div style="background:#fff1f2;border:1.5px solid #fecdd3;border-radius:.875rem;padding:1rem;margin-bottom:1.25rem;">
                    <p class="text-xs font-bold text-rose-600 mb-0.5"><i class="fas fa-triangle-exclamation mr-1"></i>Perhatian</p>
                    <p class="text-xs text-rose-700">Waris akan dimaklumkan melalui notifikasi sistem mengenai penolakan ini beserta sebab yang dinyatakan.</p>
                </div>

                <!-- Pilihan sebab -->
                <p class="form-label mb-3">Pilih sebab penolakan <span class="text-rose-500">*</span></p>
                <div class="flex flex-col gap-2 mb-4" id="reasonList">
                    <?php
                    $sebabList = [
                        'Dokumen tidak lengkap atau tidak sah',
                        'Maklumat jenazah tidak sepadan dengan rekod',
                        'Sijil kematian tidak disertakan',
                        'Permohonan pendua / duplikasi',
                        'Lot kubur tidak tersedia untuk kawasan diminta',
                        'Lain-lain',
                    ];
                    foreach ($sebabList as $i => $sebab): ?>
                    <label class="reason-option" onclick="pilihSebab(this, '<?= htmlspecialchars($sebab, ENT_QUOTES) ?>')">
                        <input type="radio" name="sebab_pilihan" value="<?= htmlspecialchars($sebab, ENT_QUOTES) ?>" required>
                        <span class="text-slate-700"><?= htmlspecialchars($sebab) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Input lain-lain -->
                <div id="lainLainDiv" style="display:none;">
                    <label class="form-label" for="sebabLain">Nyatakan sebab lain <span class="text-rose-500">*</span></label>
                    <textarea name="sebab_lain" id="sebabLain" class="form-textarea" placeholder="Nyatakan sebab penolakan dengan jelas..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-sm btn-ghost" onclick="tutupModal('tolak')">Batal</button>
                <button type="submit" class="btn-sm" style="background:#f43f5e;color:#fff;">
                    <i class="fas fa-xmark"></i> Sahkan Penolakan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════ -->
<script>
function bukaModal(jenis, data) {
    if (jenis === 'view') {
        // Isi kandungan modal view
        document.getElementById('viewModalSubtitle').textContent = 'Tempahan #' + data.id + ' — ' + data.waris_nama;

        const statusMap = {
            'Pending'        : '<span class="badge badge-pending">● Belum Diluluskan</span>',
            'Lulus'          : '<span class="badge badge-lulus">● Diluluskan</span>',
            'Tolak'          : '<span class="badge badge-tolak">● Ditolak</span>',
            'Bayaran Berjaya': '<span class="badge badge-bayaran">● Bayaran Berjaya</span>',
        };
        const statusBadge = statusMap[data.status_proses] || data.status_proses;

        let lotHtml = data.lot_ditetapkan
            ? `<span style="color:var(--emerald-700);font-weight:700;"><i class="fas fa-location-dot mr-1"></i>${esc(data.lot_ditetapkan)}</span>`
            : '<span style="color:var(--slate-300);">Belum ditetapkan</span>';

        let ulasanHtml = data.ulasan_admin
            ? `<div style="background:var(--slate-50);border-radius:.75rem;padding:.75rem;font-size:.8125rem;color:var(--slate-600);">${esc(data.ulasan_admin)}</div>`
            : '<span style="color:var(--slate-300);font-size:.8125rem;">—</span>';

        let buktiBiasa = data.bukti_sijil
            ? `<a href="${esc(data.bukti_sijil)}" target="_blank" class="btn-xs btn-green"><i class="fas fa-file-pdf mr-1"></i>Lihat Dokumen</a>`
            : '<span style="color:var(--slate-300);font-size:.8125rem;">Tiada</span>';

        let buktipermit = data.permit_polis
            ? `<a href="${esc(data.permit_polis)}" target="_blank" class="btn-xs btn-green"><i class="fas fa-file-pdf mr-1"></i>Lihat Permit</a>`
            : '<span style="color:var(--slate-300);font-size:.8125rem;">Tiada</span>';

        document.getElementById('viewModalBody').innerHTML = `
            <div class="detail-section">
                <p class="detail-section-title"><i class="fas fa-user"></i> Maklumat Waris</p>
                <div class="detail-grid">
                    <div class="detail-item"><label>Nama Penuh</label><span>${esc(data.waris_nama)}</span></div>
                    <div class="detail-item"><label>No. K/P</label><span>${esc(data.waris_ic || '—')}</span></div>
                    <div class="detail-item"><label>No. Telefon</label><span>${esc(data.waris_tel || '—')}</span></div>
                    <div class="detail-item"><label>No. Tel. Alternatif</label><span>${esc(data.no_tel_alt || '—')}</span></div>
                    <div class="detail-item"><label>E-mel</label><span>${esc(data.waris_email || '—')}</span></div>
                    <div class="detail-item"><label>Hubungan dengan Jenazah</label><span>${esc(data.hub_waris || '—')}</span></div>
                    <div class="detail-item full"><label>Alamat</label><span>${esc(data.waris_alamat || '—')}</span></div>
                </div>
            </div>

            <div style="height:1px;background:var(--slate-100);margin:1rem 0;"></div>

            <div class="detail-section">
                <p class="detail-section-title"><i class="fas fa-person"></i> Maklumat Jenazah</p>
                <div class="detail-grid">
                    <div class="detail-item"><label>Nama Jenazah</label><span>${esc(data.nama_jenazah)}</span></div>
                    <div class="detail-item"><label>No. K/P Jenazah</label><span>${esc(data.jenazah_ic || '—')}</span></div>
                    <div class="detail-item"><label>Jantina</label><span>${esc(data.jantina || '—')}</span></div>
                    <div class="detail-item"><label>Tarikh Wafat</label><span>${data.tarikh_wafat ? fmtDate(data.tarikh_wafat) : '—'}</span></div>
                    <div class="detail-item"><label>Masa Wafat</label><span>${esc(data.masa_wafat || '—')}</span></div>
                    <div class="detail-item full"><label>Lokasi Wafat</label><span>${esc(data.lokasi_wafat || '—')}</span></div>
                </div>
            </div>

            <div style="height:1px;background:var(--slate-100);margin:1rem 0;"></div>

            <div class="detail-section">
                <p class="detail-section-title"><i class="fas fa-clipboard"></i> Status & Dokumen</p>
                <div class="detail-grid">
                    <div class="detail-item"><label>Status Proses</label><span>${statusBadge}</span></div>
                    <div class="detail-item"><label>Lot Ditetapkan</label><span>${lotHtml}</span></div>
                    <div class="detail-item"><label>Sijil / Bukti</label><span>${buktiBiasa}</span></div>
                    <div class="detail-item"><label>Permit Polis</label><span>${buktipermit}</span></div>
                    <div class="detail-item full"><label>Catatan Waris</label>
                        <span>${data.catatan ? `<div style="background:var(--slate-50);border-radius:.75rem;padding:.75rem;font-size:.8125rem;">${esc(data.catatan)}</div>` : '<span style="color:var(--slate-300);">—</span>'}</span>
                    </div>
                    <div class="detail-item full"><label>Ulasan Admin</label><span>${ulasanHtml}</span></div>
                </div>
            </div>
        `;

        document.getElementById('modalView').classList.add('open');
    }

    else if (jenis === 'lulus') {
        document.getElementById('lulusTempahanId').value = data.id;
        document.getElementById('lulusModalSubtitle').textContent = 'Tempahan #' + data.id;
        document.getElementById('lulusRingkasan').innerHTML =
            `<strong>${esc(data.waris_nama)}</strong> memohon lot bagi jenazah <strong>${esc(data.nama_jenazah)}</strong>` +
            (data.tarikh_wafat ? ` (wafat: ${fmtDate(data.tarikh_wafat)})` : '') + '.';
        document.getElementById('modalLulus').classList.add('open');
    }

    else if (jenis === 'tolak') {
        document.getElementById('tolakTempahanId').value = data.id;
        document.getElementById('tolakModalSubtitle').textContent = 'Tempahan #' + data.id + ' — ' + data.nama_jenazah;
        // Reset radio
        document.querySelectorAll('#reasonList .reason-option').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('#reasonList input[type=radio]').forEach(el => el.checked = false);
        document.getElementById('lainLainDiv').style.display = 'none';
        document.getElementById('modalTolak').classList.add('open');
    }

}

function tutupModal(jenis) {
    document.getElementById('modal' + jenis.charAt(0).toUpperCase() + jenis.slice(1)).classList.remove('open');
}

function pilihSebab(el, val) {
    document.querySelectorAll('.reason-option').forEach(x => x.classList.remove('selected'));
    el.classList.add('selected');
    const ll = document.getElementById('lainLainDiv');
    if (val === 'Lain-lain') {
        ll.style.display = 'block';
        document.getElementById('sebabLain').required = true;
    } else {
        ll.style.display = 'none';
        document.getElementById('sebabLain').required = false;
    }
}

// Close on backdrop click
['modalView','modalLulus','modalTolak'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) {
            const key = id.replace('modal','').toLowerCase();
            tutupModal(key === 'lulus' ? 'lulus' : key === 'tolak' ? 'tolak' : 'view');
        }
    });
});

// Helpers
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('ms-MY', {day:'2-digit', month:'long', year:'numeric'});
}

// Progress bar animate on load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.progress-fill').forEach(el => {
        const w = el.style.width;
        el.style.width = '0';
        setTimeout(() => { el.style.width = w; }, 300);
    });
});
</script>

</div> <!-- close the flex min-h-screen div from header.php -->
</body>

</html>
