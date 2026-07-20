<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (!isset($_GET['type']) || $_GET['type'] !== 'infaq') {
        header("Location: login.php");
        exit();
    }
}

define('STATUS_DIBAYAR',   'Dibayar');
define('STATUS_TUNGGAKAN', 'Tunggakan');
define('YURAN_KHAIRAT',    60);
define('YURAN_BOOKING',    1100);

$title = "Pembayaran";
include 'header.php';
if (isset($_SESSION['user_id']) && (trim($_GET['type'] ?? $_POST['type'] ?? '') !== 'infaq')) {
    include 'sidebar.php';
}

$error   = "";
$success = "";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $type         = trim($_GET['type'] ?? '');
    $reference_no = 'SG-' . strtoupper(date('Ymd')) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    $amaun_bayaran = 0;
    $data          = null;
    $page_title    = "Pembayaran";
    $page_desc     = "";
    $is_paid       = false;

    // ============================================================
    // LOAD DATA IKUT TYPE
    // ============================================================
    if ($type === 'khairat') {

        $khairat_id = (int)($_GET['khairat_id'] ?? 0);
        if (!$khairat_id) throw new InvalidArgumentException("ID Khairat tidak sah.");

        // FK dah tukar: id_waris → user_id
        $stmt = $pdo->prepare("
            SELECT dk.*, u.full_name AS nama_waris
            FROM daftar_khairat dk
            LEFT JOIN users u ON u.id = dk.user_id
            WHERE dk.id = ?
            LIMIT 1
        ");
        $stmt->execute([$khairat_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) throw new InvalidArgumentException("Rekod khairat tidak dijumpai.");
        if ((int)$data['user_id'] !== (int)$_SESSION['user_id'])
            throw new InvalidArgumentException("Akses tidak dibenarkan.");

        $amaun_bayaran = ($data['status_yuran'] === STATUS_DIBAYAR) ? 0 : YURAN_KHAIRAT;
        $is_paid       = ($data['status_yuran'] === STATUS_DIBAYAR);
        $page_title    = "Yuran Khairat Kematian";
        $page_desc     = "Pembayaran yuran tahunan bagi ahli: " . htmlspecialchars($data['nama_ahli']);

    } elseif ($type === 'booking') {

        // URL param tukar: id_tempahan → tempahan_id (ikut redirect dari daftar_jenazah.php)
        $tempahan_id = (int)($_GET['tempahan_id'] ?? 0);
        if (!$tempahan_id) throw new InvalidArgumentException("ID Tempahan tidak sah.");

        // FK dah tukar: id_jenazah → jenazah_id, id_waris → user_id
        // PK dah tukar: id_jenazah → id, id_tempahan → id
        $stmt = $pdo->prepare("
            SELECT mj.*, t.id AS tempahan_id, t.user_id, t.status_proses,
                   u.full_name AS nama_waris
            FROM tempahan t
            JOIN maklumat_jenazah mj ON mj.id = t.jenazah_id
            JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$tempahan_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) throw new InvalidArgumentException("Rekod tempahan tidak dijumpai.");
        if ((int)$data['user_id'] !== (int)$_SESSION['user_id'])
            throw new InvalidArgumentException("Akses tidak dibenarkan.");

        // Check if the deceased (jenazah) is a registered and paid khairat member
        $stmt_check_jenazah = $pdo->prepare("
            SELECT COUNT(*) 
            FROM daftar_khairat 
            WHERE no_ic = ? AND status_yuran = 'Dibayar'
        ");
        $stmt_check_jenazah->execute([$data['no_ic']]);
        $is_jenazah_ahli = $stmt_check_jenazah->fetchColumn() > 0;

        if ($is_jenazah_ahli) {
            $amaun_bayaran = 0;
        } else {
            $amaun_bayaran = YURAN_BOOKING;
        }

        $page_title    = "Pembayaran Tempahan Lot Pusara";
        $page_desc     = "Bagi jenazah: " . htmlspecialchars($data['nama_jenazah']) . ($is_jenazah_ahli ? " <span class='text-emerald-600 font-bold'>(Ahli Khairat - Ditanggung Sepenuhnya)</span>" : "");

    } elseif ($type === 'infaq') {
        $infaq_amount = floatval($_GET['amount'] ?? 0);
        if ($infaq_amount <= 0) {
            $infaq_amount = floatval($_POST['infaq_amount'] ?? 0);
        }
        if ($infaq_amount <= 0) {
            throw new InvalidArgumentException("Jumlah sumbangan infaq tidak sah.");
        }

        $nama_penderma = trim($_GET['name'] ?? '');
        if (empty($nama_penderma)) {
            $nama_penderma = trim($_POST['infaq_nama_penderma'] ?? '');
        }
        if (empty($nama_penderma)) {
            $nama_penderma = 'Hamba Allah';
        }

        $data = [
            'nama_penderma' => $nama_penderma,
            'jumlah' => $infaq_amount
        ];
        $amaun_bayaran = $infaq_amount;
        $page_title = "Sumbangan Infaq Digital";
        $page_desc = "Sumbangan seikhlas hati untuk menampung kos pengurusan jenazah golongan yang memerlukan.";

    } else {
        throw new InvalidArgumentException("Jenis transaksi tidak dikenali.");
    }

    // ============================================================
    // PROSES PEMBAYARAN — POST
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayaran'])) {

        $kaedah = trim($_POST['kaedah_bayaran'] ?? '');
        if (empty($kaedah)) throw new InvalidArgumentException("Sila pilih kaedah pembayaran.");

        $pdo->beginTransaction();

        // --------------------------------------------------------
        // KHAIRAT
        // --------------------------------------------------------
        if ($type === 'khairat' && !$is_paid) {

            // PK dah tukar: id_khairat → id
            $pdo->prepare("
                UPDATE daftar_khairat SET status_yuran = ? WHERE id = ?
            ")->execute([STATUS_DIBAYAR, $khairat_id]);

            // FK bayaran dah tukar: id_khairat → khairat_id, id_tempahan → tempahan_id
            $pdo->prepare("
                INSERT INTO bayaran (khairat_id, tempahan_id, jumlah, kaedah_bayaran, tarikh_transaksi, bukti_bayaran)
                VALUES (?, NULL, ?, ?, NOW(), NULL)
            ")->execute([$khairat_id, YURAN_KHAIRAT, $kaedah]);

            // Update user status khairat as well if registering self
            if (isset($data['hubungan']) && (strcasecmp($data['hubungan'], 'Diri Sendiri') === 0)) {
                $pdo->prepare("UPDATE users SET status_khairat = true WHERE id = ?")->execute([$_SESSION['user_id']]);
            }

            $pdo->commit();
            $payment_success = true;
            $success_type = 'khairat';
            $success_id = $khairat_id;
        }

        // --------------------------------------------------------
        // BOOKING
        // --------------------------------------------------------
        elseif ($type === 'booking') {

            // PK dah tukar: id_tempahan → id
            $pdo->prepare("
                UPDATE tempahan SET status_proses = 'Bayaran Berjaya', updated_at = NOW() WHERE id = ?
            ")->execute([$tempahan_id]);

            // FK bayaran dah tukar: id_tempahan → tempahan_id, id_khairat → khairat_id
            $pdo->prepare("
                INSERT INTO bayaran (tempahan_id, khairat_id, jumlah, kaedah_bayaran, tarikh_transaksi, bukti_bayaran)
                VALUES (?, NULL, ?, ?, NOW(), NULL)
            ")->execute([$tempahan_id, $amaun_bayaran, $kaedah]);

            $pdo->commit();
            $payment_success = true;
            $success_type = 'booking';
            $success_id = $tempahan_id;
        }
        elseif ($type === 'infaq') {
            $infaq_amount = floatval($_POST['infaq_amount'] ?? 0);
            $nama_penderma = trim($_POST['infaq_nama_penderma'] ?? 'Hamba Allah');
            $email = isset($_GET['email']) ? trim($_GET['email']) : null;
            $phone = isset($_GET['phone']) ? trim($_GET['phone']) : null;

            if ($infaq_amount <= 0) throw new InvalidArgumentException("Jumlah sumbangan tidak sah.");

            // 1. Insert into infaq table
            $stmt_infaq = $pdo->prepare("
                INSERT INTO infaq (nama_penderma, email, no_telefon, jumlah, kaedah_bayaran, tarikh_transaksi, no_rujukan)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt_infaq->execute([$nama_penderma, $email, $phone, $infaq_amount, $kaedah, $reference_no]);
            $infaq_id = $pdo->lastInsertId();

            // 2. Insert into bayaran table to make sure it shows in dashboard / reports
            $stmt_bayaran = $pdo->prepare("
                INSERT INTO bayaran (tempahan_id, khairat_id, infaq_id, jumlah, kaedah_bayaran, tarikh_transaksi, bukti_bayaran)
                VALUES (NULL, NULL, ?, ?, ?, NOW(), NULL)
            ");
            $stmt_bayaran->execute([$infaq_id, $infaq_amount, $kaedah]);

            $pdo->commit();
            $payment_success = true;
            $success_type = 'infaq';
            $success_id = $infaq_id;
        }
    }

} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = "Ralat sistem: " . $e->getMessage();
}
?>

<style>
:root {
    --green-50:  #f0fdf4; --green-100: #dcfce7; --green-200: #bbf7d0;
    --green-500: #22c55e; --green-600: #16a34a; --green-700: #15803d;
    --green-800: #166534; --green-900: #14532d;
    --slate-50:  #f8fafc; --slate-100: #f1f5f9; --slate-200: #e2e8f0;
    --slate-300: #cbd5e1; --slate-400: #94a3b8; --slate-500: #64748b;
    --slate-600: #475569; --slate-700: #334155; --slate-800: #1e293b;
    --red-50: #fef2f2; --red-100: #fee2e2; --red-600: #dc2626; --red-700: #b91c1c;
    --amber-100: #fef3c7; --amber-600: #d97706;
    --font: 'Plus Jakarta Sans', sans-serif;
    --r-sm: 10px; --r-md: 16px; --r-lg: 24px; --r-xl: 32px;
    --sh-sm: 0 1px 3px rgba(0,0,0,.06); --sh-md: 0 4px 16px rgba(0,0,0,.07);
}
.page-main { flex: 1; padding: 2rem 2.5rem 4rem; overflow-y: auto; }
@media (max-width: 768px) { .page-main { padding: 1.25rem 1rem 3rem; } }

.back-link { display: inline-flex; align-items: center; gap: .45rem; font-size: .82rem; font-weight: 600; color: var(--slate-500); text-decoration: none; margin-bottom: 1.5rem; transition: color .15s; }
.back-link:hover { color: var(--green-700); }

.page-header { margin-bottom: 2rem; }
.page-header h1 { font-size: 1.75rem; font-weight: 800; color: var(--green-900); line-height: 1.2; }
.page-header p { font-size: .88rem; color: var(--slate-500); margin-top: .35rem; }

.alert { display: flex; align-items: flex-start; gap: .85rem; border-radius: var(--r-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: .875rem; font-weight: 500; }
.alert-error { background: var(--red-50); border: 1px solid var(--red-100); color: var(--red-700); }

.payment-layout { display: grid; grid-template-columns: 1fr 360px; gap: 1.75rem; align-items: start; max-width: 960px; }
@media (max-width: 900px) { .payment-layout { grid-template-columns: 1fr; } }

.card { background: #fff; border-radius: var(--r-xl); border: 1px solid var(--slate-200); box-shadow: var(--sh-md); overflow: hidden; }
.card-header { padding: 1.4rem 1.75rem; background: rgba(22,163,74,.04); border-bottom: 1px solid var(--green-100); display: flex; align-items: center; gap: .75rem; }
.card-header-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--green-100); display: flex; align-items: center; justify-content: center; color: var(--green-700); font-size: .9rem; flex-shrink: 0; }
.card-header h3 { font-size: 1rem; font-weight: 800; color: var(--green-900); }
.card-body { padding: 1.75rem; }

.ref-badge { display: inline-flex; align-items: center; gap: .5rem; background: var(--slate-100); border: 1px solid var(--slate-200); border-radius: 8px; padding: .45rem .85rem; font-size: .75rem; font-weight: 700; color: var(--slate-600); letter-spacing: .04em; margin-bottom: 1.5rem; }

.detail-table { width: 100%; border-collapse: collapse; margin-bottom: 1.25rem; }
.detail-table tr { border-bottom: 1px solid var(--slate-100); }
.detail-table tr:last-child { border-bottom: none; }
.detail-table td { padding: .65rem 0; font-size: .875rem; vertical-align: middle; }
.detail-table td:first-child { color: var(--slate-400); font-weight: 600; width: 45%; font-size: .8rem; }
.detail-table td:last-child { color: var(--slate-800); font-weight: 600; text-align: right; }

.divider { border: none; border-top: 1.5px solid var(--slate-200); margin: 1.25rem 0; }

.total-row { display: flex; align-items: center; justify-content: space-between; background: var(--green-50); border: 1px solid var(--green-200); border-radius: var(--r-md); padding: 1rem 1.25rem; margin: 1.25rem 0; }
.total-row .total-label { font-size: .8rem; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: .06em; }
.total-row .total-amount { font-size: 1.5rem; font-weight: 800; color: var(--green-800); }

.paid-badge { display: flex; align-items: center; gap: .75rem; background: var(--green-50); border: 1.5px solid var(--green-200); border-radius: var(--r-md); padding: 1rem 1.25rem; color: var(--green-800); font-size: .9rem; font-weight: 700; margin: 1.25rem 0; }

.method-label { font-size: .72rem; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .75rem; margin-top: 1.25rem; }
.method-options { display: flex; flex-direction: column; gap: .6rem; }
.method-option { display: flex; align-items: center; gap: .85rem; padding: .85rem 1.1rem; border: 1.5px solid var(--slate-200); border-radius: var(--r-sm); cursor: pointer; transition: border-color .15s, background .15s; font-size: .875rem; font-weight: 600; color: var(--slate-700); user-select: none; }
.method-option:hover { border-color: var(--green-400); background: var(--green-50); }
.method-option input[type="radio"] { display: none; }
.method-icon { width: 38px; height: 38px; border-radius: 9px; background: var(--slate-100); display: flex; align-items: center; justify-content: center; color: var(--slate-500); font-size: .95rem; flex-shrink: 0; }
.method-option.selected { border-color: var(--green-500); background: var(--green-50); }
.method-option.selected .method-icon { background: var(--green-100); color: var(--green-700); }
.method-check { width: 18px; height: 18px; border: 2px solid var(--slate-300); border-radius: 50%; margin-left: auto; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all .15s; }
.method-option.selected .method-check { border-color: var(--green-600); background: var(--green-600); }
.method-option.selected .method-check::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #fff; }

.btn-primary { display: flex; align-items: center; justify-content: center; gap: .6rem; width: 100%; padding: 1rem 1.5rem; background: linear-gradient(135deg, var(--green-600), var(--green-700)); color: #fff; font-family: var(--font); font-size: .95rem; font-weight: 700; border: none; border-radius: var(--r-md); cursor: pointer; transition: transform .15s, box-shadow .15s, filter .15s; box-shadow: 0 4px 16px rgba(22,163,74,.3); margin-top: 1.5rem; }
.btn-primary:hover { filter: brightness(1.05); box-shadow: 0 6px 24px rgba(22,163,74,.35); transform: translateY(-1px); }
.btn-primary:disabled { opacity: .7; cursor: not-allowed; transform: none; }
.btn-back { display: flex; align-items: center; justify-content: center; gap: .6rem; width: 100%; padding: .85rem 1.5rem; background: var(--slate-100); color: var(--slate-700); font-family: var(--font); font-size: .9rem; font-weight: 700; border: 1.5px solid var(--slate-200); border-radius: var(--r-md); cursor: pointer; transition: background .15s; margin-top: 1rem; text-decoration: none; }
.btn-back:hover { background: var(--slate-200); }

.security-note { display: flex; align-items: center; gap: .5rem; justify-content: center; margin-top: .85rem; font-size: .72rem; color: var(--slate-400); }

.summary-item { display: flex; justify-content: space-between; padding: .55rem 0; font-size: .845rem; border-bottom: 1px solid var(--slate-100); }
.summary-item:last-of-type { border-bottom: none; }
.summary-item .s-label { color: var(--slate-500); }
.summary-item .s-value { font-weight: 700; color: var(--slate-800); }
.summary-total { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--slate-200); }
.summary-total .t-label { font-size: .8rem; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: .06em; }
.summary-total .t-amount { font-size: 1.4rem; font-weight: 800; color: var(--green-800); }
.benefit-item { display: flex; align-items: center; gap: .6rem; margin-bottom: .55rem; font-size: .82rem; color: var(--slate-600); }

.status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .75rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
.status-aktif   { background: var(--green-100); color: var(--green-800); }
.status-belum   { background: var(--red-100); color: var(--red-700); }
.status-tunggak { background: var(--amber-100); color: var(--amber-600); }

/* FPX Bank buttons layout */
.fpx-bank-btn.active {
    background-color: var(--green-50) !important;
    border-color: var(--green-500) !important;
    box-shadow: 0 0 0 2px var(--green-100);
}
</style>

<?php if ((trim($_GET['type'] ?? $_POST['type'] ?? '')) === 'infaq'): ?>
<style>
    .page-main {
        max-width: 960px;
        margin: 0 auto;
        width: 100%;
        padding: 3rem 2rem 5rem;
    }
</style>
<?php endif; ?>

<main class="page-main">

    <!-- BACK LINK -->
    <?php if ($type === 'khairat'): ?>
        <a href="daftar_khairat.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Khairat</a>
    <?php elseif ($type === 'booking'): ?>
        <a href="booking.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Borang Tempahan</a>
    <?php elseif ($type === 'infaq'): ?>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Laman Utama</a>
    <?php endif; ?>

    <!-- ERROR -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-triangle-exclamation"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$error && $data): ?>

    <div class="page-header">
        <h1><?php echo $page_title; ?></h1>
        <?php if ($page_desc): ?><p><?php echo $page_desc; ?></p><?php endif; ?>
    </div>

    <div class="payment-layout">

        <!-- LEFT: FORM BAYARAN -->
        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon"><i class="fas fa-receipt"></i></div>
                    <h3>Maklumat Pembayaran</h3>
                </div>
                <div class="card-body">

                    <div class="ref-badge">
                        <i class="fas fa-hashtag"></i>
                        No. Rujukan: <?php echo $reference_no; ?>
                    </div>

                    <!-- Detail table -->
                    <?php if ($type === 'khairat'): ?>
                    <table class="detail-table">
                        <tr><td>Jenis Bayaran</td><td>Yuran Khairat Kematian</td></tr>
                        <tr><td>Nama Ahli</td><td><?php echo htmlspecialchars($data['nama_ahli']); ?></td></tr>
                        <tr><td>No. IC</td><td><?php echo htmlspecialchars($data['no_ic']); ?></td></tr>
                        <tr><td>Hubungan</td><td><?php echo htmlspecialchars($data['hubungan']); ?></td></tr>
                        <tr>
                            <td>Status</td>
                            <td>
                                <?php
                                $sc = $data['status_yuran'] === STATUS_DIBAYAR ? 'status-aktif'
                                    : ($data['status_yuran'] === STATUS_TUNGGAKAN ? 'status-tunggak' : 'status-belum');
                                ?>
                                <span class="status-badge <?php echo $sc; ?>"><?php echo htmlspecialchars($data['status_yuran']); ?></span>
                            </td>
                        </tr>
                    </table>

                    <?php elseif ($type === 'booking'): ?>
                    <table class="detail-table">
                        <tr><td>Jenis Bayaran</td><td>Tempahan Lot Pusara</td></tr>
                        <tr><td>Nama Jenazah</td><td><?php echo htmlspecialchars($data['nama_jenazah']); ?></td></tr>
                        <tr><td>No. IC</td><td><?php echo htmlspecialchars($data['no_ic']); ?></td></tr>
                        <tr><td>Tarikh Kematian</td><td><?php echo $data['tarikh_wafat'] ? date('d M Y', strtotime($data['tarikh_wafat'])) : '—'; ?></td></tr>
                        <tr><td>Lokasi Kematian</td><td><?php echo htmlspecialchars($data['lokasi_wafat']); ?></td></tr>
                    </table>
                    <?php elseif ($type === 'infaq'): ?>
                    <table class="detail-table">
                        <tr><td>Jenis Transaksi</td><td>Infaq Masjid & Tabung Kebajikan</td></tr>
                        <tr><td>Penderma</td><td><?php echo htmlspecialchars(strtoupper($data['nama_penderma'])); ?></td></tr>
                        <tr><td>Jumlah Sumbangan</td><td>RM <?php echo number_format($data['jumlah'], 2); ?></td></tr>
                    </table>
                    <?php endif; ?>

                    <hr class="divider">

                    <?php if ($is_paid): ?>
                        <div class="paid-badge">
                            <i class="fas fa-circle-check" style="font-size:1.25rem;"></i>
                            Yuran telah dibayar. Tiada bayaran diperlukan.
                        </div>
                        <a href="daftar_khairat.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Kembali ke Senarai Ahli
                        </a>
                    <?php else: ?>

                        <div class="total-row">
                            <div class="total-label">Jumlah Bayaran</div>
                            <div class="total-amount">RM <?php echo number_format($amaun_bayaran, 2); ?></div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <input type="hidden" name="proses_bayaran" value="1">
                            <?php if ($type === 'infaq'): ?>
                                <input type="hidden" name="infaq_amount" value="<?php echo htmlspecialchars($data['jumlah']); ?>">
                                <input type="hidden" name="infaq_nama_penderma" value="<?php echo htmlspecialchars($data['nama_penderma']); ?>">
                            <?php endif; ?>

                            <!-- Kaedah bayaran -->
                            <div class="method-label">Kaedah Pembayaran</div>
                            <div class="method-options">

                                <label class="method-option" onclick="selectMethod(this)">
                                    <input type="radio" name="kaedah_bayaran" value="FPX">
                                    <div class="method-icon"><i class="fas fa-landmark"></i></div>
                                    <div>
                                        <div>FPX (Online Banking)</div>
                                        <div style="font-size:.72rem;color:var(--slate-400);font-weight:500;margin-top:.1rem;">Maybank, CIMB, RHB, dll.</div>
                                    </div>
                                    <div class="method-check"></div>
                                </label>

                                <label class="method-option" onclick="selectMethod(this)">
                                    <input type="radio" name="kaedah_bayaran" value="eWallet">
                                    <div class="method-icon"><i class="fas fa-mobile-screen-button"></i></div>
                                    <div>
                                        <div>eWallet</div>
                                        <div style="font-size:.72rem;color:var(--slate-400);font-weight:500;margin-top:.1rem;">Touch 'n Go, GrabPay, Boost</div>
                                    </div>
                                    <div class="method-check"></div>
                                </label>

                                <label class="method-option" onclick="selectMethod(this)">
                                    <input type="radio" name="kaedah_bayaran" value="Kad Kredit/Debit">
                                    <div class="method-icon"><i class="fas fa-credit-card"></i></div>
                                    <div>
                                        <div>Kad Kredit / Debit</div>
                                        <div style="font-size:.72rem;color:var(--slate-400);font-weight:500;margin-top:.1rem;">Visa, Mastercard</div>
                                    </div>
                                    <div class="method-check"></div>
                                </label>

                                <label class="method-option" onclick="selectMethod(this)">
                                    <input type="radio" name="kaedah_bayaran" value="Tunai di Kaunter">
                                    <div class="method-icon"><i class="fas fa-money-bill-wave"></i></div>
                                    <div>
                                        <div>Tunai di Kaunter</div>
                                        <div style="font-size:.72rem;color:var(--slate-400);font-weight:500;margin-top:.1rem;">Bayar terus di pejabat</div>
                                    </div>
                                    <div class="method-check"></div>
                                </label>

                            </div>

                            <button type="submit" class="btn-primary" id="btnBayar">
                                <i class="fas fa-lock"></i>
                                <?php if ($amaun_bayaran > 0): ?>
                                    Bayar RM <?php echo number_format($amaun_bayaran, 2); ?> Sekarang
                                <?php else: ?>
                                    Sahkan Tempahan (Percuma)
                                <?php endif; ?>
                            </button>

                            <div class="security-note">
                                <i class="fas fa-shield"></i>
                                Transaksi ini selamat dan disulitkan
                            </div>

                            <!-- Bantuan Pembayaran WhatsApp Admin -->
                            <div style="margin-top: 1.5rem; padding: 1rem; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; font-size: 0.8rem; text-align: center; color: #166534;">
                                <p style="font-weight: 700; margin-bottom: 0.25rem;">Menghadapi masalah untuk membuat pembayaran?</p>
                                <p style="margin-bottom: 0.5rem; color: #15803d; font-size: 0.75rem;">Sila hubungi pentadbir sistem melalui WhatsApp untuk bantuan segera.</p>
                                <a href="https://wa.me/601126923772?text=Saya%20mengalami%20masalah%20ketika%20membuat%20pembayaran%20yuran/tempahan%20di%20SmartGrave" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: #16a34a; color: white; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 700; text-decoration: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#15803d'" onmouseout="this.style.backgroundColor='#16a34a'">
                                    <i class="fab fa-whatsapp" style="font-size: 1rem;"></i> Hubungi Admin WhatsApp
                                </a>
                            </div>

                        </form>

                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Payment Gateway Modal -->
        <div id="gatewayModal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-emerald-50 m-4">
                <!-- Gateway Header -->
                <div class="bg-emerald-950 text-white p-6 relative">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-emerald-800 text-yellow-400 p-2.5 rounded-xl border border-emerald-700">
                                <i class="fas fa-mosque"></i>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-base leading-tight">SmartGrave SecurePay</h4>
                                <p class="text-[10px] text-emerald-400 uppercase tracking-widest font-bold mt-0.5">Gerbang Pembayaran Rasmi</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeGateway()" class="text-white/60 hover:text-white transition">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    
                    <div class="mt-6 bg-emerald-900/50 p-4 rounded-2xl border border-emerald-850/60 flex justify-between items-center">
                        <div>
                            <p class="text-[9px] text-emerald-300 font-bold uppercase tracking-wider">Penerima</p>
                            <p class="text-xs font-bold text-white">Masjid Kariah Bangi</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-emerald-300 font-bold uppercase tracking-wider">Jumlah Amaun</p>
                            <p class="text-base font-black text-yellow-400">RM <?php echo number_format($amaun_bayaran, 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Gateway Body -->
                <div class="p-6">
                    <!-- STEP 1: Form / Details -->
                    <div id="gatewayStepForm">
                        <!-- FPX Bank Selection -->
                        <div id="gatewayFpx" class="hidden">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Pilih Perbankan Internet (FPX)</p>
                            <div class="grid grid-cols-2 gap-2 mb-6">
                                <button type="button" onclick="selectBank('Maybank2u', this)" class="fpx-bank-btn p-3 border border-slate-200 rounded-xl flex items-center gap-2 hover:bg-emerald-50 hover:border-emerald-300 transition text-left text-xs font-semibold">
                                    <span class="w-2.5 h-2.5 bg-yellow-400 rounded-full flex-shrink-0"></span> Maybank2u
                                </button>
                                <button type="button" onclick="selectBank('CIMB Clicks', this)" class="fpx-bank-btn p-3 border border-slate-200 rounded-xl flex items-center gap-2 hover:bg-emerald-50 hover:border-emerald-300 transition text-left text-xs font-semibold">
                                    <span class="w-2.5 h-2.5 bg-red-600 rounded-full flex-shrink-0"></span> CIMB
                                </button>
                                <button type="button" onclick="selectBank('Bank Islam', this)" class="fpx-bank-btn p-3 border border-slate-200 rounded-xl flex items-center gap-2 hover:bg-emerald-50 hover:border-emerald-300 transition text-left text-xs font-semibold">
                                    <span class="w-2.5 h-2.5 bg-blue-800 rounded-full flex-shrink-0"></span> Bank Islam
                                </button>
                                <button type="button" onclick="selectBank('RHB Now', this)" class="fpx-bank-btn p-3 border border-slate-200 rounded-xl flex items-center gap-2 hover:bg-emerald-50 hover:border-emerald-300 transition text-left text-xs font-semibold">
                                    <span class="w-2.5 h-2.5 bg-blue-600 rounded-full flex-shrink-0"></span> RHB Bank
                                </button>
                            </div>
                        </div>

                        <!-- Card Details -->
                        <div id="gatewayCard" class="hidden mb-6">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Masukkan Maklumat Kad Kredit / Debit</p>
                            <div class="space-y-3">
                                <div>
                                    <input type="text" id="cardNo" placeholder="Nombor Kad (16 digit)" class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-emerald-500 focus:bg-white transition" maxlength="16">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="text" id="cardExpiry" placeholder="MM/YY" class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-emerald-500 focus:bg-white transition" maxlength="5">
                                    <input type="password" id="cardCvv" placeholder="CVV" class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-emerald-500 focus:bg-white transition" maxlength="3">
                                </div>
                            </div>
                        </div>

                        <!-- eWallet Details (QR Code) -->
                        <div id="gatewayEwallet" class="hidden mb-6 text-center">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3">Imbas QR Kod DuitNow</p>
                            <div class="flex flex-col items-center justify-center p-6 border border-slate-100 rounded-2xl bg-slate-50/50 mb-3">
                                <div class="w-40 h-40 bg-white border border-slate-200 rounded-2xl flex items-center justify-center relative p-3 shadow-inner">
                                    <i class="fas fa-qrcode text-8xl text-slate-800"></i>
                                    <div class="absolute inset-0 bg-emerald-500/5 flex items-center justify-center pointer-events-none"></div>
                                </div>
                                <p class="text-[10px] text-slate-500 mt-3 font-semibold">Buka aplikasi Touch 'n Go, GrabPay atau Boost untuk imbas</p>
                            </div>
                        </div>

                        <!-- Cash Instructions -->
                        <div id="gatewayCounter" class="hidden mb-6">
                            <div class="p-4 border border-amber-200 bg-amber-50 rounded-2xl text-xs text-amber-800 space-y-2">
                                <p class="font-bold flex items-center gap-1.5"><i class="fas fa-exclamation-circle text-amber-600"></i> Pembayaran di Kaunter:</p>
                                <p>Sila buat bayaran tunai / kad di kaunter pejabat pengurusan Masjid Kariah Bangi dalam masa <strong class="text-amber-950">3 hari bekerja</strong>.</p>
                                <p>Sila sebutkan No. Rujukan transaksi anda semasa pembayaran.</p>
                            </div>
                        </div>
                        
                        <input type="hidden" id="selectedGatewayBank" value="">
                        
                        <button type="button" onclick="startProcessing()" class="w-full p-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 text-sm">
                            <i class="fas fa-lock"></i> Teruskan Pembayaran
                        </button>
                    </div>
                    
                    <!-- STEP 2: Processing -->
                    <div id="gatewayStepProcessing" class="hidden text-center py-10 space-y-6">
                        <div class="relative w-16 h-16 mx-auto">
                            <div class="w-16 h-16 border-4 border-emerald-100 rounded-full"></div>
                            <div class="absolute top-0 left-0 w-16 h-16 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
                        </div>
                        <div class="space-y-2">
                            <h5 class="font-bold text-slate-800 text-sm" id="processingStatusText">Menghubungkan ke gerbang pembayaran...</h5>
                            <p class="text-[10px] text-slate-400">Sila tunggu sebentar dan jangan tutup tetingkap ini.</p>
                        </div>
                    </div>
                    
                    <!-- STEP 3: Success -->
                    <div id="gatewayStepSuccess" class="hidden text-center py-10 space-y-6">
                        <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-2xl animate-bounce">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="space-y-2">
                            <h5 class="font-black text-lg text-emerald-950">Pembayaran Berjaya!</h5>
                            <p class="text-xs text-slate-400">Menghasilkan resit anda secara automatik...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Gateway Footer -->
                <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between text-[9px] text-slate-400 font-bold uppercase tracking-wider">
                    <span class="flex items-center gap-1"><i class="fas fa-shield-halved text-emerald-600 text-xs"></i> SSL SECURED</span>
                    <span>FPX MEMBER</span>
                </div>
            </div>
        </div>

        <!-- RIGHT: RINGKASAN -->
        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon"><i class="fas fa-list-check"></i></div>
                    <h3>Ringkasan Transaksi</h3>
                </div>
                <div class="card-body">

                    <?php if ($type === 'khairat'): ?>
                        <div class="summary-item"><span class="s-label">Yuran Khairat</span><span class="s-value">RM<?php echo number_format(YURAN_KHAIRAT, 2); ?></span></div>
                        <div class="summary-item"><span class="s-label">Tempoh Perlindungan</span><span class="s-value">1 Tahun</span></div>
                        <div class="summary-item"><span class="s-label">Jenis Perlindungan</span><span class="s-value">Khairat Kematian</span></div>
                    <?php elseif ($type === 'booking'): ?>
                        <div class="summary-item"><span class="s-label">Pengurusan Jenazah</span><span class="s-value">RM950.00</span></div>
                        <div class="summary-item"><span class="s-label">Van jenazah</span><span class="s-value">RM50.00</span></div>
                        <div class="summary-item"><span class="s-label">Pentadbiran</span><span class="s-value">RM100.00</span></div>
                    <?php elseif ($type === 'infaq'): ?>
                        <div class="summary-item"><span class="s-label">Jenis Transaksi</span><span class="s-value">Sumbangan Infaq</span></div>
                        <div class="summary-item"><span class="s-label">Penderma</span><span class="s-value"><?php echo htmlspecialchars($data['nama_penderma'] ?? 'HAMBA ALLAH'); ?></span></div>
                        <div class="summary-item"><span class="s-label">Tujuan</span><span class="s-value">Tabung Kebajikan Kariah</span></div>
                    <?php endif; ?>

                    <div class="summary-total">
                        <span class="t-label">Jumlah</span>
                        <span class="t-amount">
                            <?php if ($is_paid): ?>
                                <span style="font-size:.9rem;color:var(--green-700);">Telah Dibayar</span>
                            <?php else: ?>
                                RM<?php echo number_format($amaun_bayaran, 2); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--slate-100);">
                        <div style="font-size:.72rem;font-weight:700;color:var(--slate-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem;">
                            <?php echo $type === 'infaq' ? 'Manfaat & Kelebihan Infaq' : 'Termasuk Dalam Pakej'; ?>
                        </div>
                        <?php
                        if ($type === 'khairat') {
                            $benefits = ['Pengurusan jenazah percuma', 'Kafan & mandian', 'Pengurusan lot pusara', 'Sokongan 24 jam'];
                        } elseif ($type === 'booking') {
                            $benefits = ['Pengurusan penuh jenazah', 'Kafan & mandian', 'Van jenazah', 'Pengkebumian teratur'];
                        } else {
                            $benefits = ['Menyumbang jariah berterusan', 'Membantu keluarga kurang mampu', 'Menyelesaikan fardu kifayah kariah', 'Pembersih harta & jiwa'];
                        }
                        foreach ($benefits as $b):
                        ?>
                        <div class="benefit-item">
                            <i class="fas <?php echo $type === 'infaq' ? 'fa-heart text-rose-500' : 'fa-circle-check text-emerald-500'; ?>" style="font-size:.75rem;flex-shrink:0;"></i>
                            <?php echo $b; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /payment-layout -->

    <?php endif; ?>

</main>

<script>
// Pilih kaedah bayaran
function selectMethod(el) {
    document.querySelectorAll('.method-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type="radio"]').checked = true;
}

// Global variable to keep track of selected bank
let selectedBankName = "";

function selectBank(bankName, btn) {
    document.querySelectorAll('.fpx-bank-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedBankName = bankName;
    document.getElementById('selectedGatewayBank').value = bankName;
}

// Open Gateway Modal
function openGateway() {
    const modal = document.getElementById('gatewayModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Reset steps
    document.getElementById('gatewayStepForm').classList.remove('hidden');
    document.getElementById('gatewayStepProcessing').classList.add('hidden');
    document.getElementById('gatewayStepSuccess').classList.add('hidden');
    
    // Get chosen payment method
    const radios = document.querySelectorAll('[name="kaedah_bayaran"]');
    let chosen = "";
    radios.forEach(r => {
        if (r.checked) chosen = r.value;
    });
    
    // Hide all gateway panels
    document.getElementById('gatewayFpx').classList.add('hidden');
    document.getElementById('gatewayCard').classList.add('hidden');
    document.getElementById('gatewayEwallet').classList.add('hidden');
    document.getElementById('gatewayCounter').classList.add('hidden');
    
    // Show corresponding panel
    if (chosen === 'FPX') {
        document.getElementById('gatewayFpx').classList.remove('hidden');
    } else if (chosen === 'Kad Kredit/Debit') {
        document.getElementById('gatewayCard').classList.remove('hidden');
    } else if (chosen === 'eWallet') {
        document.getElementById('gatewayEwallet').classList.remove('hidden');
    } else if (chosen === 'Tunai di Kaunter') {
        document.getElementById('gatewayCounter').classList.remove('hidden');
    }
}

function closeGateway() {
    const modal = document.getElementById('gatewayModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function startProcessing() {
    // Validate fields if card
    const radios = document.querySelectorAll('[name="kaedah_bayaran"]');
    let chosen = "";
    radios.forEach(r => { if (r.checked) chosen = r.value; });
    
    if (chosen === 'FPX' && !selectedBankName) {
        alert('Sila pilih bank anda terlebih dahulu.');
        return;
    }
    
    if (chosen === 'Kad Kredit/Debit') {
        const cardNo = document.getElementById('cardNo').value;
        const cardExpiry = document.getElementById('cardExpiry').value;
        const cardCvv = document.getElementById('cardCvv').value;
        if (!cardNo || cardNo.length < 16 || !cardExpiry || !cardCvv) {
            alert('Sila isi maklumat kad yang lengkap.');
            return;
        }
    }
    
    // Switch to step 2
    document.getElementById('gatewayStepForm').classList.add('hidden');
    document.getElementById('gatewayStepProcessing').classList.remove('hidden');
    
    const statuses = [
        "Menghubungkan ke gerbang perbankan...",
        "Mengesahkan transaksi selamat...",
        "Menunggu kelulusan bank anda...",
        "Transaksi sedang diproses..."
    ];
    
    let statusIndex = 0;
    const interval = setInterval(() => {
        if (statusIndex < statuses.length - 1) {
            statusIndex++;
            document.getElementById('processingStatusText').innerText = statuses[statusIndex];
        }
    }, 800);
    
    setTimeout(() => {
        clearInterval(interval);
        // Switch to step 3 (Success)
        document.getElementById('gatewayStepProcessing').classList.add('hidden');
        document.getElementById('gatewayStepSuccess').classList.remove('hidden');
        
        setTimeout(() => {
            // Submit form to server
            document.getElementById('paymentForm').submit();
        }, 1500);
    }, 3200);
}

// Validate sebelum submit & show simulated gateway
document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const radios  = document.querySelectorAll('[name="kaedah_bayaran"]');
    const checked = [...radios].some(r => r.checked);

    if (!checked) {
        alert('Sila pilih kaedah pembayaran terlebih dahulu.');
        return;
    }
    
    const amaun = <?php echo (float)$amaun_bayaran; ?>;
    if (amaun === 0) {
        // Free, submit directly with a quick processing spinner on button
        const btn = document.getElementById('btnBayar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }
        document.getElementById('paymentForm').submit();
    } else {
        openGateway();
    }
});
</script>

<?php if (isset($payment_success) && $payment_success): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    openReceiptModal('<?= $success_type ?>', <?= $success_id ?>);
});
function closeReceiptModal() {
    window.location.href = "<?= ($success_type === 'booking') ? 'waris_dashboard.php' : (($success_type === 'khairat') ? 'daftar_khairat.php' : 'index.php') ?>";
}
</script>
<?php endif; ?>
</body>
</html>