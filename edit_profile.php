<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error   = "";

// Tab tracking
$active_tab = $_GET['tab'] ?? 'profile';

// ============================================================
// PROCESS PROFILE UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_profile'])) {
    $full_name  = strtoupper(trim($_POST['full_name']));
    $ic_number  = preg_replace('/[^0-9]/', '', trim($_POST['ic_number']));
    $no_telefon = trim($_POST['no_telefon']);
    $gender     = trim($_POST['gender']);
    $email      = trim($_POST['email']);
    $username   = trim($_POST['username']);
    $alamat     = trim($_POST['alamat']);

    try {
        if (empty($full_name) || empty($ic_number) || empty($no_telefon) || empty($gender) || empty($email) || empty($username) || empty($alamat)) {
            throw new InvalidArgumentException("Sila lengkapkan semua maklumat yang diperlukan.");
        }
        if (strlen($ic_number) !== 12) {
            throw new InvalidArgumentException("No. IC mestilah 12 digit.");
        }

        // Check duplicate email or username or IC for other users
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) FROM users 
            WHERE (username = ? OR email = ? OR ic_number = ?) AND id != ?
        ");
        $stmt_check->execute([$username, $email, $ic_number, $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new InvalidArgumentException("Username, E-mel atau No. IC ini sudah digunakan oleh pengguna lain.");
        }

        // Update database
        $stmt_update = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, ic_number = ?, no_telefon = ?, gender = ?, email = ?, username = ?, alamat = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$full_name, $ic_number, $no_telefon, $gender, $email, $username, $alamat, $user_id]);
        
        $success = "Profil anda berjaya dikemaskini.";
        $active_tab = 'profile';

    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        $active_tab = 'profile';
    } catch (PDOException $e) {
        $error = "Ralat pangkalan data: " . $e->getMessage();
        $active_tab = 'profile';
    }
}

// ============================================================
// PROCESS PASSWORD UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    try {
        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            throw new InvalidArgumentException("Sila lengkapkan semua medan kata laluan.");
        }
        if ($new_pass !== $confirm_pass) {
            throw new InvalidArgumentException("Kata laluan baru dan pengesahan kata laluan tidak sepadan.");
        }
        if (strlen($new_pass) < 6) {
            throw new InvalidArgumentException("Kata laluan baru mestilah sekurang-kurangnya 6 aksara.");
        }

        // Verify current password
        $stmt_verify = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_verify->execute([$user_id]);
        $user_data = $stmt_verify->fetch();

        if (!$user_data || !password_verify($current_pass, $user_data['password'])) {
            throw new InvalidArgumentException("Kata laluan semasa anda adalah salah.");
        }

        // Update hashed password
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt_pass_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_pass_update->execute([$hashed_pass, $user_id]);

        $success = "Kata laluan anda berjaya ditukar.";
        $active_tab = 'password';

    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        $active_tab = 'password';
    } catch (PDOException $e) {
        $error = "Ralat pangkalan data: " . $e->getMessage();
        $active_tab = 'password';
    }
}

// ============================================================
// LOAD CURRENT USER DETAILS
// ============================================================
$stmt_load = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_load->execute([$user_id]);
$u = $stmt_load->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    die("Pengguna tidak dijumpai.");
}

$title = "Kemaskini Profil";
include 'header.php';
include 'sidebar.php';
?>

<main class="flex-1 p-6 lg:p-12 overflow-y-auto">
    
    <!-- Header Page -->
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950 tracking-tight">Kemaskini Akaun</h1>
            <p class="text-emerald-700 font-medium tracking-tight">Urus maklumat peribadi dan keselamatan kata laluan anda</p>
        </div>
        <a href="waris_dashboard.php" class="px-4 py-2 border border-emerald-200 hover:bg-emerald-50 text-emerald-800 text-xs font-bold rounded-xl transition flex items-center gap-1.5 bg-white shadow-sm">
            <i class="fas fa-chevron-left"></i> Dashboard
        </a>
    </div>

    <!-- Success & Error Alerts -->
    <?php if ($success): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-600 text-lg"></i>
            <span class="text-xs font-bold text-emerald-800"><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fas fa-triangle-exclamation text-rose-600 text-lg"></i>
            <span class="text-xs font-bold text-rose-800"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Unified Form Card Container -->
    <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
        
        <!-- Tab selector -->
        <div class="flex border-b border-slate-100 bg-emerald-50/20 p-2">
            <button onclick="switchTab('profile')" id="tabBtn-profile" class="flex-1 py-4 text-center text-xs font-extrabold rounded-2xl transition duration-150 uppercase tracking-wider flex items-center justify-center gap-2">
                <i class="fas fa-id-card"></i> Maklumat Peribadi
            </button>
            <button onclick="switchTab('password')" id="tabBtn-password" class="flex-1 py-4 text-center text-xs font-extrabold rounded-2xl transition duration-150 uppercase tracking-wider flex items-center justify-center gap-2">
                <i class="fas fa-shield-halved"></i> Keselamatan Kata Laluan
            </button>
        </div>

        <!-- Tab contents -->
        <div class="p-8 md:p-10">
            
            <!-- TAB 1: PROFILE INFO -->
            <div id="tabContent-profile" class="hidden">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Nama Penuh -->
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Nama Penuh (Seperti dalam IC)</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($u['full_name'] ?? ''); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800 uppercase" required>
                        </div>

                        <!-- No IC -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">No. Kad Pengenalan</label>
                            <input type="text" name="ic_number" value="<?php echo htmlspecialchars($u['ic_number'] ?? ''); ?>" maxlength="12" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800 font-mono" required>
                        </div>

                        <!-- Jantina -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Jantina</label>
                            <div class="flex gap-3">
                                <label class="flex-1 flex items-center justify-center px-4 py-4 bg-slate-50 border border-slate-100 rounded-2xl cursor-pointer hover:bg-emerald-50/50 transition">
                                    <input type="radio" name="gender" value="Lelaki" class="w-3.5 h-3.5 text-emerald-600 border-slate-300 focus:ring-emerald-500" <?php echo (strcasecmp($u['gender'] ?? '', 'Lelaki') === 0) ? 'checked' : ''; ?> required>
                                    <span class="ml-2 text-xs font-bold text-slate-700">Lelaki</span>
                                </label>
                                <label class="flex-1 flex items-center justify-center px-4 py-4 bg-slate-50 border border-slate-100 rounded-2xl cursor-pointer hover:bg-emerald-50/50 transition">
                                    <input type="radio" name="gender" value="Perempuan" class="w-3.5 h-3.5 text-emerald-600 border-slate-300 focus:ring-emerald-500" <?php echo (strcasecmp($u['gender'] ?? '', 'Perempuan') === 0) ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-xs font-bold text-slate-700">Perempuan</span>
                                </label>
                            </div>
                        </div>

                        <!-- No Telefon -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">No. Telefon</label>
                            <input type="text" name="no_telefon" value="<?php echo htmlspecialchars($u['no_telefon'] ?? ''); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" required>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">E-mel</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" required>
                        </div>

                        <!-- Username -->
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Nama Pengguna (Username)</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($u['username'] ?? ''); ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" required>
                        </div>

                        <!-- Alamat -->
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Alamat Penuh</label>
                            <textarea name="alamat" rows="4" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800 leading-relaxed" required><?php echo htmlspecialchars($u['alamat'] ?? ''); ?></textarea>
                        </div>

                    </div>

                    <div class="pt-6 border-t border-slate-50 flex justify-end">
                        <button type="submit" name="submit_profile" class="px-8 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:brightness-105 text-white rounded-2xl font-bold transition shadow-lg shadow-emerald-700/10 text-xs uppercase tracking-wider">
                            <i class="fas fa-save mr-2"></i> Simpan Profil
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: PASSWORD CONFIG -->
            <div id="tabContent-password" class="hidden">
                <form method="POST" class="space-y-6 max-w-lg mx-auto">
                    
                    <!-- Current Password -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Kata Laluan Semasa</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"><i class="fas fa-key"></i></span>
                            <input type="password" name="current_password" class="w-full pl-12 pr-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" placeholder="Masukkan kata laluan sekarang" required>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Kata Laluan Baru</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"><i class="fas fa-lock"></i></span>
                            <input type="password" name="new_password" class="w-full pl-12 pr-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" placeholder="Minimum 6 aksara" required>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Sahkan Kata Laluan Baru</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"><i class="fas fa-shield"></i></span>
                            <input type="password" name="confirm_password" class="w-full pl-12 pr-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-xs font-bold text-slate-800" placeholder="Ulangi kata laluan baru" required>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-50 flex justify-end">
                        <button type="submit" name="submit_password" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:brightness-105 text-white rounded-2xl font-bold transition shadow-lg shadow-emerald-700/10 text-xs uppercase tracking-wider">
                            <i class="fas fa-shield-halved mr-2"></i> Tukar Kata Laluan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</main>

<script>
    // Tab switching engine
    function switchTab(tabId) {
        // Hide all tabs
        document.getElementById('tabContent-profile').classList.add('hidden');
        document.getElementById('tabContent-password').classList.add('hidden');
        
        // Deactivate all buttons
        document.getElementById('tabBtn-profile').className = "flex-1 py-4 text-center text-xs font-extrabold rounded-2xl transition duration-150 uppercase tracking-wider flex items-center justify-center gap-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50";
        document.getElementById('tabBtn-password').className = "flex-1 py-4 text-center text-xs font-extrabold rounded-2xl transition duration-150 uppercase tracking-wider flex items-center justify-center gap-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50";
        
        // Show current tab
        document.getElementById('tabContent-' + tabId).classList.remove('hidden');
        
        // Activate current button
        document.getElementById('tabBtn-' + tabId).className = "flex-1 py-4 text-center text-xs font-extrabold rounded-2xl transition duration-150 uppercase tracking-wider flex items-center justify-center gap-2 bg-emerald-800 text-white shadow-md shadow-emerald-800/10";
    }

    // Default load tab
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || '<?php echo $active_tab; ?>';
        switchTab(tab);
    });
</script>

</body>
</html>
