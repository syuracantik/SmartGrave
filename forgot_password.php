<?php
session_start();
require_once 'db.php';

$error = "";
$success = "";
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['step1'])) {
        $identity = trim($_POST['identity']);
        if (empty($identity)) {
            $error = "Sila masukkan ID Pengguna atau Emel anda.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$identity, $identity]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $otp = strval(rand(100000, 999999));
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_username'] = $user['username'];
                    $_SESSION['reset_email'] = $user['email'];
                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['reset_otp_expiry'] = time() + 300; // 5 min
                    $_SESSION['reset_step'] = 2;
                    $step = 2;
                } else {
                    $error = "ID Pengguna atau Emel tidak ditemui.";
                }
            } catch (PDOException $e) {
                $error = "Ralat pangkalan data: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['step2'])) {
        $otp_input = trim($_POST['otp']);
        if (empty($otp_input)) {
            $error = "Sila masukkan kod keselamatan 6-digit.";
        } elseif ($otp_input !== ($_SESSION['reset_otp'] ?? '')) {
            $error = "Kod keselamatan salah.";
        } elseif (time() > ($_SESSION['reset_otp_expiry'] ?? 0)) {
            $error = "Kod keselamatan telah tamat tempoh. Sila minta kod baru.";
            $_SESSION['reset_step'] = 1;
            $step = 1;
        } else {
            $_SESSION['reset_step'] = 3;
            $step = 3;
        }
    } elseif (isset($_POST['step3'])) {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if ($password !== $password_confirm) {
            $error = "Sahkan kata laluan tidak sepadan.";
        } elseif (strlen($password) < 8) {
            $error = "Kata laluan mestilah sekurang-kurangnya 8 huruf/aksara.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Kata laluan mestilah mengandungi sekurang-kurangnya satu (1) huruf besar.";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);

                // Clear session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_username']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_otp_expiry']);
                unset($_SESSION['reset_step']);

                header("Location: login.php?reset=success");
                exit();
            } catch (PDOException $e) {
                $error = "Ralat menukar kata laluan: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['cancel'])) {
        // Reset recovery flow
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_username']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_otp']);
        unset($_SESSION['reset_otp_expiry']);
        unset($_SESSION['reset_step']);
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Laluan | SmartGrave Bangi Lama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-islamic {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png"), 
                              linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-islamic min-h-screen flex items-center justify-center p-6">

    <div class="glass-card w-full max-w-md rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] overflow-hidden border border-white/20">
        
        <div class="p-10 text-center relative bg-white">
            <a href="login.php" class="absolute left-8 top-10 text-gray-400 hover:text-emerald-700 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <div class="w-16 h-16 bg-emerald-800 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                <i class="fas fa-key text-2xl"></i>
            </div>
            
            <h2 class="text-2xl font-extrabold text-emerald-950 tracking-tight leading-tight">
                Pulihkan <span class="text-emerald-600 font-medium">Kata Laluan</span>
            </h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Urusan Sekuriti SmartGrave</p>
        </div>

        <div class="px-10 pb-10">
            <!-- Simulated Message for OTP -->
            <?php if ($step === 2 && isset($_SESSION['reset_otp'])): ?>
                <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-xl mb-6 shadow-sm">
                    <p class="text-[11px] text-amber-800 font-bold leading-normal">
                        <i class="fas fa-mobile-alt mr-1"></i> [SIMULASI PORTAL - PERCUMA]
                    </p>
                    <p class="text-[11px] text-amber-700 mt-1 leading-normal">
                        Kod keselamatan telah dihantar ke Emel / WhatsApp anda. Masukkan kod ini untuk pengesahan:
                    </p>
                    <p class="text-lg font-black text-amber-900 mt-2 tracking-widest text-center bg-white py-2 rounded-lg border border-amber-100">
                        <?php echo htmlspecialchars($_SESSION['reset_otp']); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-xl mb-6">
                    <p class="text-red-800 text-xs font-semibold flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- STEP 1: REQUEST OTP -->
            <?php if ($step === 1): ?>
                <form method="POST" action="forgot_password.php" class="space-y-6">
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">ID Pengguna / Alamat Emel</label>
                        <div class="relative group">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                <i class="fas fa-user-circle text-lg"></i>
                            </span>
                            <input type="text" name="identity" placeholder="Masukkan ID Pengguna atau Emel" 
                                class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" required>
                        </div>
                    </div>

                    <button type="submit" name="step1" class="w-full bg-emerald-800 text-white py-4 rounded-xl font-bold text-sm hover:bg-emerald-700 transition shadow-lg flex items-center justify-center space-x-3 tracking-wide">
                        <span>HANTAR KOD KESELAMATAN</span>
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </button>
                </form>

            <!-- STEP 2: VERIFY OTP -->
            <?php elseif ($step === 2): ?>
                <form method="POST" action="forgot_password.php" class="space-y-6">
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Masukkan Kod Keselamatan</label>
                        <div class="relative group">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                <i class="fas fa-shield-alt text-lg"></i>
                            </span>
                            <input type="text" name="otp" placeholder="6-digit kod keselamatan" maxlength="6"
                                class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-semibold tracking-[0.3em] text-center" required>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" name="cancel" class="flex-1 bg-gray-100 text-gray-700 py-4 rounded-xl font-bold text-sm hover:bg-gray-200 transition">
                            BATAL
                        </button>
                        <button type="submit" name="step2" class="flex-[2] bg-emerald-800 text-white py-4 rounded-xl font-bold text-sm hover:bg-emerald-700 transition shadow-lg flex items-center justify-center space-x-3">
                            <span>SAHKAN KOD</span>
                            <i class="fas fa-check text-xs"></i>
                        </button>
                    </div>
                </form>

            <!-- STEP 3: RESET PASSWORD -->
            <?php elseif ($step === 3): ?>
                <form method="POST" action="forgot_password.php" class="space-y-6">
                    <div class="space-y-4">
                        <!-- Password -->
                        <div>
                            <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Kata Laluan Baru</label>
                            <div class="relative">
                                <input type="password" id="password" name="password" class="w-full pl-5 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="••••••••" required>
                                <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-emerald-700 transition-colors focus:outline-none">
                                    <i class="fas fa-eye" id="eyeIcon1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Sahkan Kata Laluan Baru</label>
                            <div class="relative">
                                <input type="password" id="password_confirm" name="password_confirm" class="w-full pl-5 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="••••••••" required>
                                <button type="button" onclick="togglePassword('password_confirm', 'eyeIcon2')" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-emerald-700 transition-colors focus:outline-none">
                                    <i class="fas fa-eye" id="eyeIcon2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="step3" class="w-full bg-emerald-800 text-white py-4 rounded-xl font-bold text-sm hover:bg-emerald-700 transition shadow-lg flex items-center justify-center space-x-3 tracking-wide">
                        <span>KEMASKINI KATA LALUAN</span>
                        <i class="fas fa-save text-xs"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(inputId, eyeIconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(eyeIconId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        }
    </script>
</body>
</html>
