<?php
require_once 'db.php';

$output = "DATABASE SCHEMA DETAILS\n";
$output .= "=======================\n";

try {
    $q = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = [];
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['table_name'];
    }

    foreach ($tables as $table) {
        $output .= "\n--- TABLE: $table ---\n";
        $q2 = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table' AND table_schema = 'public' ORDER BY ordinal_position");
        while($row2 = $q2->fetch(PDO::FETCH_ASSOC)) {
            $output .= "  {$row2['column_name']} ({$row2['data_type']})\n";
        }
    }
} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents('schema_info.txt', $output);
echo "Database schema info written to schema_info.txt successfully!";
?>
