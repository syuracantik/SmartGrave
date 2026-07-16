<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$title = "Daftar Ahli Khairat";
include 'header.php';
include 'sidebar.php';

$user_id = $_SESSION['user_id'];

$success = "";
$error   = "";

// ============================================================
// FETCH DATA USER (untuk auto-fill "Diri Sendiri")
// Satu table je — tak perlu JOIN waris lagi
// ============================================================
$data_waris = ['nama' => '', 'no_ic' => '', 'telefon' => '', 'alamat' => ''];
$status_kariah = "Bukan Ahli";
$initial = "U";

try {
    $stmtUser = $pdo->prepare("
        SELECT full_name AS nama, ic_number AS no_ic,
               no_telefon AS telefon, alamat, gender, status_khairat
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmtUser->execute([$user_id]);
    $row = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $data_waris['nama']    = strtoupper(trim($row['nama']    ?? ''));
        $data_waris['no_ic']   = preg_replace('/[^0-9]/', '', trim($row['no_ic']   ?? ''));
        $data_waris['telefon'] = trim($row['telefon'] ?? '');
        $data_waris['alamat']  = trim($row['alamat']  ?? '');
        
        $nama_user = $row['nama'];
        
        // Dynamically verify if they have registered Diri Sendiri and paid
        $stmt_check_khairat = $pdo->prepare("
            SELECT COUNT(*) 
            FROM daftar_khairat 
            WHERE user_id = ? AND (hubungan = 'Diri Sendiri' OR hubungan = 'DIRI SENDIRI') AND status_yuran = 'Dibayar'
        ");
        $stmt_check_khairat->execute([$user_id]);
        $has_active_khairat = $stmt_check_khairat->fetchColumn() > 0;

        $is_member = false;
        if ($has_active_khairat) {
            $is_member = true;
            if (!$row['status_khairat']) {
                $pdo->prepare("UPDATE users SET status_khairat = true WHERE id = ?")->execute([$user_id]);
            }
        } else {
            $is_member = ($row['status_khairat'] === true || $row['status_khairat'] === 1 || $row['status_khairat'] === 't');
        }

        if ($is_member) {
            $status_kariah  = "Ahli";
        } else {
            $status_kariah  = "Bukan Ahli";
        }
        
        $initial = !empty($nama_user) ? strtoupper(substr($nama_user, 0, 1)) : "U";
    }

} catch (PDOException $e) {
    error_log("Auto-fill user error: " . $e->getMessage());
}

// ============================================================
// CONSTANTS
// ============================================================
define('YURAN_KHAIRAT',    60);
define('STATUS_DIBAYAR',   'Dibayar');
define('STATUS_TUNGGAKAN', 'Tunggakan');
define('STATUS_BELUM',     'Tunggakan');

// ============================================================
// REGISTER AHLI — POST HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_khairat'])) {

    try {

        $nama_ahli = strtoupper(trim($_POST['nama_ahli']));
        $no_ic     = preg_replace('/[^0-9]/', '', trim($_POST['no_ic']));
        $telefon   = preg_replace('/[^0-9+\-]/', '', trim($_POST['telefon']));
        $hubungan  = trim($_POST['hubungan']);
        $alamat    = trim($_POST['alamat']);

        // Handle pekerjaan lain-lain
        $pekerjaan_pilihan = trim($_POST['pekerjaan']);
        if ($pekerjaan_pilihan === 'Lain-lain') {
            $pekerjaan = trim($_POST['pekerjaan_lain']);
            if (empty($pekerjaan))
                throw new InvalidArgumentException("Sila nyatakan pekerjaan jika memilih 'Lain-lain'.");
        } else {
            $pekerjaan = $pekerjaan_pilihan;
        }

        // Handle hubungan lain-lain
        if ($hubungan === 'Lain-lain') {
            $hubungan_nyata = trim($_POST['hubungan_lain'] ?? '');
            if (empty($hubungan_nyata))
                throw new InvalidArgumentException("Sila nyatakan hubungan jika memilih 'Lain-lain'.");
            $hubungan = $hubungan_nyata;
        }

        if (strlen($no_ic) !== 12)
            throw new InvalidArgumentException("No. IC tidak sah. Pastikan 12 digit dimasukkan.");

        if (empty($nama_ahli) || empty($telefon) || empty($alamat))
            throw new InvalidArgumentException("Sila lengkapkan semua maklumat yang diperlukan.");

        // --------------------------------------------------------
        // CHECK DUPLICATE IC — FK dah tukar: id_waris → user_id
        // --------------------------------------------------------
        $check = $pdo->prepare("
            SELECT dk.*, u.full_name AS nama_waris
            FROM daftar_khairat dk
            LEFT JOIN users u ON u.id = dk.user_id
            WHERE dk.no_ic = ?
            LIMIT 1
        ");
        $check->execute([$no_ic]);

        if ($check->rowCount() > 0) {

            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing['status_yuran'] === STATUS_DIBAYAR) {
                $error = "No. IC <strong>" . htmlspecialchars($no_ic) . "</strong> sudah berdaftar dan aktif sebagai ahli khairat.";
            } elseif ($existing['user_id'] == $user_id) {
                $error = "No. IC ini sudah didaftarkan oleh anda tetapi yuran belum dijelaskan. Sila gunakan butang <strong>Bayar</strong> di senarai ahli di bawah.";
            } else {
                $error = "No. IC ini sudah wujud dalam sistem di bawah waris lain.";
            }

        } else {

            // --------------------------------------------------------
            // INSERT — FK dah tukar: id_waris → user_id
            // --------------------------------------------------------
            $stmt = $pdo->prepare("
                INSERT INTO daftar_khairat
                    (user_id, tarikh_daftar, status_yuran, no_ic,
                     nama_ahli, telefon, pekerjaan, hubungan, alamat)
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                STATUS_BELUM,
                $no_ic,
                $nama_ahli,
                $telefon,
                $pekerjaan,
                $hubungan,
                $alamat,
            ]);

            $khairat_id = $pdo->lastInsertId();

            // URL param ikut schema baru: khairat_id (bukan id_khairat)
            echo "<script>window.location.href='payment.php?type=khairat&khairat_id=" . $khairat_id . "';</script>";
            exit();
        }

    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Ralat pangkalan data: " . $e->getMessage();
    }
}

// ============================================================
// FETCH SENARAI AHLI — FK dah tukar: id_waris → user_id
// ============================================================
$stmtAhli = $pdo->prepare("
    SELECT *
    FROM daftar_khairat
    WHERE user_id = ?
    ORDER BY tarikh_daftar DESC
");
$stmtAhli->execute([$user_id]);
$senarai_ahli = $stmtAhli->fetchAll(PDO::FETCH_ASSOC);

$jumlah_aktif   = 0;
$jumlah_tunggak = 0;
foreach ($senarai_ahli as $a) {
    if ($a['status_yuran'] === STATUS_DIBAYAR) $jumlah_aktif++;
    if ($a['status_yuran'] !== STATUS_DIBAYAR) $jumlah_tunggak++;
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<title><?php echo $title; ?> – SmartGrave</title>

<style>
/* =====================================================
   DESIGN TOKENS
===================================================== */
:root {
    --green-50:  #f0fdf4;
    --green-100: #dcfce7;
    --green-200: #bbf7d0;
    --green-500: #22c55e;
    --green-600: #16a34a;
    --green-700: #15803d;
    --green-800: #166534;
    --green-900: #14532d;

    --slate-50:  #f8fafc;
    --slate-100: #f1f5f9;
    --slate-200: #e2e8f0;
    --slate-400: #94a3b8;
    --slate-500: #64748b;
    --slate-600: #475569;
    --slate-700: #334155;
    --slate-800: #1e293b;
    --slate-900: #0f172a;

    --red-50:  #fef2f2;
    --red-100: #fee2e2;
    --red-600: #dc2626;
    --red-700: #b91c1c;

    --amber-50:  #fffbeb;
    --amber-100: #fef3c7;
    --amber-600: #d97706;
    --amber-700: #b45309;

    --font: 'Plus Jakarta Sans', sans-serif;
    --radius-sm: 10px;
    --radius-md: 16px;
    --radius-lg: 24px;
    --radius-xl: 32px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.07), 0 2px 6px rgba(0,0,0,.04);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.1), 0 4px 12px rgba(0,0,0,.05);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: var(--font);
    background: var(--slate-50);
    color: var(--slate-800);
    -webkit-font-smoothing: antialiased;
}

/* =====================================================
   PAGE LAYOUT
===================================================== */
    /* Corak bulat-bulat geometri (Islamic Pattern) */
    .bg-islamic {
        background-color: #f8fafc; /* Warna base (Slate 50) */
        background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
        background-attachment: fixed;
    }

    /* Efek Glassmorphism untuk borang supaya nampak 'clean' */
    .glass-effect {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
/* =====================================================
   PAGE HEADER
===================================================== */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 1.875rem;
    font-weight: 800;
    color: var(--green-900);
    line-height: 1.2;
}

.page-header p {
    margin-top: .3rem;
    font-size: .9rem;
    color: var(--green-700);
    font-weight: 500;
}

.header-badge {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #fff;
    border: 1px solid var(--green-100);
    border-radius: var(--radius-md);
    padding: .75rem 1.25rem;
    box-shadow: var(--shadow-sm);
    white-space: nowrap;
}

.header-badge-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--green-100);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--green-700);
    font-size: 1.1rem;
}

.header-badge-label {
    font-size: .65rem;
    font-weight: 700;
    color: var(--slate-400);
    text-transform: uppercase;
    letter-spacing: .06em;
}

.header-badge-value {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--green-900);
    line-height: 1.2;
}

/* =====================================================
   STAT STRIP
===================================================== */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}

.stat-card {
    background: #fff;
    border-radius: var(--radius-md);
    padding: 1.1rem 1.25rem;
    border: 1px solid var(--slate-200);
    box-shadow: var(--shadow-sm);
}

.stat-card .stat-label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--slate-400);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: .4rem;
}

.stat-card .stat-num {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
}

.stat-card.green .stat-num { color: var(--green-700); }
.stat-card.amber .stat-num { color: var(--amber-700); }
.stat-card.slate .stat-num { color: var(--slate-700); }

.stat-card .stat-sub {
    font-size: .75rem;
    color: var(--slate-400);
    margin-top: .25rem;
}

/* =====================================================
   INFO BANNER
===================================================== */
.info-banner {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: var(--green-50);
    border: 1px solid var(--green-200);
    border-left: 4px solid var(--green-600);
    border-radius: var(--radius-md);
    padding: 1.1rem 1.4rem;
    margin-bottom: 2rem;
}

.info-banner-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--green-100);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--green-700);
    font-size: .9rem;
}

.info-banner h4 {
    font-size: .85rem;
    font-weight: 700;
    color: var(--green-900);
    margin-bottom: .2rem;
}

.info-banner p {
    font-size: .82rem;
    color: var(--slate-600);
    line-height: 1.6;
}

/* =====================================================
   ALERTS
===================================================== */
.alert {
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: .875rem;
    font-weight: 500;
}

.alert-success {
    background: var(--green-50);
    border: 1px solid var(--green-200);
    color: var(--green-800);
}

.alert-error {
    background: var(--red-50);
    border: 1px solid var(--red-100);
    color: var(--red-700);
}

.alert i { margin-top: .1rem; flex-shrink: 0; }

/* =====================================================
   MAIN GRID
===================================================== */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.75rem;
    align-items: start;
}

@media (max-width: 1200px) {
    .content-grid { grid-template-columns: 1fr; }
}

/* =====================================================
   CARD
===================================================== */
.card {
    background: #fff;
    border-radius: var(--radius-xl);
    border: 1px solid var(--slate-200);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

.card-header {
    padding: 1.5rem 2rem;
    background: rgba(22, 163, 74, .04);
    border-bottom: 1px solid var(--green-100);
    display: flex;
    align-items: center;
    gap: .75rem;
}

.card-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--green-100);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--green-700);
    font-size: .9rem;
    flex-shrink: 0;
}

.card-header h3 {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--green-900);
}

.card-body {
    padding: 2rem;
}

/* =====================================================
   FORM ELEMENTS
===================================================== */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.form-grid .full { grid-column: 1 / -1; }

@media (max-width: 640px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }
}

.form-group { display: flex; flex-direction: column; gap: .45rem; }

.form-group label {
    font-size: .72rem;
    font-weight: 700;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: .06em;
}

.form-group label .req { color: var(--red-600); margin-left: 2px; }

.form-control {
    width: 100%;
    padding: .8rem 1rem;
    border: 1.5px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: var(--font);
    font-size: .9rem;
    color: var(--slate-800);
    background: var(--slate-50);
    transition: border-color .2s, box-shadow .2s, background .2s;
    outline: none;
}

.form-control:focus {
    border-color: var(--green-500);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}

.form-control::placeholder { color: var(--slate-400); }

select.form-control { appearance: none; cursor: pointer; }

.select-wrap { position: relative; }

.select-wrap::after {
    content: "\f107";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--slate-400);
    pointer-events: none;
    font-size: .8rem;
}

textarea.form-control { resize: vertical; min-height: 100px; }

/* IC mask styling */
.ic-hint {
    font-size: .7rem;
    color: var(--slate-400);
    margin-top: .2rem;
}

/* Lain-lain reveal box */
.reveal-box {
    display: none;
    animation: slideDown .2s ease;
}

.reveal-box.active { display: flex; flex-direction: column; gap: .45rem; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* =====================================================
   FORM FOOTER / SUBMIT
===================================================== */
.form-footer {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--slate-100);
}

.btn-primary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .6rem;
    width: 100%;
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--green-600) 0%, var(--green-700) 100%);
    color: #fff;
    font-family: var(--font);
    font-size: .95rem;
    font-weight: 700;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: transform .15s, box-shadow .15s, filter .15s;
    box-shadow: 0 4px 16px rgba(22,163,74,.3);
}

.btn-primary:hover {
    filter: brightness(1.05);
    box-shadow: 0 6px 24px rgba(22,163,74,.35);
    transform: translateY(-1px);
}

.btn-primary:active { transform: translateY(0); box-shadow: none; }

.btn-pay {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem .9rem;
    background: var(--green-600);
    color: #fff;
    font-family: var(--font);
    font-size: .75rem;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    transition: background .15s;
}

.btn-pay:hover { background: var(--green-700); }

/* =====================================================
   MEMBER LIST (right panel)
===================================================== */
.member-list {
    display: flex;
    flex-direction: column;
    gap: .85rem;
    max-height: 680px;
    overflow-y: auto;
    padding: 1.5rem;
    scrollbar-width: thin;
    scrollbar-color: var(--slate-200) transparent;
}

.member-card {
    border: 1.5px solid var(--slate-200);
    border-radius: var(--radius-md);
    padding: 1rem 1.1rem;
    transition: border-color .2s, box-shadow .2s;
    position: relative;
}

.member-card:hover {
    border-color: var(--green-200);
    box-shadow: var(--shadow-sm);
}

.member-card.active-member {
    border-color: var(--green-200);
    background: var(--green-50);
}

.member-card.tunggak-member {
    border-color: var(--amber-100);
    background: var(--amber-50);
}

.member-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .75rem;
}

.member-name {
    font-size: .9rem;
    font-weight: 700;
    color: var(--green-900);
    text-transform: uppercase;
    line-height: 1.3;
}

.member-ic {
    font-size: .75rem;
    color: var(--slate-400);
    font-weight: 500;
    margin-top: .1rem;
    font-feature-settings: "tnum";
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .28rem .7rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}

.status-pill.aktif {
    background: var(--green-100);
    color: var(--green-800);
}

.status-pill.tunggak {
    background: var(--amber-100);
    color: var(--amber-700);
}

.status-pill.belum {
    background: var(--red-100);
    color: var(--red-700);
}

.member-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    border-top: 1px solid var(--slate-100);
    padding-top: .65rem;
    margin-top: .1rem;
}

.member-meta-item label {
    display: block;
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--slate-400);
    letter-spacing: .05em;
    margin-bottom: .15rem;
}

.member-meta-item span {
    font-size: .8rem;
    font-weight: 600;
    color: var(--slate-700);
}

.member-hub-pill {
    display: inline-flex;
    padding: .2rem .65rem;
    border-radius: 999px;
    background: var(--slate-100);
    color: var(--slate-600);
    font-size: .7rem;
    font-weight: 600;
}

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 3rem 1rem;
    gap: .75rem;
}

.empty-state-icon {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    background: var(--slate-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--slate-400);
}

.empty-state h4 { font-size: .9rem; font-weight: 700; color: var(--slate-700); }

.empty-state p { font-size: .8rem; color: var(--slate-400); max-width: 180px; line-height: 1.5; }

/* =====================================================
   STICKY RIGHT PANEL
===================================================== */
.panel-sticky { position: sticky; top: 1.5rem; }
</style>
</head>
<body>

<main class="flex-1 p-6 lg:p-12 overflow-y-auto bg-islamic">
    

    <!-- ================================================
         PAGE HEADER
    ================================================ -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950 tracking-tight">Daftar Ahli Khairat Kematian</h1>
            <p class="text-emerald-700 font-medium tracking-tight mt-1">Daftarkan ahli keluarga untuk perlindungan khairat kematian</p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <!-- Badge Yuran Tahunan -->
            <div class="flex items-center gap-3 bg-white border border-emerald-100 rounded-2xl px-5 py-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-700">
                    <i class="fas fa-shield-heart text-lg"></i>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider leading-none">Yuran Tahunan</div>
                    <div class="text-base font-black text-emerald-900 mt-1">RM<?php echo YURAN_KHAIRAT; ?> / Orang</div>
                </div>
            </div>

            <!-- Profile Dropdown (Same as waris_dashboard.php) -->
            <div class="flex items-center space-x-4 bg-white/50 p-2 rounded-2xl border border-emerald-50">
                <div class="text-right hidden md:block px-2">
                    <span class="block text-[10px] font-bold text-emerald-600 uppercase tracking-widest leading-none">Status Kariah</span>
                    <span class="text-sm font-bold text-emerald-900"><?php echo htmlspecialchars($status_kariah); ?></span>
                </div>
                
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

    <!-- ================================================
         STAT STRIP
    ================================================ -->
    <div class="stat-strip">
        <div class="stat-card slate">
            <div class="stat-label">Jumlah Ahli</div>
            <div class="stat-num"><?php echo count($senarai_ahli); ?></div>
            <div class="stat-sub">Telah didaftarkan</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Ahli Aktif</div>
            <div class="stat-num"><?php echo $jumlah_aktif; ?></div>
            <div class="stat-sub">Yuran dibayar</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Belum Bayar</div>
            <div class="stat-num"><?php echo $jumlah_tunggak; ?></div>
            <div class="stat-sub">Perlu tindakan</div>
        </div>
    </div>

    <!-- ================================================
         INFO BANNER
    ================================================ -->
    <div class="info-banner">
        <div class="info-banner-icon"><i class="fas fa-circle-info"></i></div>
        <div>
            <h4>Kelebihan Ahli Khairat Kematian</h4>
            <p>
                Dengan mendaftar, anda layak mendapat pengurusan jenazah <strong>PERCUMA</strong>
                termasuk pengkebumian, kafan, dan pengurusan lot pusara. Yuran tahunan hanya
                <strong>RM<?php echo YURAN_KHAIRAT; ?></strong> setiap orang.
            </p>
        </div>
    </div>

    <!-- ================================================
         ALERTS
    ================================================ -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-triangle-exclamation"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <!-- ================================================
         CONTENT GRID
    ================================================ -->
    <div class="content-grid">

        <!-- LEFT — BORANG -->
        <div>
            <div class="card">

                <div class="card-header">
                    <div class="card-header-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>Borang Pendaftaran Ahli</h3>
                </div>

                <div class="card-body">

                    <form method="POST" id="formKhairat" novalidate>

                        <div class="form-grid">

                            <!-- NAMA PENUH -->
                            <div class="form-group full">
                                <label>Nama Penuh <span class="req">*</span></label>
                                <input
                                    type="text"
                                    name="nama_ahli"
                                    class="form-control"
                                    placeholder="Contoh: NUR AISYAH BINTI AHMAD"
                                    required
                                    autocomplete="off"
                                    style="text-transform: uppercase;"
                                >
                            </div>

                            <!-- NO IC -->
                            <div class="form-group">
                                <label>No. Kad Pengenalan <span class="req">*</span></label>
                                <input
                                    type="text"
                                    name="no_ic"
                                    id="noIc"
                                    class="form-control"
                                    placeholder="000000-00-0000"
                                    maxlength="14"
                                    required
                                    autocomplete="off"
                                    inputmode="numeric"
                                >
                                <span class="ic-hint" id="icHint">Masukkan 12 digit nombor IC</span>
                            </div>

                            <!-- HUBUNGAN -->
                            <div class="form-group">
                                <label>Hubungan <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <select name="hubungan" id="hubunganSelect" class="form-control" required onchange="toggleHubunganLain()">
                                        <option value="">— Pilih Hubungan —</option>
                                        <option>Diri Sendiri</option>
                                        <option>Isteri</option>
                                        <option>Suami</option>
                                        <option>Anak</option>
                                        <option>Ibu</option>
                                        <option>Bapa</option>
                                        <option>Adik-beradik</option>
                                        <option value="Lain-lain">Lain-lain</option>
                                    </select>
                                </div>
                            </div>

                            <!-- HUBUNGAN LAIN-LAIN (hidden) -->
                            <div class="form-group reveal-box" id="hubunganLainBox">
                                <label>Nyatakan Hubungan <span class="req">*</span></label>
                                <input
                                    type="text"
                                    name="hubungan_lain"
                                    id="hubunganLainInput"
                                    class="form-control"
                                    placeholder="Contoh: Menantu, Cucu, Biras..."
                                >
                            </div>

                            <!-- TELEFON -->
                            <div class="form-group">
                                <label>No. Telefon <span class="req">*</span></label>
                                <input
                                    type="tel"
                                    name="telefon"
                                    class="form-control"
                                    placeholder="01X-XXXXXXX"
                                    required
                                    inputmode="tel"
                                >
                            </div>

                            <!-- PEKERJAAN -->
                            <div class="form-group">
                                <label>Pekerjaan / Status <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <select name="pekerjaan" id="pekerjaanSelect" class="form-control" required onchange="togglePekerjaanLain()">
                                        <option value="">— Pilih —</option>
                                        <option>Pekerja Swasta</option>
                                        <option>Kerajaan</option>
                                        <option>Pelajar</option>
                                        <option>Bekerja Sendiri</option>
                                        <option>Pesara</option>
                                        <option>Suri Rumah</option>
                                        <option>Tidak Bekerja</option>
                                        <option value="Lain-lain">Lain-lain</option>
                                    </select>
                                </div>
                            </div>

                            <!-- PEKERJAAN LAIN-LAIN (hidden) -->
                            <div class="form-group full reveal-box" id="pekerjaanLainBox">
                                <label>Nyatakan Pekerjaan <span class="req">*</span></label>
                                <input
                                    type="text"
                                    name="pekerjaan_lain"
                                    id="pekerjaanLainInput"
                                    class="form-control"
                                    placeholder="Contoh: Freelancer, Rider, Usahawan..."
                                >
                            </div>

                            <!-- ALAMAT -->
                            <div class="form-group full">
                                <label>Alamat Penuh <span class="req">*</span></label>
                                <textarea
                                    name="alamat"
                                    class="form-control"
                                    rows="4"
                                    placeholder="No. rumah, jalan, taman, poskod, negeri..."
                                    required
                                ></textarea>
                            </div>

                        </div><!-- /form-grid -->

                        <!-- SUBMIT -->
                        <div class="form-footer">
                            <button type="submit" name="submit_khairat" class="btn-primary">
                                <i class="fas fa-credit-card"></i>
                                Teruskan ke Pembayaran &mdash; RM<?php echo YURAN_KHAIRAT; ?>
                            </button>
                        </div>

                    </form>

                </div><!-- /card-body -->

            </div><!-- /card -->
        </div><!-- /LEFT -->

        <!-- RIGHT — SENARAI AHLI -->
        <div class="panel-sticky">
            <div class="card">

                <div class="card-header">
                    <div class="card-header-icon"><i class="fas fa-id-card"></i></div>
                    <h3>Ahli Yang Didaftarkan</h3>
                </div>

                <div class="member-list">

                    <?php if (count($senarai_ahli) > 0): ?>

                        <?php foreach ($senarai_ahli as $ahli): ?>

                            <?php
                            // DB only allows: 'Dibayar' or 'Tunggakan'
                            $statusClass = 'tunggak';
                            $statusLabel = 'Belum Bayar';
                            $statusIcon  = 'fa-clock';
                            $cardClass   = 'tunggak-member';

                            if ($ahli['status_yuran'] === STATUS_DIBAYAR) {
                                $statusClass = 'aktif';
                                $statusLabel = 'Aktif';
                                $statusIcon  = 'fa-check';
                                $cardClass   = 'active-member';
                            }

                            // Format date
                            $tarikh = !empty($ahli['tarikh_daftar'])
                                ? date('d M Y', strtotime($ahli['tarikh_daftar']))
                                : '—';
                            ?>

                            <div class="member-card <?php echo $cardClass; ?>">

                                <div class="member-top">
                                    <div>
                                        <div class="member-name">
                                            <?php echo htmlspecialchars($ahli['nama_ahli']); ?>
                                        </div>
                                        <div class="member-ic">
                                            <?php echo htmlspecialchars($ahli['no_ic']); ?>
                                        </div>
                                    </div>
                                    <span class="status-pill <?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?>"></i>
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </div>

                                <div class="member-meta">
                                    <div class="member-meta-item">
                                        <label>Tarikh Daftar</label>
                                        <span><?php echo $tarikh; ?></span>
                                    </div>

                                    <div class="member-meta-item" style="text-align:right;">
                                        <?php if (!empty($ahli['hubungan'])): ?>
                                            <div class="member-hub-pill">
                                                <?php echo htmlspecialchars($ahli['hubungan']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($ahli['status_yuran'] !== STATUS_DIBAYAR): ?>
                                    <div style="margin-top:.75rem; padding-top:.65rem; border-top: 1px solid rgba(0,0,0,.06); display:flex; justify-content:flex-end;">
                                        <a
                                            href="payment.php?type=khairat&khairat_id=<?php echo (int)$ahli['id']; ?>"
                                            class="btn-pay"
                                        >
                                            <i class="fas fa-credit-card"></i>
                                            Bayar RM<?php echo YURAN_KHAIRAT; ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:.75rem; padding-top:.65rem; border-top: 1px solid rgba(0,0,0,.06); display:flex; justify-content:flex-end;">
                                        <a
                                            href="#"
                                            onclick="openReceiptModal('khairat', <?php echo (int)$ahli['id']; ?>); return false;"
                                            class="btn-pay"
                                            style="background: var(--slate-600); cursor: pointer;"
                                            onmouseover="this.style.background='var(--slate-700)'"
                                            onmouseout="this.style.background='var(--slate-600)'"
                                        >
                                            <i class="fas fa-file-invoice"></i>
                                            Lihat Resit
                                        </a>
                                    </div>
                                <?php endif; ?>

                            </div><!-- /member-card -->

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4>Tiada Ahli Lagi</h4>
                            <p>Ahli yang berjaya didaftarkan akan muncul di sini.</p>
                        </div>

                    <?php endif; ?>

                </div><!-- /member-list -->

            </div><!-- /card -->
        </div><!-- /RIGHT -->

    </div><!-- /content-grid -->
    <footer class="text-center text-slate-400 text-[10px] mt-10 tracking-widest uppercase pb-6">
        &copy; 2026 SmartGrave Bangi Lama. Sistem Pengurusan Jenazah Digital Patuh Syariah.
    </footer>
</main>

<!-- =====================================================
     SCRIPTS
===================================================== -->
<script>
// ============================================================
// DATA WARIS — inject dari PHP ke JS
// ============================================================
const dataWaris = {
    nama:    <?php echo json_encode($data_waris['nama']    ?? ''); ?>,
    no_ic:   <?php echo json_encode($data_waris['no_ic']   ?? ''); ?>,
    telefon: <?php echo json_encode($data_waris['telefon'] ?? ''); ?>,
    alamat:  <?php echo json_encode($data_waris['alamat']  ?? ''); ?>
};


// ============================================================
// IC auto-format: 000000-00-0000
const icInput = document.getElementById('noIc');
const icHint  = document.getElementById('icHint');

icInput.addEventListener('input', function () {
    let digits = this.value.replace(/\D/g, '').slice(0, 12);
    let formatted = digits;
    if (digits.length > 6) formatted = digits.slice(0,6) + '-' + digits.slice(6);
    if (digits.length > 8) formatted = digits.slice(0,6) + '-' + digits.slice(6,8) + '-' + digits.slice(8);
    this.value = formatted;

    const len = digits.length;
    if (len === 12) {
        icHint.textContent = '✓ Nombor IC lengkap';
        icHint.style.color = 'var(--green-700)';
    } else {
        icHint.textContent = len + ' / 12 digit';
        icHint.style.color = 'var(--slate-400)';
    }
});

// Nama uppercase live
document.querySelector('[name="nama_ahli"]').addEventListener('input', function () {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});

// ============================================================
// AUTO-FILL helper
// ============================================================
function formatIcDisplay(ic) {
    const digits = ic.replace(/\D/g, '').slice(0, 12);
    let formatted = digits;
    if (digits.length > 6) formatted = digits.slice(0,6) + '-' + digits.slice(6);
    if (digits.length > 8) formatted = digits.slice(0,6) + '-' + digits.slice(6,8) + '-' + digits.slice(8);
    return formatted;
}

function fillDiriSendiri() {
    const fNama   = document.querySelector('[name="nama_ahli"]');
    const fIc     = document.getElementById('noIc');
    const fTel    = document.querySelector('[name="telefon"]');
    const fAlamat = document.querySelector('[name="alamat"]');

    fNama.value   = dataWaris.nama;
    fTel.value    = dataWaris.telefon;
    fAlamat.value = dataWaris.alamat;

    // IC — format & trigger hint update
    fIc.value = formatIcDisplay(dataWaris.no_ic);
    fIc.dispatchEvent(new Event('input'));

    // Visual flash to indicate autofill
    [fNama, fIc, fTel, fAlamat].forEach(el => {
        el.style.transition = 'background .3s';
        el.style.background = 'rgba(34,197,94,.08)';
        setTimeout(() => { el.style.background = ''; }, 1000);
    });
}

function clearDiriSendiri() {
    const fNama   = document.querySelector('[name="nama_ahli"]');
    const fIc     = document.getElementById('noIc');
    const fTel    = document.querySelector('[name="telefon"]');
    const fAlamat = document.querySelector('[name="alamat"]');

    // Only clear if still showing waris data (avoid clearing user edits for other selections)
    fNama.value   = '';
    fIc.value     = '';
    fTel.value    = '';
    fAlamat.value = '';

    icHint.textContent = 'Masukkan 12 digit nombor IC';
    icHint.style.color = 'var(--slate-400)';
}

// ============================================================
// Toggle Hubungan Lain-lain + Diri Sendiri auto-fill
// ============================================================
let lastHubungan = '';

function toggleHubunganLain() {
    const select = document.getElementById('hubunganSelect');
    const box    = document.getElementById('hubunganLainBox');
    const input  = document.getElementById('hubunganLainInput');
    const val    = select.value;

    // Handle Lain-lain reveal
    if (val === 'Lain-lain') {
        box.classList.add('active');
        input.required = true;
    } else {
        box.classList.remove('active');
        input.required = false;
        input.value = '';
    }

    // Handle Diri Sendiri auto-fill
    if (val === 'Diri Sendiri') {
        fillDiriSendiri();
    } else if (lastHubungan === 'Diri Sendiri') {
        // Was diri sendiri, now changed — clear the autofilled fields
        clearDiriSendiri();
    }

    lastHubungan = val;
}

// Toggle Pekerjaan Lain-lain
function togglePekerjaanLain() {
    const select = document.getElementById('pekerjaanSelect');
    const box    = document.getElementById('pekerjaanLainBox');
    const input  = document.getElementById('pekerjaanLainInput');

    if (select.value === 'Lain-lain') {
        box.classList.add('active');
        input.required = true;
    } else {
        box.classList.remove('active');
        input.required = false;
        input.value = '';
    }
}

// Basic client-side validation before submit
document.getElementById('formKhairat').addEventListener('submit', function (e) {
    const ic     = document.getElementById('noIc').value.replace(/\D/g, '');
    const nama   = document.querySelector('[name="nama_ahli"]').value.trim();
    const hub    = document.getElementById('hubunganSelect').value;

    if (ic.length !== 12) {
        e.preventDefault();
        alert('No. IC mesti mengandungi tepat 12 digit.');
        icInput.focus();
        return;
    }

    if (!nama) {
        e.preventDefault();
        alert('Sila masukkan nama penuh.');
        return;
    }

    if (!hub) {
        e.preventDefault();
        alert('Sila pilih hubungan.');
        return;
    }

    if (hub === 'Lain-lain' && !document.getElementById('hubunganLainInput').value.trim()) {
        e.preventDefault();
        alert('Sila nyatakan hubungan.');
        return;
    }
});
</script>

</body>
</html>