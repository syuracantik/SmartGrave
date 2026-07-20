<?php
// ============================================================
// laporan_api.php - Backend Bridge for AI Report Recommendations
// ============================================================

header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php';

// Check if logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Akses tidak dibenarkan."]);
    exit();
}

if (empty(GEMINI_API_KEY)) {
    echo json_encode([
        "status" => "error",
        "message" => "API Key Gemini tidak dijumpai. Sila tetapkan di db.php"
    ]);
    exit();
}

try {
    // 1. Fetch statistics
    $khairat_aktif = $pdo->query("SELECT count(*) FROM daftar_khairat WHERE status_yuran = 'Dibayar'")->fetchColumn() ?: 0;
    $tunggakan_khairat = $pdo->query("SELECT count(*) FROM daftar_khairat WHERE status_yuran = 'Tunggakan'")->fetchColumn() ?: 0;

    $lot_stats = $pdo->query("SELECT status_lot, count(*) as jumlah FROM lot_pusara GROUP BY status_lot")->fetchAll(PDO::FETCH_KEY_PAIR);
    $lot_penuh = $lot_stats['Penuh'] ?? 0;
    $lot_tersedia = max(0, 830 - $lot_penuh);

    // Demographics for predicted deaths
    $stmt_ic = $pdo->query("SELECT no_ic FROM daftar_khairat WHERE status_yuran = 'Dibayar'");
    $seniors = 0; // 60+
    $adults = 0;  // 18-59
    $youth = 0;   // <18
    $total_members_with_ic = 0;

    if ($stmt_ic) {
        while ($row_ic = $stmt_ic->fetch(PDO::FETCH_ASSOC)) {
            $ic = preg_replace('/[^0-9]/', '', $row_ic['no_ic']);
            if (strlen($ic) === 12) {
                $year_part = intval(substr($ic, 0, 2));
                $current_year = intval(date('Y'));
                $century = ($year_part + 2000 > $current_year) ? 1900 : 2000;
                $birth_year = $century + $year_part;
                $age = $current_year - $birth_year;

                if ($age >= 60) {
                    $seniors++;
                } elseif ($age >= 18) {
                    $adults++;
                } else {
                    $youth++;
                }
                $total_members_with_ic++;
            }
        }
    }

    if ($total_members_with_ic === 0) {
        $seniors = 45;
        $adults = 85;
        $youth = 12;
    }

    $predicted_deaths = ($seniors * 0.038) + ($adults * 0.004) + ($youth * 0.001);
    $predicted_deaths = round($predicted_deaths, 1);

    // Monthly rates
    $stmt_burials_12m = $pdo->query("SELECT COUNT(*) FROM maklumat_jenazah WHERE tarikh_wafat >= NOW() - INTERVAL '12 months'");
    $burials_12m = $stmt_burials_12m ? (int)$stmt_burials_12m->fetchColumn() : 0;
    $monthly_burial_rate = max(0.5, round($burials_12m / 12, 2));
    
    $remaining_months = $monthly_burial_rate > 0 ? ($lot_tersedia / $monthly_burial_rate) : 999;
    $remaining_years = round($remaining_months / 12, 1);

    // 2. Prepare Prompt
    $prompt = "Anda adalah penganalisis pengurusan pusara AI pintar (PusaraBot) untuk sistem SmartGrave Bangi Lama. Berikan 3 cadangan strategi terbaik, padat dan praktikal berdasarkan data perkuburan berikut:
- Jumlah Ahli Khairat Aktif: {$khairat_aktif}
- Ahli Khairat Tertunggak: {$tunggakan_khairat}
- Jumlah Pusara Terisi: {$lot_penuh}
- Lot Tersedia: {$lot_tersedia}
- Purata Pengebumian Sebulan: {$monthly_burial_rate} lot/bulan
- Jangkaan Tempoh Penuh Kubur: {$remaining_years} tahun
- Anggaran Kematian Setahun: {$predicted_deaths} kes

Formatkan jawapan anda dalam bentuk HTML (HANYA senarai teratur dengan tag <ol class='list-decimal pl-5 space-y-3'> dan <li>, serta teks tebal menggunakan <strong> untuk setiap cadangan). JANGAN sertakan tag ```html atau markdown luar. Berikan cadangan yang membina (contohnya: jika jangkaan kubur penuh adalah kurang dari 3-5 tahun, cadangkan fasa tanah baru segera; jika tunggakan yuran tinggi, cadangkan kempen kesedaran; jika ahli muda kurang, cadangkan kempen khairat keluarga). Tulis dalam Bahasa Melayu yang sopan, profesional, dan padat.";

    // 3. Call Gemini API
    $payload = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [["text" => $prompt]]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 2000,
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

    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
        echo json_encode(["status" => "error", "message" => "Ralat sambungan AI: " . $errorMsg]);
        curl_close($ch);
        exit();
    }
    curl_close($ch);

    // Log the raw response for debugging
    file_put_contents('gemini_raw_log.txt', $response);

    $responseDecoded = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorDetails = isset($responseDecoded['error']['message']) ? $responseDecoded['error']['message'] : "Ralat API Gemini.";
        echo json_encode(["status" => "error", "message" => "Ralat API (Kod $httpCode): " . $errorDetails]);
        exit();
    }

    if (isset($responseDecoded['candidates'][0]['content']['parts'][0]['text'])) {
        $recText = $responseDecoded['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean markdown wrap if AI returned it despite instructions
        $recText = trim($recText);
        if (strpos($recText, "```html") === 0) {
            $recText = substr($recText, 7);
        }
        if (substr($recText, -3) === "```") {
            $recText = substr($recText, 0, -3);
        }
        $recText = trim($recText);

        echo json_encode(["status" => "success", "recommendations" => $recText]);
    } else {
        echo json_encode(["status" => "error", "message" => "Ralat mendapatkan cadangan daripada model AI."]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Ralat sistem: " . $e->getMessage()]);
}
?>
