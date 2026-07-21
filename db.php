<?php
// Tetapkan zon masa lalai secara global ke waktu Malaysia (GMT+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Periksa jika fail db_local.php wujud (hanya di local XAMPP anda)
if (file_exists(__DIR__ . '/db_local.php')) {
    include __DIR__ . '/db_local.php';
} else {
    // Jika tiada db_local.php, ambil dari Environment Variables (di server Render/Railway)
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '6543';
    $dbname = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    
    $gemini_api_key = getenv('GEMINI_API_KEY');
    $gemini_model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    if ($pdo) {
        // Tetapkan zon masa pangkalan data PostgreSQL ke Malaysia
        $pdo->exec("SET TIME ZONE 'Asia/Kuala_Lumpur'");

        // Schema migrations have been successfully executed and the database is up to date.
    }
} catch (PDOException $e) {
    echo $e->getMessage();
}

// Gemini AI Config
define('GEMINI_API_KEY', $gemini_api_key);
define('GEMINI_MODEL', $gemini_model);

// No. Telefon Penggali Kubur (Boleh ditukar)
define('NO_TEL_PENGGALI', '601126923772');
?>