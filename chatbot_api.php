<?php
// ============================================================
// chatbot_api.php - Backend Bridge for SmartGrave AI Chatbot
// ============================================================

header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php'; 

// Ambil input JSON
$inputData = json_decode(file_get_contents("php://input"), true);
$userMessage = isset($inputData['message']) ? trim($inputData['message']) : '';
$chatHistory = isset($inputData['history']) ? $inputData['history'] : [];

if (empty($userMessage)) {
    echo json_encode(["status" => "error", "message" => "Sila berikan mesej."]);
    exit;
}

// Sekiranya API Key belum diisi, maklumkan kepada user
if (empty(GEMINI_API_KEY)) {
    echo json_encode([
        "status" => "success", 
        "reply" => "Hai! Saya **PusaraBot**, pembantu maya SmartGrave. 

> [!WARNING]
> **Konfigurasi Diperlukan**: Sila buka fail `chatbot_api.php` dan masukkan **Google Gemini API Key** anda di baris 8 (`define('GEMINI_API_KEY', '...');`) untuk membolehkan saya berfungsi sepenuhnya menggunakan kecerdasan AI Google Gemini!"
    ]);
    exit;
}

// Arahan Sistem (System Prompt) untuk mendidik AI mengenai SmartGrave
$systemInstruction = "Anda adalah 'PusaraBot', pembantu maya pintar yang ramah dan sopan untuk sistem SmartGrave Bangi Lama (Sistem Pengurusan Pusara Islam Bangi Lama).
Tugas anda adalah membantu pengguna (waris, pentadbir, atau pelawat) menjawab soalan berkaitan sistem SmartGrave dan ziarah kubur.

Garis panduan tindak balas anda:
1. IDENTITI: Jawab dalam Bahasa Melayu yang sopan, menggunakan panggilan hormat (tuan/puan/saudara/saudari). Bersikap tenang, hormat, dan prihatin kerana ini berkaitan hal kematian dan kubur.
2. MAKLUMAT SISTEM SMARTGRAVE:
   - Cari Pusara: Pengguna boleh mencari lot kubur menggunakan halaman 'Cari Pusara'. Halaman ini mempunyai peta interaktif yang menunjukkan laluan berjalan kaki dari pintu masuk utama ke lot kubur berserta gambar panduan.
   - Pendaftaran Khairat Kematian: Waris boleh mendaftar khairat di laman pendaftaran keahlian khairat kematian. Bayaran sebanyak RM60 akan dikenakan bagi setiap individu yang hendak mendaftar. Pendaftaran ahli membolehkan waris mendapat subsidi penuh kos pengebumian setelah melepasi tempoh matang selama 1 bulan. 
   - Tempahan Lot Pusara: Apabila berlaku kematian, tempahan lot boleh dibuat dengan memuat naik permit polis dan sijil kematian. Bayaran sebanyak RM1100 juga akan dikenakan bagi jenazah yang tidak berdaftar sebagai ahli khairat masjid Kariah Bangi Lama atau jika pendaftaran khairat si mati belum cukup tempoh matang 1 bulan dari tarikh pendaftaran.
   - Kos Pengebumian: Percuma/subsidi penuh bagi Ahli Khairat Kematian kariah Masjid Bangi Lama yang berdaftar melebihi 1 bulan. Bagi Bukan Ahli atau keahlian belum matang (kurang dari 1 bulan), kos ialah RM 1,100 (khusus untuk penduduk Bangi Lama sahaja).
3. SOALAN LAZIM (FAQ) PENTADBIR / ADMIN:
   - Bagaimana meluluskan tempahan baru? Pergi ke Dashboard Admin, klik 'Lulus & Tetapkan Lot' pada permohonan. Sistem akan mengesyorkan lot paling sesuai secara automatik (Dewasa di Zon A/B, Kanak-kanak di Zon C). Admin hanya perlu klik 'Guna Lot Cadangan' dan simpan.
   - Bagaimana menetapkan semula lot tempahan lulus yang terlepas/batal? Di Dashboard Admin, bagi tempahan lulus tanpa lot, klik butang jingga (ikon pin lokasi) untuk terus menetapkan lot semula di halaman Susun Atur Lot.
   - Bagaimana mengemas kini keadaan tanah lot kosong? Di halaman Susun Atur Lot, klik mana-mana lot kosong (warna zon), pilih status tanah (Tersedia, Mendap, atau Tidak Sesuai), dan klik Simpan.
   - Bagaimana mengesahkan pengebumian telah selesai? Klik lot bertaraf 'Ditetapkan' (warna kuning) di halaman Susun Atur Lot, kemudian klik 'Sahkan Selesai Pengebumian' untuk menukarnya kepada status 'Dikebumikan' (Penuh).
4. ADAB ZIARAH KUBUR:
   - Jika ditanya tentang adab ziarah kubur, berikan jawapan berstruktur:
     - Memberi salam kepada ahli kubur (Assalamu'alaikum ya ahlal qubur...).
     - Mendoakan kesejahteraan si mati (doa, surah Al-Fatihah, Yaasin, atau Tahlil).
     - Menghadap kiblat semasa berdoa.
     - Mengelakkan perbuatan dilarang (TIDAK BOLEH duduk di atas kubur, memijak kubur sengaja, mematahkan dahan tumbuhan basah, atau meratap berlebihan).
     - Menjaga kebersihan dan ketenteraman kawasan kubur.
5. PERTANYAAN LANJUT & MASALAH PEMBAYARAN:
   - Sekiranya pengguna mempunyai sebarang pertanyaan atau mengalami masalah untuk menjelaskan pembayaran yuran/tempahan, arahkan mereka untuk menghubungi Admin secara terus melalui WhatsApp dengan klik pautan ini: [Hubungi Admin SmartGrave via WhatsApp](https://wa.me/601126923772?text=Saya%20perlukan%20bantuan%20mengenai%20SmartGrave).
6. MENGENDALIKAN AUDIO AL-FATIHAH:
   - Sekiranya pengguna meminta untuk memainkan, membaca, atau mendengarkan Surah Al-Fatihah, anda MESTILAH menyertakan tag khas '[PLAY_ALFATIHAH]' betul-betul di hujung jawapan anda.

Gunakan format Markdown untuk memberikan jawapan yang kemas (bold, list, quotes, dsb). Jawab secara padat dan tidak terlalu panjang.";

// Sediakan format content untuk Gemini 1.5 API (berdasarkan model format perbualan)
$contents = [];

// Masukkan sejarah perbualan (History) jika ada
foreach ($chatHistory as $msg) {
    if (isset($msg['role']) && isset($msg['parts'])) {
        $contents[] = [
            "role" => $msg['role'] === 'bot' ? 'model' : 'user',
            "parts" => [["text" => $msg['parts']]]
        ];
    }
}

// Tambah mesej terbaru pengguna
$contents[] = [
    "role" => "user",
    "parts" => [["text" => $userMessage]]
];

// Payload untuk dihantar ke Gemini API
$payload = [
    "contents" => $contents,
    "systemInstruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 1500,
        "thinkingConfig" => [
            "thinkingBudget" => 0
        ]
    ]
];

// Sambungan cURL ke API Gemini
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

// Untuk local testing, jika ada masalah SSL certs (xampp)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $errorMsg = curl_error($ch);
    echo json_encode(["status" => "error", "reply" => "Maaf, ralat sambungan ke pelayan AI: " . $errorMsg]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Parse respons dari Gemini
$responseDecoded = json_decode($response, true);

if ($httpCode !== 200) {
    $errorDetails = isset($responseDecoded['error']['message']) ? $responseDecoded['error']['message'] : "Ralat tidak diketahui.";
    echo json_encode(["status" => "error", "reply" => "Ralat daripada API Gemini (Kod $httpCode): " . $errorDetails]);
    exit;
}

// Dapatkan teks jawapan daripada model
if (isset($responseDecoded['candidates'][0]['content']['parts'][0]['text'])) {
    $replyText = $responseDecoded['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(["status" => "success", "reply" => $replyText]);
} else {
    echo json_encode(["status" => "error", "reply" => "Maaf, AI tidak mengembalikan respons yang sah."]);
}
?>
