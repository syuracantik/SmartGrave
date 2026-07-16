<?php
// C:\xampp\htdocs\smartgrave\scratch_test_gemini.php
require_once 'db.php';

$prompt = "Anda adalah penganalisis pengurusan pusara AI pintar (PusaraBot) untuk sistem SmartGrave Bangi Lama. Berikan 3 cadangan strategi terbaik, padat dan praktikal berdasarkan data perkuburan berikut:
- Jumlah Ahli Khairat Aktif: 120
- Ahli Khairat Tertunggak: 15
- Jumlah Pusara Terisi: 85
- Lot Tersedia: 355
- Purata Pengebumian Sebulan: 2 lot/bulan
- Jangkaan Tempoh Penuh Kubur: 14.8 tahun
- Anggaran Kematian Setahun: 5 kes

Formatkan jawapan anda dalam bentuk HTML (HANYA senarai teratur dengan tag <ol class='list-decimal pl-5 space-y-3'> dan <li>, serta teks tebal menggunakan <strong> untuk setiap cadangan). JANGAN sertakan tag ```html atau markdown luar. Tulis dalam Bahasa Melayu yang sopan, profesional, dan padat.";

$payload = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [["text" => $prompt]]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: $httpCode\n\n";
echo "RAW RESPONSE:\n";
echo $response;
echo "\n";
?>
