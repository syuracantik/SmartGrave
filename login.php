<?php
session_start();
require_once 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usernameInput = $_POST['username'];
    $passwordInput = $_POST['password'];

    try {
        // Query ini membenarkan log masuk guna username ATAU email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameInput, $usernameInput]);
        $user = $stmt->fetch();

        if ($user) {
            // Semak password
            if (password_verify($passwordInput, $user['password']) || $passwordInput === $user['password']) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['full_name'];

                if (strtolower($user['role']) == 'pentadbir' || strtolower($user['role']) == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: waris_dashboard.php");
                }
                exit();
            } else {
                $error = "Kata laluan salah!";
            }
        } else {
            $error = "ID Pengguna atau Emel tidak dijumpai!";
        }
    } catch (PDOException $e) {
        $error = "Ralat pangkalan data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk | SmartGrave Bangi Lama</title>
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
            <a href="index.html" class="absolute left-8 top-10 text-gray-400 hover:text-emerald-700 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <div class="w-20 h-20 bg-emerald-800 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-xl transform hover:rotate-6 transition-transform duration-300">
                <i class="fas fa-mosque text-3xl"></i>
            </div>
            
            <h2 class="text-2xl font-extrabold text-emerald-950 tracking-tight leading-tight">
                SmartGrave <span class="text-emerald-600 font-medium">Bangi Lama</span>
            </h2>
            <div class="flex items-center justify-center mt-2 space-x-2">
                <span class="h-[1px] w-8 bg-gray-200"></span>
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-[0.2em]">Portal Pengurusan Rasmi</p>
                <span class="h-[1px] w-8 bg-gray-200"></span>
            </div>
        </div>
        
        <form id="loginForm" method="POST" action="login.php" class="px-10 pb-10 space-y-6">
            <div class="space-y-4">
                <?php if (!empty($error)): ?>
                    <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <p class="text-red-800 text-[12px] font-medium flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">ID Pengguna / Emel</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-emerald-600 transition-colors">
                            <i class="fas fa-user-circle text-lg"></i>
                        </span>
                        <input type="text" name="username" id="username" placeholder="Cth: waris@email.com" 
                            class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all text-sm font-medium" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Kata Laluan</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-emerald-600 transition-colors">
                            <i class="fas fa-lock text-lg"></i>
                        </span>
                        <input type="password" name="password" id="password" placeholder="••••••••" 
                            class="w-full pl-12 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all text-sm font-medium" required>
                        
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-emerald-700 transition-colors">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" id="loginBtn" class="w-full bg-emerald-800 text-white py-4 rounded-xl font-bold text-sm hover:bg-emerald-700 active:scale-[0.98] transition-all shadow-lg shadow-emerald-900/20 flex items-center justify-center space-x-3 tracking-wide">
                <span>LOG MASUK</span>
                <i class="fas fa-sign-in-alt text-xs opacity-70"></i>
            </button>

            <div class="text-center pt-4 border-t border-gray-100">
                <p class="text-gray-500 text-xs">Pendaftaran baharu? 
                    <a href="signup.php" class="text-emerald-700 font-bold hover:underline ml-1">Daftar Sekarang</a>
                </p>
            </div>
        </form>

        <div class="py-4 bg-gray-50/80 text-center border-t border-gray-100">
            <p class="text-[10px] text-gray-400 font-medium tracking-wide">
                &copy; 2026 SmartGrave Bangi Lama. Hak Cipta Terpelihara.
            </p>
        </div>
    </div>

    <script>
        // Loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch animate-spin"></i> <span>Mengesahkan...</span>';
            btn.classList.replace('bg-emerald-800', 'bg-emerald-600');
        });

        // Toggle Password
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>