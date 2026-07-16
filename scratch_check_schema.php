<?php
require_once 'db.php';
try {
    echo "--- TABLES ---\n";
    $q = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = [];
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['table_name'];
        echo "- {$row['table_name']}\n";
    }

    foreach ($tables as $table) {
        echo "\n--- COLUMNS FOR $table ---\n";
        $q2 = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table' AND table_schema = 'public'");
        while($row2 = $q2->fetch(PDO::FETCH_ASSOC)) {
            echo "  {$row2['column_name']} ({$row2['data_type']})\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
