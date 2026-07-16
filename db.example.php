<?php
// Ganti dengan maklumat database anda sendiri
$host = "your-supabase-host.pooler.supabase.com";
$port = "6543";
$dbname = "postgres";
$user = "your-database-user";
$password = "YOUR_DATABASE_PASSWORD_HERE";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    
    if ($pdo) {
        // Ensure updated_at column exists in tempahan table for the 24-hour expiration filter
        $pdo->exec("ALTER TABLE tempahan ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP");

        // Ensure infaq table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS infaq (
            id SERIAL PRIMARY KEY,
            nama_penderma VARCHAR(255) DEFAULT 'Hamba Allah',
            email VARCHAR(255) DEFAULT NULL,
            no_telefon VARCHAR(50) DEFAULT NULL,
            jumlah DECIMAL(10,2) NOT NULL,
            kaedah_bayaran VARCHAR(50) NOT NULL,
            tarikh_transaksi TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            no_rujukan VARCHAR(100) NOT NULL
        )");

        // Ensure infaq_id column exists in bayaran table
        $pdo->exec("ALTER TABLE bayaran ADD COLUMN IF NOT EXISTS infaq_id INTEGER REFERENCES infaq(id) ON DELETE SET NULL");
    }
} catch (PDOException $e) {
    echo $e->getMessage();
}

// Gemini AI Config - Ganti dengan API Key anda sendiri
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_MODEL', 'gemini-2.5-flash');
?>
