<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Support both 'booking' and 'khairat'
$type = $_GET['type'] ?? 'booking';

if (!isset($_GET['id'])) {
    die("ID tidak sah");
}

$id = (int)$_GET['id'];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($type === 'khairat') {
        // Query Khairat Receipt
        $stmt = $pdo->prepare("
            SELECT 
                dk.id,
                dk.status_yuran,
                dk.tarikh_daftar,
                dk.nama_ahli,
                dk.no_ic,
                dk.telefon,
                dk.alamat,
                dk.hubungan,
                b.jumlah,
                b.kaedah_bayaran,
                b.tarikh_transaksi,
                u.full_name AS nama_waris,
                u.no_telefon AS tel_waris
            FROM daftar_khairat dk
            LEFT JOIN bayaran b ON b.khairat_id = dk.id
            LEFT JOIN users u ON u.id = dk.user_id
            WHERE dk.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Resit tidak dijumpai");
        }

        $nama_arwah_atau_ahli = $data['nama_ahli'];
        $no_ic_arwah_atau_ahli = $data['no_ic'];
        $label_nama = "Nama Ahli";
        $label_ic = "No. Kad Pengenalan";
        $extra_info = [
            'Hubungan' => $data['hubungan'],
            'No. Telefon' => $data['telefon'],
            'Alamat' => $data['alamat']
        ];
        
        $status_label = ($data['status_yuran'] === 'Dibayar') ? 'LULUS / AKTIF' : $data['status_yuran'];
        $no_resit = "RCPT-K-" . date('Ymd') . "-" . str_pad($id, 5, '0', STR_PAD_LEFT);
        
        $back_url = "daftar_khairat.php";
        $back_label = "Kembali ke Senarai Ahli";

    } else {
        // Query Booking Receipt
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.status_proses,
                t.tarikh_mohon,
                mj.nama_jenazah,
                mj.no_ic,
                mj.tarikh_wafat,
                mj.masa_wafat,
                mj.lokasi_wafat,
                b.jumlah,
                b.kaedah_bayaran,
                b.tarikh_transaksi,
                u.full_name AS nama_waris,
                u.no_telefon AS tel_waris
            FROM tempahan t
            JOIN maklumat_jenazah mj ON mj.id = t.jenazah_id
            LEFT JOIN bayaran b ON b.tempahan_id = t.id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Resit tidak dijumpai");
        }

        $nama_arwah_atau_ahli = $data['nama_jenazah'];
        $no_ic_arwah_atau_ahli = $data['no_ic'];
        $label_nama = "Nama Arwah";
        $label_ic = "No. Kad Pengenalan Arwah";
        $extra_info = [
            'Tarikh Wafat' => $data['tarikh_wafat'] ? date('d M Y', strtotime($data['tarikh_wafat'])) : '—',
            'Lokasi Pengebumian' => $data['lokasi_wafat'] ? $data['lokasi_wafat'] : '—'
        ];
        
        $status_label = $data['status_proses'];
        // mapping status text
        if (strcasecmp($status_label, 'Bayaran Berjaya') === 0 || strcasecmp($status_label, 'Selesai') === 0) {
            $status_label = 'LULUS / AKTIF';
        }
        $no_resit = "RCPT-B-" . date('Ymd') . "-" . str_pad($id, 5, '0', STR_PAD_LEFT);
        
        $back_url = "waris_dashboard.php";
        $back_label = "Kembali ke Dashboard";
    }

} catch (Exception $e) {
    die("Ralat: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resit Pembayaran <?php echo htmlspecialchars($no_resit); ?> | SmartGrave</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
            background-attachment: fixed;
        }
        
        .receipt-card {
            background: #ffffff;
            border: 1px solid rgba(22, 163, 74, 0.15);
            box-shadow: 0 15px 40px -10px rgba(0, 0, 0, 0.08);
            position: relative;
        }
        
        /* Simulated ticket perforation */
        .perforation {
            border-top: 2px dashed #e2e8f0;
            position: relative;
            margin: 1.5rem 0;
        }
        .perforation::before, .perforation::after {
            content: '';
            width: 20px;
            height: 20px;
            background: #f8fafc; /* Matches body bg */
            border-radius: 50%;
            position: absolute;
            top: -10px;
            box-shadow: inset 0 0 4px rgba(0, 0, 0, 0.02);
        }
        .perforation::before { left: -31px; }
        .perforation::after { right: -31px; }

        @media print {
            body {
                background: none !important;
                background-image: none !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .perforation::before, .perforation::after {
                display: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen py-10 px-4 md:py-16">

<div class="max-w-xl mx-auto">
    <!-- BACK NAVIGATION (No Print) -->
    <div class="mb-6 no-print flex items-center justify-between">
        <a href="<?php echo $back_url; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:text-emerald-950 transition">
            <i class="fas fa-arrow-left"></i> <?php echo $back_label; ?>
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-700/10 transition flex items-center gap-1.5">
            <i class="fas fa-print"></i> Cetak Resit
        </button>
    </div>

    <!-- MAIN RECEIPT CARD -->
    <div class="receipt-card rounded-[2.5rem] overflow-hidden p-8 md:p-10">
        
        <!-- Header / Logo -->
        <div class="text-center mb-8 border-b pb-6 border-slate-100">
            <div class="w-16 h-16 bg-emerald-800 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-800/10">
                <i class="fas fa-mosque text-2xl"></i>
            </div>
            <h1 class="text-xl font-extrabold text-emerald-950 tracking-tight">MASJID KARIAH BANGI LAMA</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Resit Transaksi Pembayaran Rasmi</p>
        </div>

        <!-- Receipt Metadata -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">No. Resit</span>
                <span class="text-sm font-extrabold text-emerald-800"><?php echo htmlspecialchars($no_resit); ?></span>
            </div>
            <div class="text-right">
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Status Bayaran</span>
                <span class="inline-flex items-center gap-1 text-xs font-extrabold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 mt-1">
                    <i class="fas fa-circle-check text-[10px]"></i> <?php echo htmlspecialchars($status_label); ?>
                </span>
            </div>
        </div>

        <div class="perforation"></div>

        <!-- Details Section -->
        <div class="space-y-4 my-6">
            <h3 class="text-xs font-bold text-emerald-900 uppercase tracking-wider border-b border-emerald-50 pb-2">Maklumat Pendaftaran</h3>
            
            <div class="flex justify-between items-start gap-4">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide flex-shrink-0"><?php echo $label_nama; ?></span>
                <span class="text-xs font-bold text-slate-800 text-right uppercase"><?php echo htmlspecialchars($nama_arwah_atau_ahli); ?></span>
            </div>
            
            <div class="flex justify-between items-start gap-4">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide flex-shrink-0"><?php echo $label_ic; ?></span>
                <span class="text-xs font-bold text-slate-800 font-mono"><?php echo htmlspecialchars($no_ic_arwah_atau_ahli); ?></span>
            </div>
            
            <?php foreach ($extra_info as $lbl => $val): ?>
            <div class="flex justify-between items-start gap-4">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide flex-shrink-0"><?php echo $lbl; ?></span>
                <span class="text-xs font-bold text-slate-800 text-right"><?php echo htmlspecialchars($val); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="perforation"></div>

        <!-- Payment Info Section -->
        <div class="space-y-4 my-6">
            <h3 class="text-xs font-bold text-emerald-900 uppercase tracking-wider border-b border-emerald-50 pb-2">Butiran Transaksi</h3>
            
            <div class="flex justify-between items-center">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Pembayar (Waris)</span>
                <span class="text-xs font-bold text-slate-800 uppercase"><?php echo htmlspecialchars($data['nama_waris']); ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Kaedah Pembayaran</span>
                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="fas fa-credit-card text-[11px] text-slate-400"></i> <?php echo htmlspecialchars($data['kaedah_bayaran'] ?? 'Atas Talian / Gateway'); ?>
                </span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Masa Transaksi</span>
                <span class="text-xs font-bold text-slate-800">
                    <?php echo $data['tarikh_transaksi'] ? date('d M Y, h:i A', strtotime($data['tarikh_transaksi'])) : date('d M Y, h:i A'); ?>
                </span>
            </div>
            
            <div class="bg-emerald-50/50 border border-emerald-100 p-4 rounded-2xl flex justify-between items-center mt-6">
                <span class="text-sm font-extrabold text-slate-600">Jumlah Amaun Dibayar</span>
                <span class="text-lg font-black text-emerald-800">RM <?php echo number_format($data['jumlah'], 2); ?></span>
            </div>
        </div>

        <div class="perforation"></div>

        <!-- Footer Seal / QR -->
        <div class="text-center mt-8">
            <div class="w-24 h-24 mx-auto bg-slate-50 border border-slate-200/50 rounded-2xl flex items-center justify-center p-2 relative shadow-inner">
                <i class="fas fa-qrcode text-5xl text-slate-600"></i>
                <div class="absolute inset-0 bg-emerald-500/5 rounded-2xl pointer-events-none"></div>
            </div>
            <p class="text-[10px] text-emerald-800 font-bold mt-3 uppercase tracking-wider"><i class="fas fa-shield-check"></i> SmartGrave Verified Receipt</p>
            <p class="text-[9px] text-slate-400 mt-1">Sila simpan resit ini sebagai bukti pembayaran pendaftaran keahlian / tempahan.</p>
        </div>

    </div>

    <!-- Extra Footer Info (No Print) -->
    <div class="mt-8 text-center text-xs text-slate-400 font-medium no-print">
        © 2026 SmartGrave Bangi Lama • Hak Cipta Terpelihara.
    </div>
</div>

</body>
</html>