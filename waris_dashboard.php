<?php 
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialise pembolehubah
$nama_user      = "Pengguna";
$ic_user        = "-";
$status_kariah  = "Bukan Ahli";
$gelaran        = "En./Pn.";
$initial        = "U";
$kos_pengurusan = "1,100.00";

// Pembolehubah untuk data permohonan
$permohonan_wujud = false;
$id_permohonan    = "Tiada Rekod";
$nama_arwah       = "-";
$ic_arwah         = "-";
$tarikh_mati      = "-";
$status_kod       = 0;

try {
    // 1. Ambil data user — satu table je, tak perlu JOIN waris lagi
    $stmt = $pdo->prepare("
        SELECT full_name, gender, ic_number, status_khairat
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $nama_user = $user['full_name'];
        $ic_user   = $user['ic_number'];

        // Logik gelaran
        $gender_db = isset($user['gender']) ? trim(strtolower($user['gender'])) : '';
        if ($gender_db === 'lelaki') {
            $gelaran = "Encik";
        } elseif ($gender_db === 'perempuan') {
            $gelaran = "Puan";
        } else {
            $gelaran = "En./Pn.";
        }

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
            // Sync status_khairat in users table if not already true
            if (!$user['status_khairat']) {
                $pdo->prepare("UPDATE users SET status_khairat = true WHERE id = ?")->execute([$user_id]);
            }
        } else {
            $is_member = ($user['status_khairat'] === true || $user['status_khairat'] === 1 || $user['status_khairat'] === 't');
        }

        if ($is_member) {
            $status_kariah  = "Ahli";
            $kos_pengurusan = "0.00";
        } else {
            $status_kariah  = "Bukan Ahli";
            $kos_pengurusan = "1,100.00";
        }

        $initial = !empty($nama_user) ? strtoupper(substr($nama_user, 0, 1)) : "U";
    }

    // 2. Ambil tempahan terbaru — FK dah tukar dari id_waris → user_id
    //    dan id_jenazah → jenazah_id, id_tempahan → id
    $stmt_app = $pdo->prepare("
        SELECT 
            t.id,
            t.status_proses,
            t.tarikh_mohon,
            m.nama_jenazah,
            m.no_ic,
            m.tarikh_wafat
        FROM tempahan t
        JOIN maklumat_jenazah m ON t.jenazah_id = m.id
        WHERE t.user_id = ?
        ORDER BY t.tarikh_mohon DESC
        LIMIT 1
    ");
    $stmt_app->execute([$user_id]);
    $app = $stmt_app->fetch(PDO::FETCH_ASSOC);

    if ($app) {
        $permohonan_wujud = true;
        $id_permohonan    = "TMP" . str_pad($app['id'], 5, '0', STR_PAD_LEFT);
        $nama_arwah       = $app['nama_jenazah'];
        $ic_arwah         = $app['no_ic'];
        $tarikh_mati      = date('d M Y', strtotime($app['tarikh_wafat']));

        // Logik status ke kod nombor untuk stepper UI
        $status_text = strtolower(trim($app['status_proses']));
        if ($status_text == 'menunggu bayaran')  $status_kod = 1;
        elseif ($status_text == 'bayaran berjaya') $status_kod = 2;
        elseif ($status_text == 'sedang diproses') $status_kod = 3;
        elseif ($status_text == 'selesai')         $status_kod = 4;
    } else {
        $permohonan_wujud = false;
        $id_permohonan    = "Tiada Rekod";
        $status_kod       = 0;
    }

} catch (PDOException $e) {
    error_log("Ralat: " . $e->getMessage());
}

$title = "Portal Waris"; 
include 'header.php'; 
include 'sidebar.php'; 
?>

<main class="flex-1 p-6 lg:p-12 overflow-y-auto bg-gray-50/30">
    
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950 tracking-tight">Dashboard Waris</h1>
            <p class="text-emerald-700 font-medium tracking-tight">Selamat Datang, <?php echo $gelaran . " " . htmlspecialchars($nama_user); ?></p>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="text-right hidden md:block">
                <span class="block text-[10px] font-bold text-emerald-600 uppercase tracking-widest leading-none">Status Kariah</span>
                <span class="text-sm font-bold text-emerald-900"><?php echo htmlspecialchars($status_kariah); ?></span>
            </div>
            
            <div class="relative group">
                <button class="w-14 h-14 bg-white rounded-2xl shadow-sm border border-emerald-100 flex items-center justify-center text-emerald-800 font-bold text-xl border-b-4 border-b-emerald-700 hover:bg-emerald-50 transition-all cursor-pointer focus:outline-none">
                    <?php echo $initial; ?>
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-300 transform origin-top-right group-hover:translate-y-0 translate-y-2">
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="p-6 rounded-[2rem] shadow-xl shadow-emerald-900/5 border-t-4 border-t-emerald-600 bg-white">
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Keahlian Khairat</p>
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-black text-emerald-900 leading-none"><?php echo ($status_kariah == 'Ahli') ? 'AKTIF 2026' : 'TIDAK AKTIF'; ?></h3>
                <div class="text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full text-[10px] font-bold tracking-tighter">RM60/TAHUN</div>
            </div>
        </div>
        <div class="p-6 rounded-[2rem] shadow-xl shadow-emerald-900/5 border-t-4 border-t-blue-500 bg-white">
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">IC Terdaftar</p>
            <h3 class="text-2xl font-black text-emerald-900 tracking-tighter"><?php echo htmlspecialchars($ic_user); ?></h3>
        </div>
        <div class="p-6 rounded-[2rem] shadow-xl shadow-emerald-900/5 border-t-4 border-t-yellow-500 bg-white">
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Jumlah Kos Pengurusan</p>
            <h3 class="text-2xl font-black text-emerald-900">RM <?php echo $kos_pengurusan; ?> <span class="text-xs font-medium text-emerald-500">(<?php echo htmlspecialchars($status_kariah); ?>)</span></h3>
        </div>
    </div>

    <div class="rounded-[2.5rem] shadow-2xl border border-white overflow-hidden mb-10 bg-white">
        <div class="bg-emerald-800/5 p-8 border-b border-emerald-100">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-extrabold text-emerald-950 uppercase tracking-tight">Status Permohonan Jenazah</h3>
                    <p class="text-sm text-emerald-700">ID Permohonan: <span class="font-bold"><?php echo htmlspecialchars($id_permohonan); ?></span></p>
                </div>
                <div class="flex items-center space-x-2 bg-white px-4 py-2 rounded-xl shadow-sm border border-emerald-50">
                    <span class="w-3 h-3 <?php echo $permohonan_wujud ? 'bg-yellow-500 animate-pulse' : 'bg-gray-300'; ?> rounded-full"></span>
                    <span class="text-sm font-bold text-slate-700 tracking-tight uppercase"><?php echo $permohonan_wujud ? htmlspecialchars($app['status_proses']) : 'Tiada Permohonan'; ?></span>
                </div>
            </div>
        </div>
        
        <div class="p-8 lg:p-12">
            <div class="relative mb-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div class="text-center md:text-left <?php echo $status_kod < 1 ? 'opacity-30' : ''; ?>">
                        <div class="w-10 h-10 <?php echo $status_kod >= 1 ? 'bg-emerald-600 text-white' : 'bg-white border-4 border-gray-200 text-gray-400'; ?> rounded-full flex items-center justify-center mx-auto md:mx-0 mb-4">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <h4 class="font-bold text-emerald-900 text-sm uppercase">Permohonan</h4>
                        <p class="text-xs text-slate-400"><?php echo $permohonan_wujud ? 'Dihantar' : 'Belum mula'; ?></p>
                    </div>
                    <div class="text-center md:text-left <?php echo $status_kod < 2 ? 'opacity-30' : ''; ?>">
                        <div class="w-10 h-10 <?php echo $status_kod >= 2 ? 'bg-emerald-600 text-white' : 'bg-white border-4 border-emerald-600 text-emerald-600'; ?> rounded-full flex items-center justify-center mx-auto md:mx-0 mb-4 shadow-inner">
                            <i class="fas <?php echo $status_kod == 2 ? 'fa-hourglass-half animate-spin' : 'fa-check'; ?> text-sm"></i>
                        </div>
                        <h4 class="font-bold text-emerald-900 text-sm uppercase">Pengesahan</h4>
                        <p class="text-xs text-slate-400 font-medium"><?php echo $status_kod == 2 ? 'Sedang disemak' : ($status_kod > 2 ? 'Selesai' : 'Menunggu'); ?></p>
                    </div>
                    <div class="text-center md:text-left <?php echo $status_kod < 3 ? 'opacity-30' : ''; ?>">
                        <div class="w-10 h-10 <?php echo $status_kod >= 3 ? 'bg-emerald-600 text-white' : 'bg-white border-4 border-gray-200 text-gray-400'; ?> rounded-full flex items-center justify-center mx-auto md:mx-0 mb-4">
                            <i class="fas fa-map-marker-alt text-sm"></i>
                        </div>
                        <h4 class="font-bold text-emerald-900 text-sm uppercase">Tugasan Lot</h4>
                    </div>
                    <div class="text-center md:text-left <?php echo $status_kod < 4 ? 'opacity-30' : ''; ?>">
                        <div class="w-10 h-10 <?php echo $status_kod >= 4 ? 'bg-emerald-600 text-white' : 'bg-white border-4 border-gray-200 text-gray-400'; ?> rounded-full flex items-center justify-center mx-auto md:mx-0 mb-4">
                            <i class="fas fa-flag-checkered text-sm"></i>
                        </div>
                        <h4 class="font-bold text-emerald-900 text-sm uppercase">Selesai</h4>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50/50 rounded-3xl border border-emerald-50 p-8 shadow-inner">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 text-nowrap">
                    <div class="space-y-4">
                        <h5 class="text-xs font-bold text-emerald-600 uppercase tracking-widest border-b border-emerald-100 pb-2 italic">Butiran Jenazah</h5>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-sm text-slate-500">Nama Arwah:</span>
                            <span class="text-sm font-bold text-emerald-950 uppercase"><?php echo htmlspecialchars($nama_arwah); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500">No. MyKad:</span>
                            <span class="text-sm font-bold text-emerald-950"><?php echo htmlspecialchars($ic_arwah); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500">Tarikh Kematian:</span>
                            <span class="text-sm font-bold text-emerald-950"><?php echo htmlspecialchars($tarikh_mati); ?></span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h5 class="text-xs font-bold text-emerald-600 uppercase tracking-widest border-b border-emerald-100 pb-2 italic">Butiran Pengebumian</h5>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-sm text-slate-500">Lokasi Lot:</span>
                            <span class="text-sm font-bold text-emerald-400 italic"><?php echo ($status_kod >= 3) ? htmlspecialchars($app['no_lot']) : 'Menunggu...'; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500 tracking-tight">Resit Pembayaran:</span>
                            <?php if (!empty($app)): ?>
                                <a href="resit.php?id=<?php echo $app['id']; ?>" class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-4 py-1.5 rounded-lg hover:bg-emerald-600 hover:text-white transition-all uppercase">Lihat</a>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-400 italic">Tiada Resit</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-slate-400 text-[10px] mt-10 tracking-widest uppercase pb-6">
        &copy; 2026 SmartGrave Bangi Lama. Sistem Pengurusan Jenazah Digital Patuh Syariah.
    </footer>
</main>