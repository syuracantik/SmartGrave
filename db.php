<?php
$host = "aws-1-ap-southeast-2.pooler.supabase.com";
$port = "6543";
$dbname = "postgres";
$user = "postgres.nsqypwpahqednmavsifc";
$password = "Syura040228101040";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    
    if ($pdo) {

    }
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>