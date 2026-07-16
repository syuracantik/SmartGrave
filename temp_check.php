<?php
require_once 'db.php';

$output = "DATABASE DIAGNOSTIC REPORT\n";
$output .= "==========================\n";

// List all tables
$output .= "\nTables in database:\n";
try {
    $q = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $output .= "  - {$row['table_name']}\n";
    }
} catch (Exception $e) {
    $output .= "Error listing tables: " . $e->getMessage() . "\n";
}

// Columns for key tables
$tables = ['tempahan', 'daftar_khairat', 'lot_pusara', 'maklumat_jenazah', 'ahli_khairat', 'waris'];
foreach ($tables as $t) {
    $output .= "\nColumns for $t:\n";
    try {
        $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$t' ORDER BY ordinal_position");
        if ($q) {
            while($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $output .= "  - {$row['column_name']} ({$row['data_type']})\n";
            }
        } else {
            $output .= "  (Table does not exist or columns query failed)\n";
        }
    } catch (Exception $e) {
        $output .= "  Error: " . $e->getMessage() . "\n";
    }
}

// Distinct status in lot_pusara
$output .= "\nDistinct status in lot_pusara:\n";
try {
    $q = $pdo->query("SELECT status_lot, COUNT(*) as cnt FROM lot_pusara GROUP BY status_lot");
    if ($q) {
        while($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $output .= "  - {$row['status_lot']}: {$row['cnt']}\n";
        }
    } else {
        $output .= "  (Table lot_pusara does not exist or status query failed)\n";
    }
} catch (Exception $e) {
    $output .= "  Error: " . $e->getMessage() . "\n";
}

// Check recent rows from tempahan
$output .= "\nSample rows from tempahan (latest 3):\n";
try {
    $q = $pdo->query("SELECT * FROM tempahan ORDER BY id_tempahan DESC LIMIT 3");
    if ($q) {
        while($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $output .= "  - " . json_encode($row) . "\n";
        }
    }
} catch (Exception $e) {
    $output .= "  Error: " . $e->getMessage() . "\n";
}

// Save output
file_put_contents('schema_output.txt', $output);
echo "Diagnostic complete. Saved to schema_output.txt\n";
?>
