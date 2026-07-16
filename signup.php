<?php
session_start();
require_once 'db.php';

$message = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname         = $_POST['fullname'];
    $ic_number        = preg_replace('/[^0-9]/', '', $_POST['ic_number']);
    $username         = trim($_POST['username']);
    $email            = trim($_POST['email']);
    $phone            = $_POST['phone'];
    $address          = $_POST['address'];
    $gender           = $_POST['gender'];
    $password_raw     = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validations
    if ($password_raw !== $password_confirm) {
        $status = "error";
        $message = "Sahkan kata laluan tidak sepadan dengan kata laluan.";
    } elseif (strlen($password_raw) < 8) {
        $status = "error";
        $message = "Kata laluan mestilah sekurang-kurangnya 8 huruf/aksara.";
    } elseif (!preg_match('/[A-Z]/', $password_raw)) {
        $status = "error";
        $message = "Kata laluan mestilah mengandungi sekurang-kurangnya satu (1) huruf besar.";
    } else {
        try {
            // Check if username already exists
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_check->execute([$username]);
            if ($stmt_check->fetchColumn() > 0) {
                $status = "error";
                $message = "ID Pengguna (Username) ini telah wujud. Sila gunakan ID lain.";
            } else {
                // Check if IC already exists
                $stmt_check_ic = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ic_number = ?");
                $stmt_check_ic->execute([$ic_number]);
                if ($stmt_check_ic->fetchColumn() > 0) {
                    $status = "error";
                    $message = "No. Kad Pengenalan ini telah wujud/berdaftar dalam sistem.";
                } else {
                    $password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);
                    // Insert user
                    $stmt = $pdo->prepare("
                        INSERT INTO users 
                            (username, email, full_name, role, password, gender, ic_number, no_telefon, alamat)
                        VALUES 
                            (?, ?, ?, 'Waris', ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username,
                        $email,
                        $fullname,
                        $password_hashed,
                        $gender,
                        $ic_number,
                        $phone,
                        $address
                    ]);
                    $status = "success";
                }
            }
        } catch (PDOException $e) {
            $status = "error";
            if (strpos($e->getMessage(), '23505') !== false) {
                $message = "Maaf, No. IC, Username atau Emel ini sudah berdaftar.";
            } else {
                $message = "Ralat: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akaun | SmartGrave Bangi Lama</title>
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
        /* Ini rahsia dia supaya card nampak putih pekat dan timbul */
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-islamic min-h-screen flex items-center justify-center p-6">

    <div class="max-w-2xl w-full glass-card rounded-[2.5rem] shadow-2xl overflow-hidden border border-white/20">
        
        <!-- Header Section -->
        <div class="p-10 text-center bg-white border-b border-gray-50 relative">
            <a href="login.php" class="absolute left-8 top-12 text-gray-400 hover:text-emerald-700 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="w-16 h-16 bg-emerald-800 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                <i class="fas fa-user-plus text-2xl"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-emerald-950 tracking-tight">Daftar Akaun <span class="text-emerald-600">Baru</span></h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mt-2">Portal Pengurusan Rasmi</p>
        </div>

        <form action="signup.php" method="POST" class="p-10 space-y-5">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <!-- Full Name -->
    <div class="md:col-span-2">
        <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Nama Penuh (Seperti dalam IC)</label>
        <input type="text" name="fullname" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="Ahmad Bin Kassim" required>
    </div>

    <!-- TAMBAHAN: No. Kad Pengenalan -->
    <div>
        <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">No. Kad Pengenalan</label>
        <input type="text" name="ic_number" maxlength="12" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="Contoh: 900101105566" required>
        <p class="text-[9px] text-gray-400 mt-1 ml-1">*Tanpa tanda sempang (-)</p>
    </div>

    <!-- Jantina -->
<div>
    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Jantina</label>
    <div class="flex space-x-4">
        <label class="flex-1 flex items-center justify-center px-4 py-4 bg-gray-50 border border-gray-100 rounded-xl cursor-pointer hover:bg-emerald-50 transition-all peer-checked:bg-emerald-100">
            <input type="radio" name="gender" value="Lelaki" class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500" required>
            <span class="ml-2 text-sm font-medium text-gray-700">Lelaki</span>
        </label>
        <label class="flex-1 flex items-center justify-center px-4 py-4 bg-gray-50 border border-gray-100 rounded-xl cursor-pointer hover:bg-emerald-50 transition-all">
            <input type="radio" name="gender" value="Perempuan" class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
            <span class="ml-2 text-sm font-medium text-gray-700">Perempuan</span>
        </label>
    </div>
</div>

                <!-- Username -->
                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">ID Pengguna</label>
                    <input type="text" name="username" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="ahmad88" required>
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">No. Telefon</label>
                    <input type="tel" name="phone" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="012-XXXXXXX" required>
                </div>

    

                <div class="md:col-span-2">
    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Alamat Rumah</label>
    <textarea name="address" rows="3" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="No. 123, Jalan Bangi Lama..." required></textarea>
</div>

                <!-- Email -->
                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Alamat Emel</label>
                    <input type="email" name="email" class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="ahmad@email.com" required>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Kata Laluan</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="w-full pl-5 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="••••••••" required>
                        <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-emerald-700 transition-colors focus:outline-none">
                            <i class="fas fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="block text-[11px] font-bold text-emerald-900 uppercase mb-2 ml-1 opacity-70">Sahkan Kata Laluan</label>
                    <div class="relative">
                        <input type="password" id="password_confirm" name="password_confirm" class="w-full pl-5 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm font-medium" placeholder="••••••••" required>
                        <button type="button" onclick="togglePassword('password_confirm', 'eyeIcon2')" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-emerald-700 transition-colors focus:outline-none">
                            <i class="fas fa-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Validation Note -->
            <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100 flex items-start space-x-3">
                <input type="checkbox" class="mt-1 w-4 h-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" required>
                <p class="text-[11px] text-emerald-800 leading-relaxed font-medium">
                    Saya mengesahkan maklumat benar dan bersetuju dengan syarat pendaftaran <strong>Bangi Lama</strong>.
                </p>
            </div>

            <!-- Error Message -->
            <?php if ($message): ?>
                <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                    <p class="text-red-800 text-[11px] font-medium"><i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full bg-emerald-800 text-white py-4 rounded-xl font-bold text-sm hover:bg-emerald-700 transition-all shadow-lg flex items-center justify-center space-x-3 tracking-widest">
                <span>DAFTAR AKAUN</span>
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        </form>

        <div class="p-6 bg-gray-50/80 text-center border-t border-gray-100">
            <p class="text-gray-500 text-xs">Sudah mempunyai akaun? 
                <a href="login.php" class="text-emerald-700 font-bold hover:underline ml-1">Log Masuk</a>
            </p>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if ($status === "success"): ?>
    <div class="fixed inset-0 bg-emerald-950/90 backdrop-blur-md z-[100] flex items-center justify-center p-4">
        <div class="bg-white p-10 rounded-[2.5rem] text-center max-w-sm shadow-2xl">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-3xl"></i>
            </div>
            <h3 class="text-xl font-extrabold text-emerald-900 mb-2">Pendaftaran Berjaya!</h3>
            <p class="text-gray-500 text-xs mb-8 leading-relaxed">Sila log masuk untuk menguruskan maklumat pusara anda.</p>
            <a href="login.php" class="block w-full bg-emerald-800 text-white py-4 rounded-xl font-bold hover:bg-emerald-700 transition text-sm">LOG MASUK</a>
        </div>
    </div>
    <?php endif; ?>

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