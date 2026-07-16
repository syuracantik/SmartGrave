<?php
require_once 'db.php';
try {
    $q = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = [];
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['table_name'];
    }
    file_put_contents('tables_list.txt', implode("\n", $tables));
    echo "Done";
} catch (Exception $e) {
    file_put_contents('tables_list.txt', "Error: " . $e->getMessage());
}
?>
