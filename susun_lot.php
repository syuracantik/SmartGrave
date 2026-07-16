<?php
// ============================================================
// susun_lot.php
// SmartGrave - Penetapan & Susun Atur Lot (Admin)
// ============================================================
session_start();
require_once 'db.php';

// Check if user is logged in (admin validation can be added here)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$title = "Susun Atur Lot";
require_once 'header.php';

// Upgrade database if necessary to ensure image columns exist
try {
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kiri VARCHAR(255)");
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kanan VARCHAR(255)");
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_penanda VARCHAR(255)");
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kiri_desc VARCHAR(255)");
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kanan_desc VARCHAR(255)");
    $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_penanda_desc VARCHAR(255)");
} catch (Exception $ex) {}

// Handle POST: Assign vacant lot or update guide photos
$msg_success = '';
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign'])) {
    $lot_id = trim($_POST['lot_id'] ?? '');
    $tempahan_id = intval($_POST['tempahan_id'] ?? 0);
    
    // File uploads handling
    $upload_dir = 'uploads/lot_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $gambar_kiri_path = '';
    $gambar_kanan_path = '';
    $gambar_penanda_path = '';
    
    // Helper to upload images
    function uploadGraveImage($file_key, $lot_id, $suffix, $upload_dir) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$file_key]['tmp_name'];
            $orig_name = $_FILES[$file_key]['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            
            // Validate extension
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = $lot_id . '_' . $suffix . '_' . time() . '.' . $ext;
                $dest = $upload_dir . $filename;
                if (move_uploaded_file($tmp_name, $dest)) {
                    return $dest;
                }
            }
        }
        return '';
    }

    $gambar_kiri_path = uploadGraveImage('gambar_kiri', $lot_id, 'kiri', $upload_dir);
    $gambar_kanan_path = uploadGraveImage('gambar_kanan', $lot_id, 'kanan', $upload_dir);
    $gambar_penanda_path = uploadGraveImage('gambar_penanda', $lot_id, 'penanda', $upload_dir);
    
    $gambar_kiri_desc = trim($_POST['gambar_kiri_desc'] ?? '');
    $gambar_kanan_desc = trim($_POST['gambar_kanan_desc'] ?? '');
    $gambar_penanda_desc = trim($_POST['gambar_penanda_desc'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        if ($tempahan_id > 0) {
            // Fetch booking info
            $stmt = $pdo->prepare("
                SELECT t.user_id, t.jenazah_id, j.nama_jenazah 
                FROM tempahan t 
                JOIN maklumat_jenazah j ON j.id = t.jenazah_id 
                WHERE t.id = ?
            ");
            $stmt->execute([$tempahan_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Tempahan tidak ditemui.");
            }
            
            $jenazah_id = $booking['jenazah_id'];
            $waris_id = $booking['user_id'];
            $nama_jenazah = $booking['nama_jenazah'];
            
            $gambar_penanda_desc = 'Kubur ' . $nama_jenazah;
            
            // Insert or update in lot_pusara
            $stmt_save = $pdo->prepare("
                INSERT INTO lot_pusara (no_lot, status_lot, jenazah_id, gambar_kiri, gambar_kanan, gambar_penanda, gambar_kiri_desc, gambar_kanan_desc, gambar_penanda_desc)
                VALUES (?, 'Penuh', ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (no_lot)
                DO UPDATE SET status_lot = 'Penuh', 
                              jenazah_id = EXCLUDED.jenazah_id,
                              gambar_kiri = COALESCE(NULLIF(EXCLUDED.gambar_kiri, ''), lot_pusara.gambar_kiri),
                              gambar_kanan = COALESCE(NULLIF(EXCLUDED.gambar_kanan, ''), lot_pusara.gambar_kanan),
                              gambar_penanda = COALESCE(NULLIF(EXCLUDED.gambar_penanda, ''), lot_pusara.gambar_penanda),
                              gambar_kiri_desc = EXCLUDED.gambar_kiri_desc,
                              gambar_kanan_desc = EXCLUDED.gambar_kanan_desc,
                              gambar_penanda_desc = EXCLUDED.gambar_penanda_desc
            ");
            $stmt_save->execute([$lot_id, $jenazah_id, $gambar_kiri_path, $gambar_kanan_path, $gambar_penanda_path, $gambar_kiri_desc, $gambar_kanan_desc, $gambar_penanda_desc]);
            
            // Update booking status
            $stmt_tempahan = $pdo->prepare("UPDATE tempahan SET status_proses = 'Lulus', updated_at = NOW() WHERE id = ?");
            $stmt_tempahan->execute([$tempahan_id]);
            
            // Notify waris
            $notif_msg = "Selesai! Lokasi pusara bagi arwah " . $nama_jenazah . " telah ditetapkan di Lot " . $lot_id . ". Panduan navigasi kini sedia untuk dirujuk di halaman Carian Pusara.";
            $stmt_notif = $pdo->prepare("INSERT INTO notifikasi (user_id, mesej) VALUES (?, ?)");
            $stmt_notif->execute([$waris_id, $notif_msg]);
            
            $pdo->commit();
            
            // Redirect back to admin dashboard with success
            echo "<script>window.location.href='admin_dashboard.php?berjaya=lulus&tempahan_id=$tempahan_id';</script>";
            exit;
        } else {
            // No active tempahan_id, this is a photo update for an already occupied lot
            $stmt_nama = $pdo->prepare("
                SELECT j.nama_jenazah 
                FROM lot_pusara lp
                JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
                WHERE lp.no_lot = ?
            ");
            $stmt_nama->execute([$lot_id]);
            $nama_jenazah = $stmt_nama->fetchColumn();
            if (!$nama_jenazah) {
                $nama_jenazah = 'Arwah';
            }
            $gambar_penanda_desc = 'Kubur ' . $nama_jenazah;

            $stmt_update = $pdo->prepare("
                UPDATE lot_pusara 
                SET gambar_kiri = COALESCE(NULLIF(?, ''), gambar_kiri),
                    gambar_kanan = COALESCE(NULLIF(?, ''), gambar_kanan),
                    gambar_penanda = COALESCE(NULLIF(?, ''), gambar_penanda),
                    gambar_kiri_desc = ?,
                    gambar_kanan_desc = ?,
                    gambar_penanda_desc = ?
                WHERE no_lot = ? AND status_lot = 'Penuh'
            ");
            $stmt_update->execute([$gambar_kiri_path, $gambar_kanan_path, $gambar_penanda_path, $gambar_kiri_desc, $gambar_kanan_desc, $gambar_penanda_desc, $lot_id]);
            
            $pdo->commit();
            $msg_success = "Gambar panduan bagi Lot $lot_id berjaya dikemaskini!";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg_error = "Gagal memproses penetapan: " . $e->getMessage();
    }
}

// Fetch active booking if tempahan_id is passed
$tempahan_id = isset($_GET['tempahan_id']) ? intval($_GET['tempahan_id']) : 0;
$active_booking = null;
if ($tempahan_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.id, t.status_proses, u.full_name AS waris_nama, u.no_telefon AS waris_tel,
               j.id AS jenazah_id, j.nama_jenazah, j.no_ic AS jenazah_ic
        FROM tempahan t
        JOIN users u ON u.id = t.user_id
        JOIN maklumat_jenazah j ON j.id = t.jenazah_id
        WHERE t.id = ?
    ");
    $stmt->execute([$tempahan_id]);
    $active_booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch occupied graves from DB
$occupied_graves = [];
try {
    $stmt = $pdo->query("
        SELECT lp.no_lot AS id, j.nama_jenazah AS nama, j.no_ic AS ic, j.tarikh_wafat AS mati,
               lp.gambar_kiri, lp.gambar_kanan, lp.gambar_penanda,
               lp.gambar_kiri_desc, lp.gambar_kanan_desc, lp.gambar_penanda_desc
        FROM lot_pusara lp
        JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
        WHERE lp.status_lot = 'Penuh'
    ");
    if ($stmt) {
        $db_graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($db_graves as $g) {
            $id = $g['id'];
            $zon = substr($id, 0, 1);
            $mati = $g['mati'] ? date('d/m/Y', strtotime($g['mati'])) : '—';
            $ic = preg_replace('/[^0-9]/', '', $g['ic']);
            $lahir = '—';
            if (strlen($ic) === 12) {
                $year_part = substr($ic, 0, 2);
                $month_part = substr($ic, 2, 2);
                $day_part = substr($ic, 4, 2);
                $current_year = intval(date('Y'));
                $century = ($year_part + 2000 > $current_year) ? 1900 : 2000;
                $year = $century + intval($year_part);
                $lahir = "$day_part/$month_part/$year";
            }
            $occupied_graves[$id] = [
                'id' => $id,
                'nama' => $g['nama'],
                'ic' => $g['ic'],
                'lahir' => $lahir,
                'mati' => $mati,
                'zon' => $zon,
                'gambar_kiri' => $g['gambar_kiri'] ?? '',
                'gambar_kanan' => $g['gambar_kanan'] ?? '',
                'gambar_penanda' => $g['gambar_penanda'] ?? '',
                'gambar_kiri_desc' => $g['gambar_kiri_desc'] ?? '',
                'gambar_kanan_desc' => $g['gambar_kanan_desc'] ?? '',
                'gambar_penanda_desc' => $g['gambar_penanda_desc'] ?? ''
            ];
        }
    }
} catch (Exception $e) {}

?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    :root {
        --font-body: 'Inter', sans-serif;
        --font-display: 'Inter', sans-serif;
        --emerald-900: #064e3b;
        --emerald-800: #065f46;
        --emerald-700: #047857;
        --emerald-600: #059669;
        --emerald-500: #10b981;
        --emerald-100: #d1fae5;
        --emerald-50: #ecfdf5;
        --border: #dde6de;
        --bg: #f8fafc;
        --surface: #fff;
    }
    body {
        font-family: var(--font-body);
        color: #1e293b;
    }
    #map {
        width: 100%;
        height: 100%;
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }
    .lot-card-status {
        transition: all 0.2s ease;
    }
    .lot-card-status:hover {
        transform: translateY(-2px);
    }
    /* Modal styles */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px); z-index: 2000;
        display: none; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff; border-radius: 1.5rem; width: 100%; max-width: 520px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: modalIn 0.22s ease-out; overflow: hidden;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.96) translateY(8px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .img-preview-box {
        width: 100%; height: 96px; border: 2px dashed #cbd5e1; border-radius: 0.75rem;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        cursor: pointer; background: #f8fafc; transition: all 0.15s;
    }
    .img-preview-box:hover { border-color: var(--emerald-500); background: var(--emerald-50); }
    .img-preview-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 0.75rem; display: none; }
</style>

<?php include 'sidebar2.php'; ?>

<main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50/30">
    <!-- Header bar -->
    <div class="px-8 py-5 border-b border-gray-100 bg-white flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-2xl font-black text-emerald-950 flex items-center gap-2">
                <i class="fas fa-cubes text-emerald-600"></i> Susun Atur & Pemetaan Lot
            </h1>
            <p class="text-xs text-emerald-700 font-medium">Urus susun atur, penetapan permohonan baru, dan gambar rujukan kubur</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-xs font-mono bg-emerald-50 border border-emerald-100 text-emerald-800 px-3 py-1.5 rounded-full flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                Admin Panel Mode
            </div>
            <a href="admin_dashboard.php" class="text-xs font-bold text-gray-500 hover:text-emerald-800 flex items-center gap-1.5 transition">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex overflow-hidden min-h-0">
        
        <!-- Left Panel: Assignment Details or Status Counts -->
        <aside class="w-80 border-r border-gray-100 bg-white p-6 flex flex-col justify-between flex-shrink-0 overflow-y-auto">
            
            <div class="space-y-6">
                
                <?php if ($msg_success): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-start gap-2.5 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-600 text-sm mt-0.5"></i>
                    <div><strong>Berjaya!</strong> <?php echo htmlspecialchars($msg_success); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($msg_error): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 text-xs rounded-xl flex items-start gap-2.5 shadow-sm">
                    <i class="fas fa-times-circle text-red-600 text-sm mt-0.5"></i>
                    <div><strong>Ralat!</strong> <?php echo htmlspecialchars($msg_error); ?></div>
                </div>
                <?php endif; ?>

                <!-- Case 1: Active Tempahan/Booking Assignment -->
                <?php if ($active_booking): ?>
                <div class="bg-amber-50/50 border border-amber-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full uppercase tracking-wider">Menunggu Lot</span>
                        <span class="text-xs font-bold text-slate-400">#TMP<?php echo str_pad($active_booking['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Nama Jenazah</span>
                            <span class="text-sm font-extrabold text-slate-800 block leading-tight mt-0.5"><?php echo htmlspecialchars($active_booking['nama_jenazah']); ?></span>
                            <span class="text-[11px] text-slate-500 font-mono">IC: <?php echo htmlspecialchars($active_booking['jenazah_ic']); ?></span>
                        </div>
                        <hr class="border-amber-200/50">
                        <div>
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Waris</span>
                            <span class="text-xs font-bold text-slate-700 block"><?php echo htmlspecialchars($active_booking['waris_nama']); ?></span>
                            <span class="text-[11px] text-slate-500 font-mono"><?php echo htmlspecialchars($active_booking['waris_tel']); ?></span>
                        </div>
                    </div>
                    
                    <div class="bg-amber-100/60 p-3.5 rounded-xl border border-amber-200/50 text-[11px] text-amber-900 leading-relaxed font-medium">
                        <i class="fas fa-info-circle text-amber-700 mr-1"></i>
                        Sila klik mana-mana lot <strong>KOSONG</strong> (warna zon) pada peta untuk menetapkan kubur.
                    </div>
                    
                    <a href="susun_lot.php" class="block text-center text-xs font-bold text-red-500 hover:text-red-700 transition">
                        Batal Pilihan Tempahan
                    </a>
                </div>
                
                <!-- Case 2: Standard Status Panel -->
                <?php else: ?>
                <div class="bg-emerald-950 text-white rounded-3xl p-5 shadow-lg relative overflow-hidden">
                    <!-- Glow -->
                    <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-emerald-500/20 blur-xl rounded-full"></div>
                    
                    <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Jumlah Lot Pusara</p>
                    <h3 class="text-3xl font-black mt-1">440 <span class="text-xs font-normal text-emerald-400">Pusara</span></h3>
                    
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/5">
                            <span class="text-[9px] font-bold text-emerald-200 uppercase tracking-wider">Terisi</span>
                            <span class="text-lg font-black block mt-0.5 text-red-300" id="sbFilledLots">0</span>
                        </div>
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/5">
                            <span class="text-[9px] font-bold text-emerald-200 uppercase tracking-wider">Tersedia</span>
                            <span class="text-lg font-black block mt-0.5 text-green-300" id="sbEmptyLots">440</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs text-slate-500 leading-relaxed">
                    <i class="fas fa-hand-pointer text-emerald-600 mr-1 text-sm"></i>
                    Klik mana-mana lot <strong>KOSONG</strong> pada peta untuk melihat koordinat atau klik lot <strong>TERISI</strong> untuk melihat maklumat jenazah serta gambar panduan.
                </div>
                <?php endif; ?>
                
                <!-- Map Legend -->
                <div class="border border-gray-100 rounded-2xl p-4 space-y-3">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Petunjuk Peta</h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-blue-50 border border-blue-500 flex-shrink-0"></div>
                            Zon A (Tersedia)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-purple-50 border border-purple-500 flex-shrink-0"></div>
                            Zon B (Tersedia)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-red-50 border border-red-500 flex-shrink-0"></div>
                            Lot Terisi (Penuh)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded border border-slate-300 bg-slate-100 flex-shrink-0"></div>
                            Laluan / Koridor Tengah
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer info -->
            <div class="text-[10px] text-slate-400 text-center tracking-wide mt-6 border-t border-gray-50 pt-4 uppercase font-bold">
                SmartGrave Pemetaan
            </div>
        </aside>

        <!-- Right Panel: Full Leaflet Map -->
        <div class="flex-1 p-6 relative">
            <div id="map"></div>
            
            <!-- Map Layer Toggle -->
            <div class="absolute top-10 left-10 z-[1000] bg-white/95 backdrop-blur-md border border-slate-200/80 rounded-full p-1 flex gap-1 shadow-md">
                <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 bg-emerald-800 text-white shadow-sm" id="layer-light" onclick="setMapLayer('light')">
                    <i class="fas fa-map"></i> Peta
                </button>
                <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-emerald-800" id="layer-satellite" onclick="setMapLayer('satellite')">
                    <i class="fas fa-satellite"></i> Satelit
                </button>
            </div>
        </div>

    </div>
</main>

<!-- ═══════════════════════════════════════
     MODAL: ASSIGN / UPDATE LOT DIALOG
     ═══════════════════════════════════════ -->
<div class="modal-overlay" id="modalLot">
    <div class="modal-box">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-extrabold text-slate-900" id="modalTitle">Tetapkan Lot</h3>
                <p class="text-xs text-slate-400 mt-0.5" id="modalSubtitle">Sila muat naik gambar jika ada</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-slate-500 hover:bg-gray-200 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" action="">
            <input type="hidden" name="action_assign" value="1">
            <input type="hidden" name="lot_id" id="formLotId">
            <input type="hidden" name="tempahan_id" value="<?php echo $tempahan_id; ?>">

            <div class="p-6 space-y-4">
                
                <!-- Active Booking Details in Form -->
                <div id="activeBookingDetails" class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-xs text-emerald-900 space-y-1.5 <?php echo $active_booking ? '' : 'hidden'; ?>">
                    <p class="font-bold text-[10px] text-emerald-700 uppercase tracking-wide">Maklumat Kemasukan Jenazah</p>
                    <p class="text-sm font-extrabold text-emerald-950"><?php echo $active_booking ? htmlspecialchars($active_booking['nama_jenazah']) : ''; ?></p>
                    <p>IC: <?php echo $active_booking ? htmlspecialchars($active_booking['jenazah_ic']) : ''; ?></p>
                </div>

                <!-- Occupied Grave Info Show -->
                <div id="occupiedDetails" class="bg-red-50 border border-red-100 rounded-xl p-4 text-xs text-red-950 space-y-1.5 hidden">
                    <p class="font-bold text-[10px] text-red-700 uppercase tracking-wide">Maklumat Arwah</p>
                    <p class="text-sm font-extrabold text-red-950" id="showNamaJenazah">—</p>
                    <p id="showIcJenazah">IC: —</p>
                    <p id="showMatiJenazah">Tarikh Wafat: —</p>
                </div>

                <!-- Latitude / Longitude Info -->
                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 border border-slate-100 p-3 rounded-xl">
                    <div>
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block">Latitude</span>
                        <span class="font-mono text-slate-700 font-bold" id="showLat">—</span>
                    </div>
                    <div>
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block">Longitude</span>
                        <span class="font-mono text-slate-700 font-bold" id="showLng">—</span>
                    </div>
                </div>

                <!-- 3 Guide Images upload fields -->
                <div class="space-y-4">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Gambar Panduan Lokasi & Penerangan</h4>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <!-- Image Left -->
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-slate-400 block text-center">Gambar 1</span>
                            <div class="img-preview-box" onclick="triggerInput('fileLeft')">
                                <span class="text-lg text-slate-400" id="previewIconLeft">📸</span>
                                <span class="text-[8px] text-slate-400 font-mono" id="previewLblLeft"></span>
                                <img id="previewImgLeft">
                            </div>
                            <input type="file" name="gambar_kiri" id="fileLeft" class="hidden" accept="image/*" onchange="previewFile(this, 'previewImgLeft', 'previewIconLeft', 'previewLblLeft')">
                            <input type="text" name="gambar_kiri_desc" id="descLeft" placeholder="Keterangan..." class="w-full text-[10px] px-2 py-1 border border-slate-200 rounded-lg mt-1" style="font-family:inherit;">
                        </div>

                        <!-- Image Right -->
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-slate-400 block text-center">Gambar 2</span>
                            <div class="img-preview-box" onclick="triggerInput('fileRight')">
                                <span class="text-lg text-slate-400" id="previewIconRight">📸</span>
                                <span class="text-[8px] text-slate-400 font-mono" id="previewLblRight"></span>
                                <img id="previewImgRight">
                            </div>
                            <input type="file" name="gambar_kanan" id="fileRight" class="hidden" accept="image/*" onchange="previewFile(this, 'previewImgRight', 'previewIconRight', 'previewLblRight')">
                            <input type="text" name="gambar_kanan_desc" id="descRight" placeholder="Keterangan..." class="w-full text-[10px] px-2 py-1 border border-slate-200 rounded-lg mt-1" style="font-family:inherit;">
                        </div>

                        <!-- Image Penanda -->
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-slate-400 block text-center">Gambar Kubur</span>
                            <div class="img-preview-box" onclick="triggerInput('filePenanda')">
                                <span class="text-lg text-slate-400" id="previewIconPenanda">📸</span>
                                <span class="text-[8px] text-slate-400 font-mono" id="previewLblPenanda"></span>
                                <img id="previewImgPenanda">
                            </div>
                            <input type="file" name="gambar_penanda" id="filePenanda" class="hidden" accept="image/*" onchange="previewFile(this, 'previewImgPenanda', 'previewIconPenanda', 'previewLblPenanda')">
                            <input type="hidden" name="gambar_penanda_desc" id="descPenanda">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer actions -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-white border border-gray-200 text-xs font-bold text-slate-500 rounded-xl hover:bg-gray-100 transition">
                    Batal
                </button>
                <button type="submit" id="btnSubmit" class="px-5 py-2 bg-emerald-800 hover:bg-emerald-700 text-xs font-bold text-white rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-emerald-900/10">
                    <i class="fas fa-save"></i> <span id="submitText">Sahkan & Tetapkan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Seeded data from PHP
const OCCUPIED_GRAVES = <?php echo json_encode($occupied_graves); ?>;
const ACTIVE_BOOKING = <?php echo $active_booking ? json_encode($active_booking) : 'null'; ?>;

// Map Coordinates
const MASJID_POS = [2.90050, 101.77336];
const CENTER = [2.89980, 101.77490];
const ENTRY_GATE = [2.89992, 101.77490];

const map = L.map('map', {
    center: CENTER, 
    zoom: 19, 
    zoomControl: false, 
    attributionControl: false
});

const lightTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 22, 
    maxNativeZoom: 19
});
const satelliteTiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 22, 
    maxNativeZoom: 19,
    attribution: 'Esri World Imagery'
});

lightTiles.addTo(map);

let currentLayer = 'light';
function setMapLayer(type) {
    if (type === currentLayer) return;
    const btnLight = document.getElementById('layer-light');
    const btnSat = document.getElementById('layer-satellite');
    
    if (type === 'light') {
        map.removeLayer(satelliteTiles);
        lightTiles.addTo(map);
        
        btnLight.className = "px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 bg-emerald-800 text-white shadow-sm";
        btnSat.className = "px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-emerald-800";
    } else {
        map.removeLayer(lightTiles);
        satelliteTiles.addTo(map);
        
        btnSat.className = "px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 bg-emerald-800 text-white shadow-sm";
        btnLight.className = "px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-emerald-800";
    }
    currentLayer = type;
}

L.control.zoom({ position: 'topright' }).addTo(map);

// Grid Dimensions
const LOT_W  = 0.000055;
const LOT_H  = 0.000080;
const GAP_X  = 0.000020;
const GAP_Y  = 0.000016;
const STEP_X = LOT_W + GAP_X;  // 0.000075 per column
const STEP_Y = LOT_H + GAP_Y;  // 0.000096 per row
const ZONE_COLS = 11;
const ZONE_ROWS = 20;

const ZONE_START = {
  A: {lat: 2.89800, lng: 101.77400},
  B: {lat: 2.89800, lng: 101.774975},
};

const LOT_COORDS = {};

// PRNG Seed helper
function seededRandom(seed) {
    let s = seed % 2147483647;
    if (s <= 0) s += 2147483646;
    return function () {
        s = (s * 16807) % 2147483647;
        return (s - 1) / 2147483646;
    };
}

// Generate coordinate bounds
function generateLots(zon) {
  const coords = {};
  const s = ZONE_START[zon];
  const rnd = seededRandom(zon === 'A' ? 31 : 67);
  let n = 1;
  for (let r = 0; r < ZONE_ROWS; r++) {
    for (let c = 0; c < ZONE_COLS; c++) {
      const id = `${zon}${String(n).padStart(3,'0')}`;
      const jx = (rnd() - 0.5) * GAP_X * 0.6;   // ±0.000006 max
      const jy = (rnd() - 0.5) * GAP_Y * 0.6;   // ±0.000005 max
      coords[id] = [
        s.lat + r * STEP_Y + LOT_H / 2 + jy,
        s.lng + c * STEP_X + LOT_W / 2 + jx,
      ];
      n++;
    }
  }
  return coords;
}

const ZONE_A_COORDS = generateLots('A');
const ZONE_B_COORDS = generateLots('B');
Object.assign(LOT_COORDS, ZONE_A_COORDS, ZONE_B_COORDS);

// Setup map layers
const ZONE_CFG = {
    A: { color: '#2563eb', fill: 'rgba(37,99,235,.04)', label: 'ZON A' },
    B: { color: '#7c3aed', fill: 'rgba(124,58,237,.04)', label: 'ZON B' },
};

// Draw zones
function drawZoneBoundary(zon) {
  const cfg = ZONE_CFG[zon];
  const s = ZONE_START[zon];
  const totalW = ZONE_COLS * STEP_X;
  const totalH = ZONE_ROWS * STEP_Y;
  const pad = 0.00005;

  L.rectangle([
    [s.lat - pad, s.lng - pad],
    [s.lat + totalH + pad, s.lng + totalW + pad]
  ],{
    color:cfg.color,weight:2,
    fill:true,fillColor:cfg.fill,fillOpacity:1,opacity:.5
  }).addTo(map);

  // Label di bahagian selatan zon supaya tidak menindih pintu masuk
  L.marker(
    [s.lat - 0.00010, s.lng + totalW / 2],
    {icon:L.divIcon({
      html:`<div style="
        background:white;color:${cfg.color};
        font-size:10px;font-weight:800;
        padding:3px 10px;border-radius:6px;
        border:1.5px solid ${cfg.color}40;
        white-space:nowrap;font-family:'DM Mono',monospace;
        box-shadow:0 2px 6px rgba(0,0,0,.12)
      ">${cfg.label}</div>`,
      className:'',iconAnchor:[28,8]
    })}
  ).addTo(map);
}

// Draw landmarks
function drawLandmarks() {
  // Koridor tengah antara Zon A dan Zon B
  const corridorLng = (ZONE_START.A.lng + ZONE_COLS * STEP_X + ZONE_START.B.lng) / 2;
  const pathPts = [
    ENTRY_GATE,
    [ZONE_START.A.lat, corridorLng],
  ];

  // Bayang koridor
  L.polyline(pathPts,{color:'#d97706',weight:14,opacity:.15,lineCap:'round'}).addTo(map);
  // Garisan dash koridor
  L.polyline(pathPts,{color:'#d97706',weight:2,opacity:.55,dashArray:'9,7',lineCap:'round'}).addTo(map);

  [
    {lat:MASJID_POS[0],  lng:MASJID_POS[1],     icon:'🕌', label:'Masjid Kariah Bangi'},
    {lat:2.90055,        lng:101.77308,         icon:'🅿️', label:'Tempat Letak Kereta'},
    {lat:ENTRY_GATE[0],  lng:ENTRY_GATE[1],     icon:'🚪', label:'Pintu Masuk Utama'},
    // Water Hydrants (Pili Air) near the lots
    {lat:2.89950,        lng:101.77485,         icon:'🚿', label:'Pili Air (Zon A - Utara)'},
    {lat:2.89840,        lng:101.77485,         icon:'🚿', label:'Pili Air (Zon A - Selatan)'},
    {lat:2.89950,        lng:101.77502,         icon:'🚿', label:'Pili Air (Zon B - Utara)'},
    {lat:2.89840,        lng:101.77502,         icon:'🚿', label:'Pili Air (Zon B - Selatan)'},
    // Trees near the lots
    {lat:2.89990,        lng:101.77390,         icon:'🌳', label:'Pokok (Barat Laut Zon A)'},
    {lat:2.89800,        lng:101.77390,         icon:'🌳', label:'Pokok (Barat Daya Zon A)'},
    {lat:2.89990,        lng:101.77590,         icon:'🌳', label:'Pokok (Timur Laut Zon B)'},
    {lat:2.89800,        lng:101.77590,         icon:'🌳', label:'Pokok (Tenggara Zon B)'},
    {lat:2.89900,        lng:101.77385,         icon:'🌳', label:'Pokok (Barat Zon A)'},
    {lat:2.89900,        lng:101.77595,         icon:'🌳', label:'Pokok (Timur Zon B)'},
    {lat:2.89875,        lng:101.77490,         icon:'🌳', label:'Pokok Besar (Koridor Tengah)'},
  ].forEach(lm=>{
    L.marker([lm.lat,lm.lng],{
      icon:L.divIcon({
        html:`<div style="font-size:20px;filter:drop-shadow(0 2px 4px rgba(0,0,0,.2))">${lm.icon}</div>`,
        className:'',iconAnchor:[10,20]
      })
    })
    .bindTooltip(`<b style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px">${lm.label}</b>`,{direction:'top',offset:[0,-16],opacity:.97})
    .addTo(map);
  });
}

// Draw and bind lot click handlers
function drawAllLots() {
  drawZoneBoundary('A');
  drawZoneBoundary('B');

  Object.entries(LOT_COORDS).forEach(([id, coord]) => {
    const occupied = OCCUPIED_GRAVES[id] ? true : false;
    const zone = id.startsWith('A') ? 'A' : 'B';
    const cfg = ZONE_CFG[zone];

    L.rectangle([
      [coord[0]-LOT_H/2, coord[1]-LOT_W/2],
      [coord[0]+LOT_H/2, coord[1]+LOT_W/2]
    ], {
      color: occupied ? '#dc2626' : cfg.color,
      weight: 1.2,
      fillOpacity: occupied ? 0.22 : 0.08,
      fillColor: occupied ? '#fecaca' : cfg.color,
    }).addTo(map)
    .bindTooltip(`<div style="font-size:11px;font-weight:700">Lot ${id}<br>${occupied ? 'Penuh (Arwah: ' + OCCUPIED_GRAVES[id].nama + ')' : 'Kosong'}</div>`, { direction: 'top', offset: [0, -5] })
    .on('click', () => {
      openLotDialog(id, coord, occupied);
    });
  });
}

// Dialog trigger
function openLotDialog(id, coord, occupied) {
    document.getElementById('formLotId').value = id;
    document.getElementById('showLat').textContent = coord[0].toFixed(7);
    document.getElementById('showLng').textContent = coord[1].toFixed(7);

    // Reset images previews
    resetImgPreviews();

    const activeBkgEl = document.getElementById('activeBookingDetails');
    const occupiedEl = document.getElementById('occupiedDetails');

    if (occupied) {
        // Lot occupied. Show details and change dialog style to update photos
        document.getElementById('modalTitle').textContent = `Urus Gambar Lot ${id}`;
        document.getElementById('modalSubtitle').textContent = "Kemaskini gambar panduan bagi lot ini.";
        document.getElementById('submitText').textContent = "Kemaskini Gambar";
        
        // Populate deceased details
        const info = OCCUPIED_GRAVES[id];
        document.getElementById('showNamaJenazah').textContent = info.nama;
        document.getElementById('showIcJenazah').textContent = `IC: ${info.ic}`;
        document.getElementById('showMatiJenazah').textContent = `Tarikh Wafat: ${info.mati}`;
        
        if (occupiedEl) occupiedEl.classList.remove('hidden');
        if (activeBkgEl) activeBkgEl.classList.add('hidden');
        
        // Show existing images in previews if available
        setImgPreview('previewImgLeft', 'previewIconLeft', 'previewLblLeft', info.gambar_kiri);
        setImgPreview('previewImgRight', 'previewIconRight', 'previewLblRight', info.gambar_kanan);
        setImgPreview('previewImgPenanda', 'previewIconPenanda', 'previewLblPenanda', info.gambar_penanda);
        
        // Populate description values
        document.getElementById('descLeft').value = info.gambar_kiri_desc || '';
        document.getElementById('descRight').value = info.gambar_kanan_desc || '';
        document.getElementById('descPenanda').value = info.gambar_penanda_desc || '';
        
        // Disable assign submit button if no action is needed
        document.getElementById('btnSubmit').disabled = false;
    } else {
        // Lot is vacant
        document.getElementById('descLeft').value = '';
        document.getElementById('descRight').value = '';
        document.getElementById('descPenanda').value = '';
        
        if (ACTIVE_BOOKING) {
            document.getElementById('modalTitle').textContent = `Tetapkan Lot ${id}`;
            document.getElementById('modalSubtitle').textContent = "Sahkan penetapan permohonan ke lot ini.";
            document.getElementById('submitText').textContent = "Sahkan & Tetapkan Lot";
            document.getElementById('btnSubmit').disabled = false;
            if (activeBkgEl) activeBkgEl.classList.remove('hidden');
        } else {
            document.getElementById('modalTitle').textContent = `Maklumat Lot ${id}`;
            document.getElementById('modalSubtitle').textContent = "Lot ini kosong.";
            document.getElementById('submitText').textContent = "Sahkan & Tetapkan Lot";
            // Disable submit if no active booking is selected
            document.getElementById('btnSubmit').disabled = true;
            if (activeBkgEl) activeBkgEl.classList.add('hidden');
        }
        if (occupiedEl) occupiedEl.classList.add('hidden');
    }

    document.getElementById('modalLot').classList.add('open');
}

function closeModal() {
    document.getElementById('modalLot').classList.remove('open');
}

// File input click trigger helper
function triggerInput(id) {
    document.getElementById(id).click();
}

// File preview handler
function previewFile(input, imgId, iconId, lblId) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            img.src = e.target.result;
            img.style.display = 'block';
            document.getElementById(iconId).style.display = 'none';
            document.getElementById(lblId).style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

function setImgPreview(imgId, iconId, lblId, path) {
    if (path && path.trim() !== '') {
        const img = document.getElementById(imgId);
        img.src = path;
        img.style.display = 'block';
        document.getElementById(iconId).style.display = 'none';
        document.getElementById(lblId).style.display = 'none';
    }
}

function resetImgPreviews() {
    ['Left', 'Right', 'Penanda'].forEach(suffix => {
        document.getElementById(`previewImg${suffix}`).style.display = 'none';
        document.getElementById(`previewImg${suffix}`).src = '';
        document.getElementById(`previewIcon${suffix}`).style.display = 'block';
        document.getElementById(`previewLbl${suffix}`).style.display = 'block';
        document.getElementById(`file${suffix}`).value = '';
    });
}

function updateSideCounts() {
    const totalCount = Object.keys(LOT_COORDS).length;
    const occupiedCount = Object.keys(OCCUPIED_GRAVES).length;
    const emptyCount = totalCount - occupiedCount;
    
    const sbFilled = document.getElementById('sbFilledLots');
    const sbEmpty = document.getElementById('sbEmptyLots');
    if (sbFilled) sbFilled.textContent = occupiedCount;
    if (sbEmpty) sbEmpty.textContent = emptyCount;
}

// Initialize
drawAllLots();
drawLandmarks();
updateSideCounts();

</script>
</body>
</html>
