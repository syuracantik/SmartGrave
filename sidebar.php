<aside class="w-72 sidebar-premium text-white hidden lg:flex flex-col shadow-2xl sticky top-0 h-screen">
    <div class="p-8">
        <div class="flex items-center space-x-3 mb-12">
            <div class="bg-emerald-800 p-2 rounded-xl text-yellow-400 border border-emerald-700">
                <i class="fas fa-mosque"></i>
            </div>
            <span class="text-2xl font-bold tracking-tight">Smart<span class="text-emerald-400">Grave</span></span>
        </div>
        
        <nav class="space-y-3">
            <a href="waris_dashboard.php" class="flex items-center space-x-4 p-4 bg-emerald-700/40 rounded-2xl text-white font-bold border border-emerald-600/50">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
            <a href="booking.php" class="flex items-center space-x-4 p-4 hover:bg-emerald-800/50 rounded-2xl transition text-emerald-100/70">
                <i class="fas fa-file-contract"></i> <span>Tempah Lot</span>
            </a>
            <a href="daftar_khairat.php" class="flex items-center space-x-4 p-4 hover:bg-emerald-800/50 rounded-2xl transition text-emerald-100/70">
                <i class="fas fa-user-plus"></i> <span>Daftar Ahli Khairat Kematian</span>
            </a>
            <hr class="border-emerald-800/50 my-6">
            <a href="index.php" class="flex items-center space-x-4 p-4 text-rose-300 hover:bg-rose-500/10 rounded-2xl transition font-semibold">
                <i class="fas fa-sign-out-alt"></i> <span>Log Keluar</span>
            </a>
        </nav>
    </div>
    
    <div class="mt-auto p-8">
        <?php
        $remaining_lots = 440;
        $percent_occupied = 0;
        try {
            include_once 'db.php';
            if (isset($pdo)) {
                $stmt_count_full = $pdo->query("SELECT COUNT(*) FROM lot_pusara WHERE status_lot = 'Penuh'");
                if ($stmt_count_full) {
                    $occupied_count = (int)$stmt_count_full->fetchColumn();
                    $remaining_lots = max(0, 440 - $occupied_count);
                    $percent_occupied = round(($occupied_count / 440) * 100);
                }
            }
        } catch (Exception $e) {
            $remaining_lots = 142; // fallback
            $percent_occupied = 35;
        }
        ?>
        <div class="bg-emerald-900/60 p-5 rounded-3xl border border-emerald-700/50 shadow-inner">
            <p class="text-[10px] font-bold text-emerald-400 uppercase mb-2">Baki Kapasiti Lot</p>
            <h4 class="text-2xl font-black mb-1"><?php echo $remaining_lots; ?> <span class="text-sm font-normal text-emerald-500">Lot</span></h4>
            <div class="w-full bg-emerald-950 h-1.5 rounded-full">
                <div class="bg-yellow-500 h-full rounded-full shadow-[0_0_8px_#eab308]" style="width: <?php echo $percent_occupied; ?>%"></div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Navigation Bar for Waris (Hanya kelihatan di telefon & tablet) -->
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="lg:hidden fixed bottom-0 left-0 right-0 bg-emerald-950 text-white z-[2000] border-t border-emerald-800 flex justify-around items-center py-3 px-2 shadow-2xl">
    <a href="waris_dashboard.php" class="flex flex-col items-center space-y-1 <?php echo ($current_page == 'waris_dashboard.php') ? 'text-yellow-400 font-bold' : 'text-emerald-300/70 hover:text-yellow-400'; ?> transition-colors">
        <i class="fas fa-th-large text-lg"></i>
        <span class="text-[9px] uppercase tracking-wider font-semibold">Dashboard</span>
    </a>
    <a href="booking.php" class="flex flex-col items-center space-y-1 <?php echo ($current_page == 'booking.php') ? 'text-yellow-400 font-bold' : 'text-emerald-300/70 hover:text-yellow-400'; ?> transition-colors">
        <i class="fas fa-file-contract text-lg"></i>
        <span class="text-[9px] uppercase tracking-wider font-semibold">Tempah Lot</span>
    </a>
    <a href="daftar_khairat.php" class="flex flex-col items-center space-y-1 <?php echo ($current_page == 'daftar_khairat.php') ? 'text-yellow-400 font-bold' : 'text-emerald-300/70 hover:text-yellow-400'; ?> transition-colors">
        <i class="fas fa-user-plus text-lg"></i>
        <span class="text-[9px] uppercase tracking-wider font-semibold">Khairat</span>
    </a>
    <a href="index.php" class="flex flex-col items-center space-y-1 text-rose-300 hover:text-rose-400 transition-colors">
        <i class="fas fa-sign-out-alt text-lg"></i>
        <span class="text-[9px] uppercase tracking-wider font-semibold">Keluar</span>
    </a>
</div>