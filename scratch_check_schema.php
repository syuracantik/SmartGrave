<?php
require_once 'db.php';
try {
    echo "--- USERS COLUMNS ---\n";
    $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users'");
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }

    echo "\n--- DAFTAR_KHAIRAT COLUMNS ---\n";
    $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'daftar_khairat'");
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }

    echo "\n--- BAYARAN COLUMNS ---\n";
    $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'bayaran'");
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
