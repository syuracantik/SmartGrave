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
            SELECT tarikh_daftar, status_yuran 
            FROM daftar_khairat 
            WHERE user_id = ? AND (hubungan = 'Diri Sendiri' OR hubungan = 'DIRI SENDIRI') AND status_yuran = 'Dibayar'
            LIMIT 1
        ");
        $stmt_check_khairat->execute([$user_id]);
        $khairat_info = $stmt_check_khairat->fetch(PDO::FETCH_ASSOC);
        $has_active_khairat = ($khairat_info !== false);

        $is_member = false;
        $is_mature = false;
        if ($has_active_khairat) {
            $is_member = true;
            $tarikh_daftar = $khairat_info['tarikh_daftar'];
            $valid_from = date('Y-m-d', strtotime($tarikh_daftar . ' + 1 month'));
            if (date('Y-m-d') >= $valid_from) {
                $is_mature = true;
            }
            // Sync status_khairat in users table if not already true
            if (!$user['status_khairat']) {
                $pdo->prepare("UPDATE users SET status_khairat = true WHERE id = ?")->execute([$user_id]);
            }
        } else {
            $is_member = ($user['status_khairat'] === true || $user['status_khairat'] === 1 || $user['status_khairat'] === 't');
            if ($is_member) {
                $stmt_reg_date = $pdo->prepare("
                    SELECT tarikh_daftar FROM daftar_khairat 
                    WHERE user_id = ? AND status_yuran = 'Dibayar' 
                    ORDER BY tarikh_daftar ASC LIMIT 1
                ");
                $stmt_reg_date->execute([$user_id]);
                $reg_date_val = $stmt_reg_date->fetchColumn();
                if ($reg_date_val) {
                    $valid_from = date('Y-m-d', strtotime($reg_date_val . ' + 1 month'));
                    if (date('Y-m-d') >= $valid_from) {
                        $is_mature = true;
                    }
                } else {
                    $is_mature = true;
                }
            }
        }

        if ($is_member) {
            if ($is_mature) {
                $status_kariah  = "Ahli";
                $kos_pengurusan = "0.00";
            } else {
                $status_kariah  = "Ahli (Menunggu Kematangan)";
                $kos_pengurusan = "1,100.00";
            }
        } else {
            $status_kariah  = "Bukan Ahli";
            $kos_pengurusan = "1,100.00";
        }

        $initial = !empty($nama_user) ? strtoupper(substr($nama_user, 0, 1)) : "U";
    }

    // 2. Ambil tempahan terbaru — FK dah tukar dari id_waris → user_id
    //    dan id_jenazah → jenazah_id, id_tempahan → id
    //    Filter out completed bookings (Lulus / Tolak) after 24 hours
    $stmt_app = $pdo->prepare("
        SELECT 
            t.id,
            t.status_proses,
            t.tarikh_mohon,
            t.ulasan_admin,
            m.nama_jenazah,
            m.no_ic,
            m.tarikh_wafat,
            lp.no_lot,
            lp.status_lot
        FROM tempahan t
        JOIN maklumat_jenazah m ON t.jenazah_id = m.id
        LEFT JOIN lot_pusara lp ON lp.jenazah_id = m.id
        WHERE t.user_id = ?
          AND NOT (t.status_proses IN ('Lulus', 'Tolak') AND t.updated_at < NOW() - INTERVAL '24 hours')
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
        if ($status_text == 'pending') {
            $status_kod = 1;
        } elseif ($status_text == 'bayaran berjaya') {
            $status_kod = 2;
        } elseif ($status_text == 'lulus') {
            if (!empty($app['no_lot']) && ($app['status_lot'] ?? '') === 'Penuh') {
                $status_kod = 4;
            } else {
                $status_kod = 3;
            }
        } elseif ($status_text == 'tolak') {
            $status_kod = -1;
        } else {
            // fallback
            if ($status_text == 'menunggu bayaran') $status_kod = 1;
            elseif ($status_text == 'selesai') $status_kod = 4;
            else $status_kod = 1;
        }
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
                    <h3 class="text-xl font-extrabold text-emerald-950 uppercase tracking-tight">Status Permohonan Lot Kubur</h3>
                    <p class="text-sm text-emerald-700">ID Permohonan: <span class="font-bold"><?php echo htmlspecialchars($id_permohonan); ?></span></p>
                </div>
                <div class="flex items-center space-x-2 bg-white px-4 py-2 rounded-xl shadow-sm border border-emerald-50">
                    <span class="w-3 h-3 <?php 
                        if (!$permohonan_wujud) echo 'bg-gray-300';
                        elseif ($status_kod == -1) echo 'bg-red-500 animate-pulse';
                        else echo 'bg-yellow-500 animate-pulse'; 
                    ?> rounded-full"></span>
                    <span class="text-sm font-bold <?php echo ($status_kod == -1) ? 'text-red-600' : 'text-slate-700'; ?> tracking-tight uppercase">
                        <?php echo $permohonan_wujud ? htmlspecialchars($app['status_proses']) : 'Tiada Permohonan'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="p-8 lg:p-12">
            <!-- Success Notification Alert -->
            <?php if (isset($_GET['edit_success'])): ?>
                <div class="p-6 bg-emerald-50 border-l-4 border-emerald-500 rounded-2xl mb-8 flex items-start space-x-4 shadow-sm">
                    <div class="bg-emerald-100 p-3 rounded-xl text-emerald-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-emerald-950 text-base">Permohonan Berjaya Dikemaskini</h4>
                        <p class="text-sm text-emerald-800 mt-1">Permohonan anda telah berjaya dikemaskini dan dihantar semula untuk kelulusan pentadbir.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Rejection Comments Card -->
            <?php if ($status_kod == -1): ?>
                <div class="p-6 bg-red-50 border-l-4 border-red-500 rounded-2xl mb-8 flex items-start space-x-4 shadow-sm">
                    <div class="bg-red-100 p-3 rounded-xl text-red-600">
                        <i class="fas fa-times-circle text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-red-950 text-base">Permohonan Ditolak oleh Pentadbir</h4>
                        <p class="text-sm text-red-800 mt-1">Maaf, permohonan tempahan lot kubur anda tidak dapat diluluskan atas sebab berikut:</p>
                        <div class="mt-3 p-4 bg-white rounded-xl border border-red-100 text-sm font-semibold text-red-900 italic">
                            "<?php echo htmlspecialchars($app['ulasan_admin'] ?? 'Tiada ulasan dinyatakan.'); ?>"
                        </div>
                        <p class="text-xs text-red-500 mt-3">*Sila kemaskini maklumat di bawah atau hubungi pihak pentadbir untuk maklumat lanjut.</p>
                        <div class="mt-4">
                            <a href="booking.php?edit_tempahan_id=<?php echo $app['id']; ?>" 
                               class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-5 rounded-xl text-sm shadow-md transition-all">
                                <i class="fas fa-edit"></i> Kemaskini & Hantar Semula
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stepper Container -->
            <div class="relative mb-16 px-4">
                <!-- Background Line (desktop only) -->
                <div class="absolute top-5 left-8 right-8 h-1.5 bg-gray-100 -z-10 rounded-full hidden md:block">
                    <!-- Dynamic Progress Line -->
                    <div class="h-full bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-500 rounded-full transition-all duration-700" 
                         style="width: <?php 
                            if ($status_kod == 1) echo '12.5%';
                            elseif ($status_kod == 2) echo '37.5%';
                            elseif ($status_kod == 3) echo '62.5%';
                            elseif ($status_kod >= 4) echo '100%';
                            else echo '0%'; 
                         ?>;">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Step 1: Permohonan -->
                    <div class="flex flex-col items-center text-center">
                        <div class="w-11 h-11 flex items-center justify-center rounded-xl transition-all duration-300 <?php 
                            echo ($status_kod >= 1) 
                                ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-lg shadow-emerald-600/20' 
                                : 'bg-gray-100 text-gray-400 border-2 border-gray-200'; 
                        ?> mb-3">
                            <i class="fas fa-file-signature text-sm"></i>
                        </div>
                        <h4 class="font-bold text-emerald-950 text-sm uppercase tracking-wide">Permohonan</h4>
                        <p class="text-xs font-semibold text-emerald-600 mt-1">Dihantar</p>
                        <p class="text-[10px] text-gray-400 mt-0.5"><?php echo $permohonan_wujud ? date('d M Y', strtotime($app['tarikh_mohon'])) : '—'; ?></p>
                    </div>

                    <!-- Step 2: Pengesahan -->
                    <div class="flex flex-col items-center text-center">
                        <div class="w-11 h-11 flex items-center justify-center rounded-xl transition-all duration-300 <?php 
                            if ($status_kod > 2) {
                                echo 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-lg shadow-emerald-600/20';
                            } elseif ($status_kod == 2) {
                                echo 'bg-amber-500 text-white ring-4 ring-amber-100 animate-pulse shadow-lg shadow-amber-500/20';
                            } else {
                                echo 'bg-gray-100 text-gray-400 border-2 border-gray-200';
                            }
                        ?> mb-3">
                            <i class="fas <?php echo $status_kod == 2 ? 'fa-spinner fa-spin' : 'fa-shield-alt'; ?> text-sm"></i>
                        </div>
                        <h4 class="font-bold <?php echo $status_kod >= 2 ? 'text-emerald-950' : 'text-gray-400'; ?> text-sm uppercase tracking-wide">Pengesahan</h4>
                        <p class="text-xs font-semibold <?php echo $status_kod == 2 ? 'text-amber-600' : ($status_kod > 2 ? 'text-emerald-600' : 'text-gray-400'); ?> mt-1">
                            <?php echo $status_kod == 2 ? 'Sedang Disemak' : ($status_kod > 2 ? 'Disahkan' : 'Menunggu'); ?>
                        </p>
                    </div>

                    <!-- Step 3: Tugasan Lot -->
                    <div class="flex flex-col items-center text-center">
                        <div class="w-11 h-11 flex items-center justify-center rounded-xl transition-all duration-300 <?php 
                            if ($status_kod > 3) {
                                echo 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-lg shadow-emerald-600/20';
                            } elseif ($status_kod == 3) {
                                echo 'bg-amber-500 text-white ring-4 ring-amber-100 animate-pulse shadow-lg shadow-amber-500/20';
                            } else {
                                echo 'bg-gray-100 text-gray-400 border-2 border-gray-200';
                            }
                        ?> mb-3">
                            <i class="fas fa-map-marker-alt text-sm"></i>
                        </div>
                        <h4 class="font-bold <?php echo $status_kod >= 3 ? 'text-emerald-950' : 'text-gray-400'; ?> text-sm uppercase tracking-wide">Tugasan Lot</h4>
                        <p class="text-xs font-semibold <?php echo $status_kod == 3 ? 'text-amber-600' : ($status_kod > 3 ? 'text-emerald-600' : 'text-gray-400'); ?> mt-1">
                            <?php 
                                if (!empty($app['no_lot'])) {
                                    echo "Lot " . htmlspecialchars($app['no_lot']);
                                } else {
                                    echo $status_kod == 3 ? 'Sedang Ditetapkan' : 'Belum Ditunjuk';
                                }
                            ?>
                        </p>
                    </div>

                    <!-- Step 4: Selesai -->
                    <div class="flex flex-col items-center text-center">
                        <div class="w-11 h-11 flex items-center justify-center rounded-xl transition-all duration-300 <?php 
                            echo ($status_kod >= 4) 
                                ? 'bg-emerald-700 text-white ring-4 ring-emerald-100 shadow-lg shadow-emerald-700/20' 
                                : 'bg-gray-100 text-gray-400 border-2 border-gray-200'; 
                        ?> mb-3">
                            <i class="fas fa-flag-checkered text-sm"></i>
                        </div>
                        <h4 class="font-bold <?php echo $status_kod >= 4 ? 'text-emerald-950' : 'text-gray-400'; ?> text-sm uppercase tracking-wide">Selesai</h4>
                        <p class="text-xs font-semibold <?php echo $status_kod >= 4 ? 'text-emerald-700' : 'text-gray-400'; ?> mt-1">
                            <?php echo $status_kod >= 4 ? 'Sedia Dilawat' : 'Menunggu Selesai'; ?>
                        </p>
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
                            <span class="text-sm font-bold text-emerald-400 italic"><?php echo ($status_kod >= 3 && !empty($app['no_lot'])) ? htmlspecialchars($app['no_lot']) : 'Menunggu...'; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500 tracking-tight">Resit Pembayaran:</span>
                            <?php if (!empty($app)): ?>
                                <a href="#" onclick="openReceiptModal('booking', <?php echo $app['id']; ?>); return false;" class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-4 py-1.5 rounded-lg hover:bg-emerald-600 hover:text-white transition-all uppercase">Lihat</a>
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