<?php 
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$nama_waris = "";
$tel_waris  = "";
$error      = "";

// Fetch data user
try {
    $stmt = $pdo->prepare("
        SELECT full_name, no_telefon 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $nama_waris = $user['full_name'];
        $tel_waris  = $user['no_telefon'];
    }
} catch (PDOException $e) {
    $error = "Ralat: " . $e->getMessage();
}

// ============================================================
// AJAX: Semak IC dalam daftar_khairat
// ============================================================
if (isset($_GET['check_ic'])) {
    header('Content-Type: application/json');
    $ic_check = preg_replace('/[^0-9]/', '', trim($_GET['check_ic']));
    if (strlen($ic_check) === 12) {
        try {
            $stmtIC = $pdo->prepare("
                SELECT nama_ahli, status_yuran 
                FROM daftar_khairat 
                WHERE no_ic = ? 
                LIMIT 1
            ");
            $stmtIC->execute([$ic_check]);
            $row = $stmtIC->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['status_yuran'] === 'Dibayar') {
                echo json_encode(['registered' => true, 'nama' => $row['nama_ahli']]);
            } elseif ($row) {
                echo json_encode(['registered' => false, 'nama' => $row['nama_ahli'], 'status' => $row['status_yuran']]);
            } else {
                echo json_encode(['registered' => false]);
            }
        } catch (PDOException $e) {
            echo json_encode(['registered' => false]);
        }
    } else {
        echo json_encode(['registered' => false]);
    }
    exit();
}

// ============================================================
// UPLOAD HELPER
// ============================================================
function uploadDokumen($file_key, $subfolder) {
    if (empty($_FILES[$file_key]['name'])) return '';

    $upload_dir = __DIR__ . "/uploads/{$subfolder}/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext))
        throw new InvalidArgumentException("Format fail tidak dibenarkan. Gunakan JPG, PNG atau PDF.");
    if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024)
        throw new InvalidArgumentException("Saiz fail terlalu besar. Had maksimum 5MB.");
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK)
        throw new RuntimeException("Gagal memuat naik fail. Kod ralat: " . $_FILES[$file_key]['error']);

    $filename    = date('Ymd') . '_' . uniqid('', true) . '.' . $ext;
    $target_path = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_path))
        throw new RuntimeException("Gagal menyimpan fail. Semak permission folder uploads/.");

    return "uploads/{$subfolder}/{$filename}";
}

// ============================================================
// PROCESS FORM — MESTI SEBELUM include header.php
// ============================================================
$path_sijil  = '';
$path_permit = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nama_jenazah = strtoupper(trim($_POST['nama_jenazah']));
        $no_ic        = preg_replace('/[^0-9]/', '', trim($_POST['no_ic']));
        $jantina      = trim($_POST['jantina']);
        $tarikh_wafat = trim($_POST['tarikh_wafat']);
        $masa_wafat   = trim($_POST['masa_wafat']);
        $lokasi_wafat = trim($_POST['lokasi_wafat']);
        $hub_waris    = trim($_POST['hub_waris']);
        $no_tel_alt   = trim($_POST['no_tel_alt']);
        $catatan      = trim($_POST['catatan'] ?? '');

        if ($hub_waris === 'lain') {
            $hub_waris = trim($_POST['hubungan_lain'] ?? '');
            if (empty($hub_waris)) throw new InvalidArgumentException("Sila nyatakan hubungan dengan si mati.");
        }

        if (empty($nama_jenazah))  throw new InvalidArgumentException("Sila masukkan nama penuh si mati.");
        if (strlen($no_ic) !== 12) throw new InvalidArgumentException("No. IC si mati tidak sah. Pastikan 12 digit.");
        if (empty($jantina))       throw new InvalidArgumentException("Sila pilih jantina.");
        if (empty($tarikh_wafat))  throw new InvalidArgumentException("Sila masukkan tarikh kematian.");
        if (empty($masa_wafat))    throw new InvalidArgumentException("Sila masukkan masa kematian.");
        if (empty($lokasi_wafat))  throw new InvalidArgumentException("Sila masukkan lokasi kematian.");
        if (empty($hub_waris))     throw new InvalidArgumentException("Sila pilih hubungan dengan si mati.");
        if (empty($no_tel_alt))    throw new InvalidArgumentException("Sila masukkan nombor telefon alternatif.");
        if (empty($_FILES['dokumen_sijil']['name'])) throw new InvalidArgumentException("Sila muat naik Sijil Kematian.");
        if (empty($_FILES['permit_polis']['name']))  throw new InvalidArgumentException("Sila muat naik Permit Polis.");

        $path_sijil  = uploadDokumen('dokumen_sijil', 'sijil_kematian');
        $path_permit = uploadDokumen('permit_polis',  'permit_polis');

        $pdo->beginTransaction();

        $stmtJ = $pdo->prepare("
            INSERT INTO maklumat_jenazah 
                (nama_jenazah, no_ic, jantina, tarikh_wafat, masa_wafat, lokasi_wafat)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtJ->execute([$nama_jenazah, $no_ic, $jantina, $tarikh_wafat, $masa_wafat, $lokasi_wafat]);
        $jenazah_id = (int)$pdo->lastInsertId();

        $stmtT = $pdo->prepare("
            INSERT INTO tempahan 
                (jenazah_id, user_id, hub_waris, no_tel_alt, catatan, bukti_sijil, permit_polis, status_proses, tarikh_mohon)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $stmtT->execute([$jenazah_id, $user_id, $hub_waris, $no_tel_alt, $catatan, $path_sijil, $path_permit]);
        $tempahan_id = (int)$pdo->lastInsertId();

        $pdo->commit();

        // BUG FIX: redirect boleh jalan sebab header.php belum include lagi
        header("Location: payment.php?type=booking&tempahan_id=" . $tempahan_id);
        exit();

    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($path_sijil  && file_exists(__DIR__.'/'.$path_sijil))  unlink(__DIR__.'/'.$path_sijil);
        if ($path_permit && file_exists(__DIR__.'/'.$path_permit)) unlink(__DIR__.'/'.$path_permit);
        $error = $e->getMessage();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($path_sijil  && file_exists(__DIR__.'/'.$path_sijil))  unlink(__DIR__.'/'.$path_sijil);
        if ($path_permit && file_exists(__DIR__.'/'.$path_permit)) unlink(__DIR__.'/'.$path_permit);
        $error = "Ralat: " . $e->getMessage();
    }
}

// ============================================================
// BARU INCLUDE HEADER — selepas semua logic & redirect selesai
// ============================================================
$title = "Pendaftaran Jenazah"; 
include 'header.php'; 
include 'sidebar.php'; 
?>

<main class="flex-1 p-6 lg:p-12 overflow-y-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950">Tempah Lot Pusara</h1>
            <p class="text-emerald-700 font-medium tracking-tight">Mohon pengurusan jenazah dan tempahan lot</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right hidden md:block">
                <span class="block text-[10px] font-bold text-emerald-600 uppercase tracking-widest leading-none">Status Kariah</span>
                <span class="text-sm font-bold text-emerald-900">Bangi Lama (Bermastautin)</span>
            </div>
            <div class="w-14 h-14 bg-white rounded-2xl shadow-sm border border-emerald-100 flex items-center justify-center text-emerald-800 font-bold text-xl border-b-4 border-b-emerald-700">
                <?php echo strtoupper(substr($nama_waris, 0, 1)); ?>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-r from-rose-50 to-white p-6 rounded-2xl border-l-4 border-l-rose-500 mb-8">
        <div class="flex items-start space-x-4">
            <div class="bg-rose-100 p-3 rounded-xl text-rose-600">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-rose-900 mb-1">Perhatian</h3>
                <p class="text-sm text-slate-600">Sila pastikan semua dokumen yang dimuat naik adalah <span class="font-bold">sah dan jelas</span>. Hanya <strong>JPG, PNG, PDF</strong> dibenarkan (maks. 5MB). Permohonan tidak lengkap akan ditolak.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl mb-6 flex items-start gap-3">
        <i class="fas fa-triangle-exclamation text-red-500 mt-0.5"></i>
        <span class="text-red-800 text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-white overflow-hidden mb-10">
        <div class="bg-emerald-800/5 p-8 border-b border-emerald-100">
            <h3 class="text-xl font-extrabold text-emerald-950">
                <i class="fas fa-file-contract mr-3 text-emerald-600"></i>
                Borang Permohonan Pengurusan Jenazah
            </h3>
        </div>
        
        <form class="p-8 lg:p-12" method="POST" id="bookingForm" enctype="multipart/form-data" novalidate>
            
            <!-- SEKSYEN 1: MAKLUMAT SI MATI -->
            <div class="mb-12">
                <h4 class="text-sm font-bold text-emerald-600 uppercase tracking-widest mb-8 flex items-center">
                    <span class="w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center mr-3 text-xs">1</span>
                    Maklumat Si Mati
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="md:col-span-2 group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Nama Penuh Si Mati <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_jenazah" 
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0 transition-all uppercase"
                               placeholder="Nama penuh" required
                               value="<?php echo htmlspecialchars($_POST['nama_jenazah'] ?? ''); ?>">
                    </div>
                    
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">No. Kad Pengenalan Si Mati <span class="text-rose-500">*</span></label>
                        <input type="text" name="no_ic" id="noIcJenazah"
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0 transition-all"
                               placeholder="000000-00-0000" maxlength="14" required inputmode="numeric">
                        <div class="mt-2 flex items-center gap-2">
                            <span id="icHint" class="text-xs text-slate-400">Masukkan 12 digit nombor IC</span>
                        </div>
                        <div id="icStatusBox" class="mt-2 hidden">
                            <span id="icStatusPill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"></span>
                        </div>
                    </div>
                    
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Jantina <span class="text-rose-500">*</span></label>
                        <select name="jantina" class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0 appearance-none" required>
                            <option value="">-- Pilih --</option>
                            <option value="Lelaki"    <?php echo ($_POST['jantina'] ?? '') === 'Lelaki'    ? 'selected' : ''; ?>>Lelaki</option>
                            <option value="Perempuan" <?php echo ($_POST['jantina'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Tarikh Kematian <span class="text-rose-500">*</span></label>
                        <input type="date" name="tarikh_wafat"
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0" required
                               value="<?php echo htmlspecialchars($_POST['tarikh_wafat'] ?? ''); ?>">
                    </div>

                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Masa Kematian <span class="text-rose-500">*</span></label>
                        <input type="time" name="masa_wafat"
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0" required
                               value="<?php echo htmlspecialchars($_POST['masa_wafat'] ?? ''); ?>">
                    </div>
                    
                    <div class="md:col-span-2 group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Lokasi Kematian <span class="text-rose-500">*</span></label>
                        <input type="text" name="lokasi_wafat"
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0"
                               placeholder="Contoh: Hospital Serdang" required
                               value="<?php echo htmlspecialchars($_POST['lokasi_wafat'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- SEKSYEN 2: MAKLUMAT WARIS -->
            <div class="mb-12">
                <h4 class="text-sm font-bold text-emerald-600 uppercase tracking-widest mb-8 flex items-center">
                    <span class="w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center mr-3 text-xs">2</span>
                    Maklumat Waris / Pemohon
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Nama Waris</label>
                        <input type="text" name="nama_waris"
                               value="<?php echo htmlspecialchars($nama_waris); ?>" 
                               class="w-full bg-slate-50 border-0 border-b-2 border-slate-200 px-2 py-3 text-slate-500 cursor-not-allowed" readonly>
                    </div>
                    
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Hubungan <span class="text-rose-500">*</span></label>
                        <select name="hub_waris" id="hubunganSelect"
                                class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0" required
                                onchange="toggleHubunganLain()">
                            <option value="">-- Pilih Hubungan --</option>
                            <option value="Bapa">Bapa</option>
                            <option value="Ibu">Ibu</option>
                            <option value="Suami">Suami</option>
                            <option value="Isteri">Isteri</option>
                            <option value="Anak">Anak</option>
                            <option value="Adik-beradik">Adik-beradik</option>
                            <option value="lain">Lain-lain</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 group hidden" id="hubunganLainWrapper">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Nyatakan Hubungan <span class="text-rose-500">*</span></label>
                        <input type="text" name="hubungan_lain" id="hubunganLainInput"
                               class="w-full bg-emerald-50/30 border-0 border-b-2 border-emerald-300 px-0 py-3 focus:border-emerald-500 focus:ring-0">
                    </div>
                    
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">No. Telefon Waris</label>
                        <input type="tel" name="telefon_waris"
                               value="<?php echo htmlspecialchars($tel_waris); ?>"
                               class="w-full bg-slate-50 border-0 border-b-2 border-slate-200 px-2 py-3 text-slate-500 cursor-not-allowed" readonly>
                    </div>
                    
                    <div class="group">
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">No. Telefon Alternatif <span class="text-rose-500">*</span></label>
                        <input type="tel" name="no_tel_alt"
                               class="w-full bg-transparent border-0 border-b-2 border-slate-200 px-0 py-3 focus:border-emerald-500 focus:ring-0"
                               placeholder="01X-XXXXXXX" required inputmode="tel"
                               value="<?php echo htmlspecialchars($_POST['no_tel_alt'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- SEKSYEN 3: MUAT NAIK DOKUMEN -->
            <div class="mb-12">
                <h4 class="text-sm font-bold text-emerald-600 uppercase tracking-widest mb-8 flex items-center">
                    <span class="w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center mr-3 text-xs">3</span>
                    Muat Naik Dokumen
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">
                            Sijil / Dokumen Kematian <span class="text-rose-500">*</span>
                        </label>
                        <label for="input_sijil" id="zone_sijil"
                               class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-2xl p-8 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                            <input type="file" name="dokumen_sijil" id="input_sijil"
                                   accept=".jpg,.jpeg,.png,.pdf" required
                                   class="hidden"
                                   onchange="handleUpload(this, 'zone_sijil', 'name_sijil', 'icon_sijil')">
                            <i id="icon_sijil" class="fas fa-file-medical text-3xl text-slate-300 mb-3"></i>
                            <p class="text-sm font-semibold text-slate-600">Klik untuk pilih fail</p>
                            <p class="text-xs text-slate-400 mt-1">JPG, PNG, PDF — Max 5MB</p>
                            <p id="name_sijil" class="text-xs font-bold text-emerald-700 mt-2 hidden"></p>
                        </label>
                        <p class="text-xs text-slate-400 mt-2">Sijil Kematian atau Notis Kematian Hospital</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">
                            Permit Polis <span class="text-rose-500">*</span>
                        </label>
                        <label for="input_permit" id="zone_permit"
                               class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-2xl p-8 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                            <input type="file" name="permit_polis" id="input_permit"
                                   accept=".jpg,.jpeg,.png,.pdf" required
                                   class="hidden"
                                   onchange="handleUpload(this, 'zone_permit', 'name_permit', 'icon_permit')">
                            <i id="icon_permit" class="fas fa-shield-halved text-3xl text-slate-300 mb-3"></i>
                            <p class="text-sm font-semibold text-slate-600">Klik untuk pilih fail</p>
                            <p class="text-xs text-slate-400 mt-1">JPG, PNG, PDF — Max 5MB</p>
                            <p id="name_permit" class="text-xs font-bold text-emerald-700 mt-2 hidden"></p>
                        </label>
                        <p class="text-xs text-slate-400 mt-2">Permit Polis jika kematian luar biasa</p>
                    </div>
                </div>
            </div>

            <!-- CATATAN -->
            <div class="mb-10">
                <label class="block text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3">Catatan Tambahan</label>
                <textarea name="catatan" rows="3"
                          class="w-full bg-slate-50 border-0 border-b-2 border-slate-200 p-4 focus:ring-0 rounded-t-xl"
                          placeholder="Permintaan khas jika ada..."><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
            </div>

            <button type="submit" name="submit_booking" id="submitBtn"
                    class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 text-white font-bold py-4 rounded-2xl shadow-lg hover:shadow-xl transition-all">
                Teruskan ke Pembayaran
            </button>
        </form>
    </div>
        <footer class="text-center text-slate-400 text-[10px] mt-10 tracking-widest uppercase pb-6">
        &copy; 2026 SmartGrave Bangi Lama. Sistem Pengurusan Jenazah Digital Patuh Syariah.
    </footer>
</main>

<script>
const icInput = document.getElementById('noIcJenazah');
const icHint  = document.getElementById('icHint');
const icBox   = document.getElementById('icStatusBox');
const icPill  = document.getElementById('icStatusPill');
let   icTimer = null;

icInput.addEventListener('input', function () {
    let digits = this.value.replace(/\D/g, '').slice(0, 12);
    let f = digits;
    if (digits.length > 6) f = digits.slice(0,6) + '-' + digits.slice(6);
    if (digits.length > 8) f = digits.slice(0,6) + '-' + digits.slice(6,8) + '-' + digits.slice(8);
    this.value = f;

    if (digits.length === 12) {
        icHint.textContent = '✓ Nombor IC lengkap';
        icHint.classList.replace('text-slate-400', 'text-emerald-600');
        clearTimeout(icTimer);
        icTimer = setTimeout(() => checkKhairat(digits), 400);
    } else {
        icHint.textContent = digits.length + ' / 12 digit';
        icHint.classList.replace('text-emerald-600', 'text-slate-400');
        icBox.classList.add('hidden');
        resetBtnText();
    }
});

function checkKhairat(digits) {
    icPill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500';
    icPill.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyemak...';
    icBox.classList.remove('hidden');

    fetch('booking.php?check_ic=' + encodeURIComponent(digits))
        .then(r => r.json())
        .then(data => {
            if (data.registered) {
                icPill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800';
                icPill.innerHTML = '<i class="fas fa-shield-check"></i> Ahli Khairat Aktif — Pengebumian PERCUMA';
                document.getElementById('submitBtn').textContent = 'Teruskan ke Pembayaran — RM 0.00 (Ahli Khairat)';
            } else if (data.nama) {
                icPill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700';
                icPill.innerHTML = '<i class="fas fa-clock"></i> Yuran Khairat Belum Dibayar — RM1,100.00 dikenakan';
                resetBtnText();
            } else {
                icPill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600';
                icPill.innerHTML = '<i class="fas fa-user-xmark"></i> Bukan Ahli Khairat — RM1,100.00 dikenakan';
                resetBtnText();
            }
        })
        .catch(() => icBox.classList.add('hidden'));
}

function resetBtnText() {
    document.getElementById('submitBtn').textContent = 'Teruskan ke Pembayaran';
}

function handleUpload(input, zoneId, nameId, iconId) {
    const zone   = document.getElementById(zoneId);
    const nameEl = document.getElementById(nameId);
    const iconEl = document.getElementById(iconId);

    if (!input.files || !input.files[0]) return;

    const file   = input.files[0];
    const sizeMB = (file.size / 1024 / 1024).toFixed(2);

    if (file.size > 5 * 1024 * 1024) {
        alert('Saiz fail terlalu besar. Had maksimum ialah 5MB.');
        input.value = '';
        return;
    }

    zone.classList.remove('border-slate-200');
    zone.classList.add('border-emerald-400', 'bg-emerald-50/50');
    iconEl.classList.remove('text-slate-300');
    iconEl.classList.add('text-emerald-500');
    nameEl.innerHTML = '<i class="fas fa-file-circle-check mr-1"></i>' + file.name + ' (' + sizeMB + ' MB)';
    nameEl.classList.remove('hidden');
}

function toggleHubunganLain() {
    const val     = document.getElementById('hubunganSelect').value;
    const wrapper = document.getElementById('hubunganLainWrapper');
    const input   = document.getElementById('hubunganLainInput');

    if (val === 'lain') {
        wrapper.classList.remove('hidden');
        input.required = true;
    } else {
        wrapper.classList.add('hidden');
        input.required = false;
        input.value    = '';
    }
}

document.querySelector('[name="nama_jenazah"]').addEventListener('input', function () {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});

document.getElementById('bookingForm').addEventListener('submit', function (e) {
    const ic     = document.getElementById('noIcJenazah').value.replace(/\D/g, '');
    const nama   = document.querySelector('[name="nama_jenazah"]').value.trim();
    const hub    = document.getElementById('hubunganSelect').value;
    const sijil  = document.getElementById('input_sijil').files.length;
    const permit = document.getElementById('input_permit').files.length;

    if (!nama)            { e.preventDefault(); alert('Sila masukkan nama penuh si mati.'); return; }
    if (ic.length !== 12) { e.preventDefault(); alert('No. IC si mati mesti 12 digit.'); return; }
    if (!hub)             { e.preventDefault(); alert('Sila pilih hubungan dengan si mati.'); return; }
    if (hub === 'lain' && !document.getElementById('hubunganLainInput').value.trim()) {
        e.preventDefault(); alert('Sila nyatakan hubungan.'); return;
    }
    if (!sijil)  { e.preventDefault(); alert('Sila muat naik Sijil Kematian.'); return; }
    if (!permit) { e.preventDefault(); alert('Sila muat naik Permit Polis.'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses, sila tunggu...';
});
</script>