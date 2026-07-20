<?php
require_once 'db.php';

$output = "DEBUG DATA FOR KHAIRAT\n";
$output .= "======================\n";

try {
    // 1. Total in daftar_khairat
    $total = $pdo->query("SELECT count(*) FROM daftar_khairat")->fetchColumn();
    $output .= "Total daftar_khairat records: $total\n";

    // 2. Breakdown of status_yuran
    $status_counts = $pdo->query("SELECT status_yuran, count(*) as count FROM daftar_khairat GROUP BY status_yuran")->fetchAll(PDO::FETCH_ASSOC);
    $output .= "Status Yuran Breakdown:\n";
    foreach ($status_counts as $row) {
        $output .= "  {$row['status_yuran']}: {$row['count']}\n";
    }

    // 3. Years in tarikh_daftar
    $year_counts = $pdo->query("SELECT EXTRACT(YEAR FROM tarikh_daftar) as yr, count(*) as count FROM daftar_khairat GROUP BY yr")->fetchAll(PDO::FETCH_ASSOC);
    $output .= "Years in tarikh_daftar:\n";
    foreach ($year_counts as $row) {
        $output .= "  {$row['yr']}: {$row['count']}\n";
    }

    // 4. Deceased check
    $deceased_count = $pdo->query("SELECT count(*) FROM maklumat_jenazah")->fetchColumn();
    $output .= "Total deceased (maklumat_jenazah): $deceased_count\n";

    // 5. Active, current year, living members
    $active_living_current_year = $pdo->query("
        SELECT count(*) FROM daftar_khairat dk
        WHERE dk.status_yuran = 'Dibayar'
          AND EXTRACT(YEAR FROM dk.tarikh_daftar) = EXTRACT(YEAR FROM CURRENT_DATE)
          AND NOT EXISTS (
              SELECT 1 FROM maklumat_jenazah mj 
              WHERE REPLACE(mj.no_ic, '-', '') = REPLACE(dk.no_ic, '-', '')
          )
    ")->fetchColumn();
    $output .= "Active, living, current year members: $active_living_current_year\n";

    // 6. Active, living members (ignoring year)
    $active_living_any_year = $pdo->query("
        SELECT count(*) FROM daftar_khairat dk
        WHERE dk.status_yuran = 'Dibayar'
          AND NOT EXISTS (
              SELECT 1 FROM maklumat_jenazah mj 
              WHERE REPLACE(mj.no_ic, '-', '') = REPLACE(dk.no_ic, '-', '')
          )
    ")->fetchColumn();
    $output .= "Active, living members (any year): $active_living_any_year\n";

    // 7. Check if there are any IC values returned by the active current year query
    $ic_list = $pdo->query("
        SELECT dk.nama_ahli, dk.no_ic, dk.tarikh_daftar FROM daftar_khairat dk
        WHERE dk.status_yuran = 'Dibayar'
          AND EXTRACT(YEAR FROM dk.tarikh_daftar) = EXTRACT(YEAR FROM CURRENT_DATE)
          AND NOT EXISTS (
              SELECT 1 FROM maklumat_jenazah mj 
              WHERE REPLACE(mj.no_ic, '-', '') = REPLACE(dk.no_ic, '-', '')
          )
    ")->fetchAll(PDO::FETCH_ASSOC);
    $output .= "Active, living, current year ICs:\n";
    foreach ($ic_list as $row) {
        $output .= "  {$row['nama_ahli']} | IC: {$row['no_ic']} | Registered: {$row['tarikh_daftar']}\n";
    }

} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents('debug_output.txt', $output);
echo "Done! Check debug_output.txt";
?>
