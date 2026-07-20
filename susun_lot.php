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

// ---------------------------------------------------------------
// Fungsi Pembantu PHP untuk Pengesyoran Lot
// ---------------------------------------------------------------
if (!function_exists('getCategoryFromIcPHP')) {
    function getCategoryFromIcPHP($ic) {
        if (!$ic) return 'Dewasa';
        $cleanIc = preg_replace('/[^0-9]/', '', $ic);
        if (strlen($cleanIc) !== 12) return 'Dewasa';
        
        $yearPart = (int)substr($cleanIc, 0, 2);
        $currentYear = (int)date('Y');
        $century = ($yearPart + 2000 > $currentYear) ? 1900 : 2000;
        $birthYear = $century + $yearPart;
        $age = $currentYear - $birthYear;
        
        return $age < 12 ? 'Kanak-kanak' : 'Dewasa';
    }
}

if (!function_exists('dapatkanKoordinatLotPHP')) {
    function dapatkanKoordinatLotPHP($lot_id) {
        $zon = substr($lot_id, 0, 1);
        $num = (int)substr($lot_id, 1);
        
        $zone_starts = [
            'A' => ['lat' => 2.89886, 'lng' => 101.77490],
            'B' => ['lat' => 2.89886, 'lng' => 101.77542],
            'C' => ['lat' => 2.89992, 'lng' => 101.77542],
        ];
        
        $s = $zone_starts[$zon] ?? ['lat' => 2.89886, 'lng' => 101.77490];
        
        $lot_w = ($zon === 'C') ? 0.000016 : 0.000022;
        $lot_h = ($zon === 'C') ? 0.000028 : 0.000042;
        
        $step_x = ($zon === 'C') ? 0.000021 : 0.000028;
        $step_y = ($zon === 'C') ? 0.000033 : 0.000050;
        
        $cols = ($zon === 'C') ? 27 : (($zon === 'B' ? 17 : 13));
        
        $idx = $num - 1;
        $r = floor($idx / $cols);
        $c = $idx % $cols;
        
        $lat = $s['lat'] + $r * $step_y + $lot_h / 2;
        $lng = $s['lng'] + $c * $step_x + $lot_w / 2;
        
        return ['lat' => $lat, 'lng' => $lng];
    }
}

if (!function_exists('jarakKePintuPHP')) {
    function jarakKePintuPHP($lot_id) {
        $coord = dapatkanKoordinatLotPHP($lot_id);
        $gate = ['lat' => 2.90016, 'lng' => 101.77537];
        $dlat = $coord['lat'] - $gate['lat'];
        $dlng = $coord['lng'] - $gate['lng'];
        return sqrt($dlat * $dlat + $dlng * $dlng);
    }
}

if (!function_exists('dapatkanSyorLot')) {
    function dapatkanSyorLot($pdo, $active_booking, &$skipped_lots = [], &$last_lot = '') {
        if (!$active_booking) return null;
        
        $ic = $active_booking['jenazah_ic'] ?? '';
        $kategori = getCategoryFromIcPHP($ic);
        
        // Tentukan zon sasaran
        $zon_sasaran = 'A';
        if ($kategori === 'Kanak-kanak') {
            $zon_sasaran = 'C';
        } else {
            // Cari zon dewasa terakhir yang digunakan (A atau B)
            $stmt = $pdo->query("
                SELECT lp.no_lot
                FROM lot_pusara lp
                JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
                WHERE (lp.no_lot LIKE 'A%' OR lp.no_lot LIKE 'B%')
                  AND lp.status_lot IN ('Penuh', 'Ditetapkan')
                ORDER BY j.id DESC
                LIMIT 1
            ");
            $last_adult = $stmt ? $stmt->fetchColumn() : null;
            if ($last_adult) {
                $zon_sasaran = substr($last_adult, 0, 1);
            } else {
                $zon_sasaran = 'A';
            }
        }
        
        // Ambil status lot bukan kosong dari pangkalan data
        $stmt = $pdo->query("SELECT no_lot, status_lot FROM lot_pusara");
        $non_vacant = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $non_vacant[$row['no_lot']] = $row['status_lot'];
            }
        }
        
        // Cari lot terakhir yang digunakan di zon sasaran untuk keperluan ulasan AI
        $stmt_last = $pdo->prepare("
            SELECT lp.no_lot
            FROM lot_pusara lp
            JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
            WHERE lp.no_lot LIKE ?
              AND lp.status_lot IN ('Penuh', 'Ditetapkan')
            ORDER BY j.id DESC
            LIMIT 1
        ");
        $stmt_last->execute([$zon_sasaran . '%']);
        $last_lot = $stmt_last->fetchColumn();
        
        // Konfigurasi baris dan lajur mengikut zon
        $capacities = ['A' => 338, 'B' => 357, 'C' => 135];
        $cols_config = ['A' => 13, 'B' => 17, 'C' => 27];
        
        $cols = $cols_config[$zon_sasaran] ?? 13;
        $total_lots = $capacities[$zon_sasaran] ?? 338;
        $max_rows = $total_lots / $cols;
        
        // Cari lot kosong bermula dari baris paling bawah (paling jauh) ke atas secara mendatar
        for ($r = 0; $r < $max_rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $n = $r * $cols + $c + 1;
                $candidate_id = $zon_sasaran . str_pad($n, 3, '0', STR_PAD_LEFT);
                $status = $non_vacant[$candidate_id] ?? 'Tersedia';
                
                if ($status === 'Tersedia') {
                    return $candidate_id;
                } else {
                    if ($status === 'Mendap' || $status === 'Tidak Sesuai') {
                        $skipped_lots[] = $candidate_id . ' (' . ($status === 'Mendap' ? 'Mendap' : 'Tidak Sesuai') . ')';
                    }
                }
            }
        }
        
        // Fallback: jika zon sasaran penuh, cari di zon lain bermula dari baris bawah
        foreach ($capacities as $zon_key => $lim) {
            if ($zon_key === $zon_sasaran) continue;
            
            $cl = $cols_config[$zon_key];
            $mr = $lim / $cl;
            for ($r = 0; $r < $mr; $r++) {
                for ($c = 0; $c < $cl; $c++) {
                    $n = $r * $cl + $c + 1;
                    $candidate_id = $zon_key . str_pad($n, 3, '0', STR_PAD_LEFT);
                    $status = $non_vacant[$candidate_id] ?? 'Tersedia';
                    if ($status === 'Tersedia') {
                        return $candidate_id;
                    } else {
                        if ($status === 'Mendap' || $status === 'Tidak Sesuai') {
                            $skipped_lots[] = $candidate_id . ' (' . ($status === 'Mendap' ? 'Mendap' : 'Tidak Sesuai') . ')';
                        }
                    }
                }
            }
        }
        
        return null;
    }
}

// ---------------------------------------------------------------
// API Endpoint: Dapatkan Ulasan AI Gemini (AJAX)
// ---------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_recommendation') {
    header('Content-Type: application/json');
    $tempahan_id = intval($_GET['tempahan_id'] ?? 0);
    $recommended_lot = trim($_GET['recommended_lot'] ?? '');
    
    if (!$tempahan_id || !$recommended_lot) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak mencukupi.']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT t.id, j.nama_jenazah, j.no_ic, j.jantina
        FROM tempahan t
        JOIN maklumat_jenazah j ON j.id = t.jenazah_id
        WHERE t.id = ?
    ");
    $stmt->execute([$tempahan_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['status' => 'error', 'message' => 'Tempahan tidak ditemui.']);
        exit;
    }
    
    $nama_jenazah = $booking['nama_jenazah'];
    $ic = $booking['no_ic'];
    $jantina = $booking['jantina'];
    $kategori = getCategoryFromIcPHP($ic);
    
    $skipped_param = trim($_GET['skipped'] ?? '');
    $last_param = trim($_GET['last'] ?? '');
    
    $prompt = "Sebagai asisten AI SmartGrave (sistem pengurusan kubur pintar), berikan ulasan ringkas (maksimum 2-3 kalimat dalam Bahasa Melayu yang sopan) tentang cadangan lot kubur ini kepada Admin.\n\n"
            . "Maklumat Jenazah:\n"
            . "- Nama: $nama_jenazah\n"
            . "- Kategori: $kategori\n"
            . "- Jantina: $jantina\n\n"
            . "Cadangan Lot: $recommended_lot\n"
            . "Sebab Cadangan:\n"
            . "- Jenis/kategori zon bersesuaian.\n";
    if ($last_param) {
        $prompt .= "- Melanjutkan susunan dari kubur terakhir yang digunakan: Lot $last_param.\n";
    }
    if ($skipped_param) {
        $prompt .= "- Melangkau lot rosak/mendap: $skipped_param.\n";
    }
    $prompt .= "\nTulis ulasan anda secara terus dalam Bahasa Melayu yang santun dan profesional. Jangan sertakan intro seperti 'Berikut adalah ulasan anda:' atau tanda format markdown. Mulakan dengan ayat mesra seperti 'Lot $recommended_lot dicadangkan kerana...'";
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 300,
            "thinkingConfig" => [
                "thinkingBudget" => 0
            ]
        ]
    ];
    
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseDecoded = json_decode($response, true);
    $replyText = '';
    
    if ($httpCode === 200 && isset($responseDecoded['candidates'][0]['content']['parts'][0]['text'])) {
        $replyText = trim($responseDecoded['candidates'][0]['content']['parts'][0]['text']);
    } else {
        $replyText = "Lot $recommended_lot dicadangkan secara automatik berasaskan kategori " . strtolower($kategori) . " dan menyambung susunan dari lot " . ($last_param ?: "sebelum ini") . ".";
        if ($skipped_param) {
            $replyText .= " Lot yang mengalami mendapan (" . $skipped_param . ") telah dilewati.";
        }
    }
    
    echo json_encode(['status' => 'success', 'explanation' => $replyText]);
    exit;
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
    
    // Kemas kini check constraint status_lot untuk membenarkan status Mendap dan Tidak Sesuai
    $pdo->exec("ALTER TABLE lot_pusara DROP CONSTRAINT IF EXISTS lot_pusara_status_lot_check");
    $pdo->exec("ALTER TABLE lot_pusara ADD CONSTRAINT lot_pusara_status_lot_check CHECK (status_lot IN ('Tersedia', 'Ditetapkan', 'Penuh', 'Mendap', 'Tidak Sesuai'))");
} catch (Exception $ex) {}

// Handle POST: Assign vacant lot, update guide photos, or update soil status
$msg_success = '';
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_assign']) && $_POST['action_assign'] === '1') {
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
        if (!function_exists('uploadGraveImage')) {
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
                
                // Insert or update in lot_pusara with 'Ditetapkan' status
                $stmt_save = $pdo->prepare("
                    INSERT INTO lot_pusara (no_lot, status_lot, jenazah_id, gambar_kiri, gambar_kanan, gambar_penanda, gambar_kiri_desc, gambar_kanan_desc, gambar_penanda_desc)
                    VALUES (?, 'Ditetapkan', ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT (no_lot)
                    DO UPDATE SET status_lot = 'Ditetapkan', 
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
                $notif_msg = "Selesai! Lokasi pusara bagi arwah " . $nama_jenazah . " telah ditetapkan di Lot " . $lot_id . " (Status: Ditetapkan). Panduan navigasi kini sedia untuk dirujuk di halaman Carian Pusara.";
                $stmt_notif = $pdo->prepare("INSERT INTO notifikasi (user_id, mesej) VALUES (?, ?)");
                $stmt_notif->execute([$waris_id, $notif_msg]);
                
                $pdo->commit();
                
                // Redirect back to admin dashboard with success
                echo "<script>window.location.href='admin_dashboard.php?berjaya=lulus&tempahan_id=$tempahan_id';</script>";
                exit;
            } else {
                // Photo update for already occupied lot
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
                    WHERE no_lot = ? AND status_lot IN ('Penuh', 'Ditetapkan')
                ");
                $stmt_update->execute([$gambar_kiri_path, $gambar_kanan_path, $gambar_penanda_path, $gambar_kiri_desc, $gambar_kanan_desc, $gambar_penanda_desc, $lot_id]);
                
                $pdo->commit();
                $msg_success = "Gambar panduan bagi Lot $lot_id berjaya dikemaskini!";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_error = "Gagal memproses penetapan: " . $e->getMessage();
        }
    } else if (isset($_POST['action_update_status']) && $_POST['action_update_status'] === '1') {
        $lot_id = trim($_POST['lot_id'] ?? '');
        $new_status = trim($_POST['new_status'] ?? '');
        
        try {
            $pdo->beginTransaction();
            if ($new_status === 'Tersedia') {
                $stmt = $pdo->prepare("DELETE FROM lot_pusara WHERE no_lot = ?");
                $stmt->execute([$lot_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO lot_pusara (no_lot, status_lot)
                    VALUES (?, ?)
                    ON CONFLICT (no_lot)
                    DO UPDATE SET status_lot = EXCLUDED.status_lot
                ");
                $stmt->execute([$lot_id, $new_status]);
            }
            $pdo->commit();
            $msg_success = "Status Lot $lot_id berjaya dikemaskini kepada $new_status!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_error = "Gagal mengemaskini status: " . $e->getMessage();
        }
    }
}

// Fetch active booking if tempahan_id is passed
$tempahan_id = isset($_GET['tempahan_id']) ? intval($_GET['tempahan_id']) : 0;
$active_booking = null;
$syor_lot = null;
$skipped_lots = [];
$last_lot = '';

if ($tempahan_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.id, t.status_proses, u.full_name AS waris_nama, u.no_telefon AS waris_tel,
               j.id AS jenazah_id, j.nama_jenazah, j.no_ic AS jenazah_ic, j.jantina
        FROM tempahan t
        JOIN users u ON u.id = t.user_id
        JOIN maklumat_jenazah j ON j.id = t.jenazah_id
        WHERE t.id = ?
    ");
    $stmt->execute([$tempahan_id]);
    $active_booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($active_booking) {
        $syor_lot = dapatkanSyorLot($pdo, $active_booking, $skipped_lots, $last_lot);
    }
}

// Fetch occupied/modified graves from DB
$occupied_graves = [];
try {
    $stmt = $pdo->query("
        SELECT lp.no_lot AS id, lp.status_lot, j.nama_jenazah AS nama, j.no_ic AS ic, j.tarikh_wafat AS mati,
               lp.gambar_kiri, lp.gambar_kanan, lp.gambar_penanda,
               lp.gambar_kiri_desc, lp.gambar_kanan_desc, lp.gambar_penanda_desc
        FROM lot_pusara lp
        LEFT JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
    ");
    if ($stmt) {
        $db_graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($db_graves as $g) {
            $id = $g['id'];
            $zon = substr($id, 0, 1);
            $mati = $g['mati'] ? date('d/m/Y', strtotime($g['mati'])) : '—';
            $ic = $g['ic'] ? preg_replace('/[^0-9]/', '', $g['ic']) : '';
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
                'status_lot' => $g['status_lot'],
                'nama' => $g['nama'] ?? '',
                'ic' => $g['ic'] ?? '',
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
                    
                    <!-- Cadangan Lot Pintar (Smart Recommendation) -->
                    <?php if ($syor_lot): ?>
                    <div class="bg-gradient-to-br from-emerald-800 to-emerald-950 text-white rounded-2xl p-4 border border-emerald-700/50 shadow-md space-y-3 mt-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-extrabold text-emerald-300 uppercase tracking-widest flex items-center gap-1">
                                <i class="fas fa-robot animate-bounce text-emerald-400"></i> Syor Lot Pintar
                            </span>
                            <span class="text-xs bg-emerald-500 text-emerald-950 px-2.5 py-0.5 rounded-full font-extrabold font-mono"><?= $syor_lot ?></span>
                        </div>
                        
                        <!-- AI Explanation -->
                        <div class="space-y-1.5">
                            <span class="text-[9px] text-emerald-300 font-bold uppercase tracking-wider block">Ulasan Pintar (Gemini AI)</span>
                            <div id="aiSyorUlasan" class="text-[11px] text-emerald-50 leading-relaxed font-medium">
                                <span class="flex items-center gap-1.5 text-emerald-300">
                                    <i class="fas fa-circle-notch animate-spin"></i> Menjana ulasan AI...
                                </span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <button type="button" onclick="pilihLotCadangan('<?= $syor_lot ?>')" class="w-full py-2 bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-black text-xs rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm">
                            <i class="fas fa-location-arrow"></i> Guna Lot Cadangan (<?= $syor_lot ?>)
                        </button>
                    </div>
                    <?php endif; ?>
                    
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
                    <h3 class="text-3xl font-black mt-1">830 <span class="text-xs font-normal text-emerald-400">Pusara</span></h3>
                    
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/5">
                            <span class="text-[9px] font-bold text-emerald-200 uppercase tracking-wider">Terisi</span>
                            <span class="text-lg font-black block mt-0.5 text-red-300" id="sbFilledLots">0</span>
                        </div>
                        <div class="bg-white/10 p-3 rounded-2xl border border-white/5">
                            <span class="text-[9px] font-bold text-emerald-200 uppercase tracking-wider">Tersedia</span>
                            <span class="text-lg font-black block mt-0.5 text-green-300" id="sbEmptyLots">830</span>
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
                            Zon A (Tersedia - Dewasa)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-purple-50 border border-purple-500 flex-shrink-0"></div>
                            Zon B (Tersedia - Dewasa)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-teal-50 border border-teal-600 flex-shrink-0"></div>
                            Zon C (Tersedia - Kanak-Kanak)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-yellow-50 border border-yellow-600 flex-shrink-0"></div>
                            Lot Ditetapkan (Belum Kebumi)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-red-50 border border-red-500 flex-shrink-0"></div>
                            Lot Terisi (Dikebumikan)
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <div class="w-4 h-4 rounded bg-zinc-600 border border-black flex-shrink-0"></div>
                            Tanah Rosak / Tidak Sesuai
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
            <input type="hidden" name="action_assign" id="actionAssign" value="1">
            <input type="hidden" name="action_update_status" id="actionUpdateStatus" value="0">
            <input type="hidden" name="lot_id" id="formLotId">
            <input type="hidden" name="tempahan_id" value="<?php echo $tempahan_id; ?>">

            <div class="p-6 space-y-4">
                
                <!-- Recommendation / Warning Box -->
                <div id="recBox"></div>
                
                <!-- Active Booking Details in Form -->
                <div id="activeBookingDetails" class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-xs text-emerald-900 space-y-1.5 <?php echo $active_booking ? '' : 'hidden'; ?>">
                    <p class="font-bold text-[10px] text-emerald-700 uppercase tracking-wide">Maklumat Kemasukan Jenazah</p>
                    <p class="text-sm font-extrabold text-emerald-950"><?php echo $active_booking ? htmlspecialchars($active_booking['nama_jenazah']) : ''; ?></p>
                    <p>IC: <?php echo $active_booking ? htmlspecialchars($active_booking['jenazah_ic']) : ''; ?></p>
                </div>

                <!-- Soil Status Box -->
                <div id="soilStatusBox" class="hidden">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Status Keadaan Tanah</label>
                    <select name="new_status" id="selectSoilStatus" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-xl bg-white focus:border-emerald-500 focus:outline-none" style="font-family:inherit;">
                        <option value="Tersedia">Tersedia (Tanah Baik)</option>
                        <option value="Mendap">Tanah Mendap / Rosak</option>
                        <option value="Tidak Sesuai">Tanah Tidak Sesuai (Berbatu/Keras)</option>
                    </select>
                </div>

                <!-- Confirm Burial Box -->
                <div id="burialConfirmBox" class="hidden border-t border-gray-100 pt-4 space-y-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Status Tindakan Pengebumian</label>
                    <button type="button" onclick="confirmBurialClick()" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fas fa-check-double"></i> Sahkan Selesai Pengebumian (Tukar status ke Dikebumikan)
                    </button>
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
                    <i class="fas fa-save"></i> <span id="submitText">Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Seeded data from PHP
const OCCUPIED_GRAVES = <?php echo json_encode($occupied_graves); ?>;
const ACTIVE_BOOKING = <?php echo $active_booking ? json_encode($active_booking) : 'null'; ?>;
const RECOMMENDED_LOT_ID = <?php echo json_encode($syor_lot); ?>;
const SKIPPED_LOTS = <?php echo json_encode(implode(', ', $skipped_lots)); ?>;
const LAST_LOT = <?php echo json_encode($last_lot); ?>;

// Map Coordinates
const MASJID_POS = [2.90061, 101.78549];
const CENTER = [2.89966, 101.77551];
const ENTRY_GATE = [2.90016, 101.77537];

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
const LOT_W  = 0.000025;
const LOT_H  = 0.000042;
const GAP_X  = 0.000008;
const GAP_Y  = 0.000008;
const STEP_X = LOT_W + GAP_X;  // 0.000033 per column
const STEP_Y = LOT_H + GAP_Y;  // 0.000050 per row
const ZONE_COLS = 11;
const ZONE_ROWS = 26;

const ZONE_START = {
  A: {lat: 2.89886, lng: 101.77490},
  B: {lat: 2.89886, lng: 101.77542},
  C: {lat: 2.89992, lng: 101.77542}, // Zon C (Kanak-kanak) diletakkan di atas Zon B (starts after B)
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
  const rnd = seededRandom(zon === 'A' ? 31 : (zon === 'B' ? 67 : 89));
  let n = 1;
  const rows = zon === 'C' ? 5 : (zon === 'B' ? 21 : ZONE_ROWS);
  const cols = zon === 'C' ? 27 : (zon === 'B' ? 17 : 13);
  const step_x = zon === 'C' ? 0.000021 : STEP_X;
  const step_y = zon === 'C' ? 0.000033 : STEP_Y;
  const lot_w = zon === 'C' ? 0.000016 : LOT_W;
  const lot_h = zon === 'C' ? 0.000028 : LOT_H;
  const gap_x = zon === 'C' ? 0.000005 : GAP_X;
  const gap_y = zon === 'C' ? 0.000005 : GAP_Y;
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const id = `${zon}${String(n).padStart(3,'0')}`;
      const jx = (rnd() - 0.5) * gap_x * 0.6;
      const jy = (rnd() - 0.5) * gap_y * 0.6;
      coords[id] = [
        s.lat + r * step_y + lot_h / 2 + jy,
        s.lng + c * step_x + lot_w / 2 + jx,
      ];
      n++;
    }
  }
  return coords;
}

const ZONE_A_COORDS = generateLots('A');
const ZONE_B_COORDS = generateLots('B');
const ZONE_C_COORDS = generateLots('C');
Object.assign(LOT_COORDS, ZONE_A_COORDS, ZONE_B_COORDS, ZONE_C_COORDS);

// Setup map layers
const ZONE_CFG = {
    A: { color: '#2563eb', fill: 'rgba(37,99,235,.04)', label: 'ZON A (DEWASA)' },
    B: { color: '#7c3aed', fill: 'rgba(124,58,237,.04)', label: 'ZON B (DEWASA)' },
    C: { color: '#0d9488', fill: 'rgba(13,148,136,.04)', label: 'ZON C (KANAK-KANAK)' },
};

// Get Category (Dewasa/Kanak-kanak) from IC
function getCategoryFromIc(ic) {
    if (!ic) return 'Dewasa';
    const cleanIc = ic.replace(/[^0-9]/g, '');
    if (cleanIc.length !== 12) return 'Dewasa';
    
    const yearPart = parseInt(cleanIc.substring(0, 2), 10);
    const currentYear = new Date().getFullYear();
    const century = (yearPart + 2000 > currentYear) ? 1900 : 2000;
    const birthYear = century + yearPart;
    const age = currentYear - birthYear;
    
    return age < 12 ? 'Kanak-kanak' : 'Dewasa';
}

// Find neighboring available lot
function getNeighboringLot(id) {
    const zone = id.charAt(0);
    const num = parseInt(id.substring(1), 10);
    const candidate1 = zone + String(num + 1).padStart(3, '0');
    const candidate2 = zone + String(num - 1).padStart(3, '0');
    
    if (LOT_COORDS[candidate1] && (!OCCUPIED_GRAVES[candidate1] || OCCUPIED_GRAVES[candidate1].status_lot === 'Tersedia')) {
        return candidate1;
    }
    if (LOT_COORDS[candidate2] && (!OCCUPIED_GRAVES[candidate2] || OCCUPIED_GRAVES[candidate2].status_lot === 'Tersedia')) {
        return candidate2;
    }
    return null;
}

// Confirm Burial
function confirmBurialClick() {
    if (confirm("Adakah anda pasti mahu mengesahkan pengebumian ini telah selesai? Status lot akan ditukar ke Dikebumikan.")) {
        document.getElementById('actionAssign').value = "0";
        document.getElementById('actionUpdateStatus').value = "1";
        
        // Tambah option 'Penuh' secara dinamik ke selectSoilStatus supaya nilainya boleh dihantar
        const select = document.getElementById('selectSoilStatus');
        let optPenuh = select.querySelector('option[value="Penuh"]');
        if (!optPenuh) {
            optPenuh = document.createElement('option');
            optPenuh.value = 'Penuh';
            optPenuh.textContent = 'Dikebumikan (Penuh)';
            select.appendChild(optPenuh);
        }
        select.value = 'Penuh';
        
        document.querySelector('#modalLot form').submit();
    }
}

// Intercept form submit to automatically set status updates
document.querySelector('#modalLot form').addEventListener('submit', function(e) {
    const lotId = document.getElementById('formLotId').value;
    const graveInfo = OCCUPIED_GRAVES[lotId];
    const isOccupied = graveInfo && (graveInfo.status_lot === 'Penuh' || graveInfo.status_lot === 'Ditetapkan');
    
    if (!ACTIVE_BOOKING && !isOccupied) {
        document.getElementById('actionAssign').value = "0";
        document.getElementById('actionUpdateStatus').value = "1";
    }
});

// Draw zones
function drawZoneBoundary(zon) {
  const cfg = ZONE_CFG[zon];
  const s = ZONE_START[zon];
  const cols = zon === 'C' ? 27 : (zon === 'B' ? 17 : 13);
  const step_x = zon === 'C' ? 0.000021 : STEP_X;
  const totalW = cols * step_x;
  const totalH = zon === 'C' ? 5 * 0.000033 : (zon === 'B' ? 21 * STEP_Y : ZONE_ROWS * STEP_Y);
  const pad = 0.00003;

  L.rectangle([
    [s.lat - pad, s.lng - pad],
    [s.lat + totalH + pad, s.lng + totalW + pad]
  ],{
    color:cfg.color,weight:2,
    fill:true,fillColor:cfg.fill,fillOpacity:1,opacity:.5
  }).addTo(map);

  // Label: Letak label C di utara (atas) dan label B di selatan (bawah) supaya tidak bertindih
  const labelLat = zon === 'C' ? s.lat + totalH + 0.00004 : s.lat - 0.00004;
  L.marker(
    [labelLat, s.lng + totalW / 2],
    {icon:L.divIcon({
      html:`<div style="
        background:white;color:${cfg.color};
        font-size:10px;font-weight:800;
        padding:4px 10px;border-radius:6px;
        border:1.5px solid ${cfg.color}40;
        white-space:nowrap;font-family:'DM Mono',monospace;
        box-shadow:0 2px 6px rgba(0,0,0,.12);
        transform: translate(-50%, -50%);
        display: inline-block;
      ">${cfg.label}</div>`,
      className:'',
      iconSize: null,
      iconAnchor:[0,0]
    })}
  ).addTo(map);
}

// Draw landmarks
function drawLandmarks() {
  // Koridor tengah antara Zon A dan Zon B/C
  const corridorLng = (ZONE_START.A.lng + 13 * STEP_X + ZONE_START.B.lng) / 2;
  const pathPts = [
    ENTRY_GATE,
    [ZONE_START.A.lat, corridorLng],
  ];

  // Garisan dash koridor tunggal
  L.polyline(pathPts,{color:'#d97706',weight:3,opacity:.7,dashArray:'7,5',lineCap:'round'}).addTo(map);

  [
    {lat:MASJID_POS[0],  lng:MASJID_POS[1],     icon:'🕌', label:'Masjid Kariah Bangi'},
    {lat:2.90025,        lng:101.77520,         icon:'🅿️', label:'Tempat Letak Kereta'},
    {lat:ENTRY_GATE[0],  lng:ENTRY_GATE[1],     icon:'🚪', label:'Pintu Masuk Utama'},
    // Water Hydrants (Pili Air) near the lots
    {lat:2.89986,        lng:101.77486,         icon:'🚿', label:'Pili Air (Zon A - Barat)'},
    {lat:2.89936,        lng:101.77486,         icon:'🚿', label:'Pili Air (Zon A - Barat)'},
    {lat:2.89986,        lng:101.77601,         icon:'🚿', label:'Pili Air (Zon B - Timur)'},
    {lat:2.89936,        lng:101.77601,         icon:'🚿', label:'Pili Air (Zon B - Timur)'},
    // Trees near the lots
    {lat:2.90016,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Laut Zon A)'},
    {lat:2.89916,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Daya Zon A)'},
    {lat:2.90016,        lng:101.77601,         icon:'🌳', label:'Pokok (Timur Laut Zon B/C)'},
    {lat:2.89916,        lng:101.77601,         icon:'🌳', label:'Pokok (Tenggara Zon B)'},
    {lat:2.89966,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Zon A)'},
    {lat:2.89966,        lng:101.77601,         icon:'🌳', label:'Pokok (Timur Zon B)'},
    {lat:2.89966,        lng:corridorLng,       icon:'🌳', label:'Pokok Besar (Koridor Tengah)'},
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
  drawZoneBoundary('C');

  Object.entries(LOT_COORDS).forEach(([id, coord]) => {
    const graveInfo = OCCUPIED_GRAVES[id];
    const zone = id.startsWith('A') ? 'A' : (id.startsWith('B') ? 'B' : 'C');
    const cfg = ZONE_CFG[zone];
    const lot_w = zone === 'C' ? 0.000016 : LOT_W;
    const lot_h = zone === 'C' ? 0.000028 : LOT_H;

    let lotColor = cfg.color;
    let lotFill = cfg.color;
    let lotOpacity = 0.08;
    let tooltipText = `Lot ${id} (${cfg.label})<br><b>Kosong</b>`;
    let isOccupied = false;
    let lotStatus = 'Tersedia';

    if (graveInfo) {
      lotStatus = graveInfo.status_lot;
      if (lotStatus === 'Penuh') {
        lotColor = '#dc2626';
        lotFill = '#fecaca';
        lotOpacity = 0.35;
        tooltipText = `Lot ${id}<br><b style="color:#dc2626">Dikebumikan (Arwah: ${graveInfo.nama})</b>`;
        isOccupied = true;
      } else if (lotStatus === 'Ditetapkan') {
        lotColor = '#d97706';
        lotFill = '#fef08a';
        lotOpacity = 0.35;
        tooltipText = `Lot ${id}<br><b style="color:#d97706">Ditetapkan (Arwah: ${graveInfo.nama})</b>`;
        isOccupied = true;
      } else if (lotStatus === 'Mendap') {
        lotColor = '#000000';
        lotFill = '#3f3f46';
        lotOpacity = 0.85;
        tooltipText = `Lot ${id}<br><b style="color:#ef4444">🚫 TIDAK BOLEH DIGUNAKAN (Tanah Mendap)</b>`;
      } else if (lotStatus === 'Tidak Sesuai') {
        lotColor = '#000000';
        lotFill = '#3f3f46';
        lotOpacity = 0.85;
        tooltipText = `Lot ${id}<br><b style="color:#ef4444">🚫 KAWASAN TIDAK SESUAI (Berbatu/Keras)</b>`;
      }
    }

    if (ACTIVE_BOOKING && id === RECOMMENDED_LOT_ID) {
      lotColor = '#10b981';
      lotFill = '#34d399';
      lotOpacity = 0.55;
      tooltipText = `⭐ <b>CADANGAN PINTAR: Lot ${id}</b><br>${cfg.label}<br><span style="color:#047857">Klik untuk tetapkan lot ini</span>`;
    }

    L.rectangle([
      [coord[0]-lot_h/2, coord[1]-lot_w/2],
      [coord[0]+lot_h/2, coord[1]+lot_w/2]
    ], {
      color: lotColor,
      weight: 1.2,
      fillOpacity: lotOpacity,
      fillColor: lotFill,
    }).addTo(map)
    .bindTooltip(`<div style="font-size:11px;font-weight:700">${tooltipText}</div>`, { direction: 'top', offset: [0, -5] })
    .on('click', () => {
      openLotDialog(id, coord, isOccupied, lotStatus);
    });
  });
}

// Dialog trigger
function openLotDialog(id, coord, occupied, lotStatus) {
    document.getElementById('formLotId').value = id;
    document.getElementById('showLat').textContent = coord[0].toFixed(7);
    document.getElementById('showLng').textContent = coord[1].toFixed(7);

    // Reset images previews
    resetImgPreviews();

    const activeBkgEl = document.getElementById('activeBookingDetails');
    const occupiedEl = document.getElementById('occupiedDetails');
    const recBox = document.getElementById('recBox');
    const soilStatusBox = document.getElementById('soilStatusBox');
    const burialConfirmBox = document.getElementById('burialConfirmBox');

    recBox.innerHTML = '';
    soilStatusBox.classList.add('hidden');
    burialConfirmBox.classList.add('hidden');
    document.getElementById('actionAssign').value = "1";
    document.getElementById('actionUpdateStatus').value = "0";
    document.getElementById('btnSubmit').disabled = false;

    if (occupied) {
        // Lot occupied. Show details and change dialog style to update photos
        document.getElementById('modalTitle').textContent = `Urus Gambar Lot ${id}`;
        document.getElementById('modalSubtitle').textContent = "Kemaskini gambar panduan bagi lot ini.";
        document.getElementById('submitText').textContent = "Simpan";
        
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
        
        document.getElementById('btnSubmit').disabled = false;

        // If status is 'Ditetapkan', show Confirm Burial action
        if (info.status_lot === 'Ditetapkan') {
            burialConfirmBox.classList.remove('hidden');
        }
    } else {
        // Lot is vacant
        document.getElementById('descLeft').value = '';
        document.getElementById('descRight').value = '';
        document.getElementById('descPenanda').value = '';
        
        if (ACTIVE_BOOKING) {
            document.getElementById('modalTitle').textContent = `Tetapkan Lot ${id}`;
            document.getElementById('modalSubtitle').textContent = "Sahkan penetapan permohonan ke lot ini.";
            document.getElementById('submitText').textContent = "Simpan";
            if (activeBkgEl) activeBkgEl.classList.remove('hidden');

            // Category recommendation logic
            const category = getCategoryFromIc(ACTIVE_BOOKING.jenazah_ic);
            const isChildZone = id.startsWith('C');
            const isMatch = (category === 'Kanak-kanak' && isChildZone) || (category === 'Dewasa' && !isChildZone);

            let recHtml = `<div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 leading-relaxed shadow-sm flex flex-col gap-1">`;
            if (category === 'Kanak-kanak') {
                recHtml += `<span class="text-rose-600 font-extrabold flex items-center gap-1"><i class="fas fa-child"></i> Jenazah: Kategori Kanak-kanak</span>`;
                recHtml += `<span>Dicadangkan penetapan di <strong>Zon C (Kanak-kanak)</strong>.</span>`;
            } else {
                recHtml += `<span class="text-blue-600 font-extrabold flex items-center gap-1"><i class="fas fa-user"></i> Jenazah: Kategori Dewasa</span>`;
                recHtml += `<span>Dicadangkan penetapan di <strong>Zon A atau Zon B (Dewasa)</strong>.</span>`;
            }
            if (!isMatch) {
                recHtml += `<span class="text-amber-600 font-extrabold mt-1 flex items-center gap-1"><i class="fas fa-exclamation-triangle"></i> Lot dipilih (${id}) tidak sepadan dengan kategori jenazah!</span>`;
            }
            recHtml += `</div>`;
            recBox.innerHTML = recHtml;

            // Soil condition warning logic (Mendap or Tidak Sesuai)
            if (lotStatus === 'Mendap' || lotStatus === 'Tidak Sesuai') {
                const neighbor = getNeighboringLot(id);
                let warnHtml = `<div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-800 leading-relaxed shadow-sm space-y-2">`;
                warnHtml += `<div class="flex items-center gap-1.5 text-rose-700 text-sm"><i class="fas fa-exclamation-circle"></i> Lot Tidak Sesuai</div>`;
                warnHtml += `<div>Lot ${id} dikategorikan sebagai <strong>${lotStatus === 'Mendap' ? 'Tanah Mendap' : 'Tanah Tidak Sesuai'}</strong> dan tidak boleh ditetapkan.</div>`;
                if (neighbor) {
                    warnHtml += `<div class="bg-white/60 p-2 border border-rose-200/50 rounded-lg text-rose-900 mt-1 flex items-center gap-1"><i class="fas fa-lightbulb text-amber-600"></i> Cadangan lot sebelah yang tersedia: <strong class="bg-rose-100 text-rose-900 px-1.5 py-0.5 rounded">${neighbor}</strong></div>`;
                }
                warnHtml += `</div>`;
                recBox.innerHTML = warnHtml;
                document.getElementById('btnSubmit').disabled = true; // Block assignment to bad lots
            }
        } else {
            // Vacant lot management (Soil Status update)
            document.getElementById('modalTitle').textContent = `Urus Keadaan Lot ${id}`;
            document.getElementById('modalSubtitle').textContent = "Kemaskini keadaan tanah bagi lot kosong ini.";
            document.getElementById('submitText').textContent = "Simpan";
            if (activeBkgEl) activeBkgEl.classList.add('hidden');
            
            soilStatusBox.classList.remove('hidden');
            document.getElementById('selectSoilStatus').value = lotStatus;
            document.getElementById('btnSubmit').disabled = false;
        }
        if (occupiedEl) occupiedEl.classList.add('hidden');
    }

    document.getElementById('modalLot').classList.add('open');
}

function closeModal() {
    document.getElementById('modalLot').classList.remove('open');
}

function triggerInput(id) {
    document.getElementById(id).click();
}

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

// Smart Recommendation JS Helpers
function pilihLotCadangan(lotId) {
    const coord = LOT_COORDS[lotId];
    if (coord) {
        // Pindahkan peta ke lot tersebut dan zoom
        map.setView(coord, 21);
        
        // Dapatkan status lot dari OCCUPIED_GRAVES (jika ada)
        const graveInfo = OCCUPIED_GRAVES[lotId];
        const isOccupied = graveInfo && (graveInfo.status_lot === 'Penuh' || graveInfo.status_lot === 'Ditetapkan');
        const lotStatus = graveInfo ? graveInfo.status_lot : 'Tersedia';
        
        // Buka dialog pengesahan penetapan secara automatik
        setTimeout(() => {
            openLotDialog(lotId, coord, isOccupied, lotStatus);
        }, 150);
    }
}

// Jalankan panggilan ulasan AI apabila halaman sedia
document.addEventListener('DOMContentLoaded', function() {
    if (ACTIVE_BOOKING && RECOMMENDED_LOT_ID) {
        const skippedStr = encodeURIComponent(SKIPPED_LOTS);
        const lastStr = encodeURIComponent(LAST_LOT);
        const url = `susun_lot.php?action=get_recommendation&tempahan_id=${ACTIVE_BOOKING.id}&recommended_lot=${RECOMMENDED_LOT_ID}&skipped=${skippedStr}&last=${lastStr}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const ulasanEl = document.getElementById('aiSyorUlasan');
                if (ulasanEl) {
                    if (data.status === 'success') {
                        ulasanEl.innerHTML = `<span class="italic text-emerald-100">"${data.explanation}"</span>`;
                    } else {
                        ulasanEl.innerHTML = `<span class="text-rose-300">Ulasan AI tidak tersedia. Sila pilih secara manual.</span>`;
                    }
                }
            })
            .catch(err => {
                const ulasanEl = document.getElementById('aiSyorUlasan');
                if (ulasanEl) {
                    ulasanEl.innerHTML = `<span class="text-rose-300">Ralat sambungan AI.</span>`;
                }
            });
            
        // Pindahkan peta ke lot cadangan pintar selepas peta dimuatkan
        const coord = LOT_COORDS[RECOMMENDED_LOT_ID];
        if (coord) {
            setTimeout(() => {
                map.setView(coord, 21);
            }, 600);
        }
    }
});

// Initialize
drawAllLots();
drawLandmarks();
updateSideCounts();

</script>
</body>
</html>
