<?php
require_once 'db.php';
$baki_lot = 142; // default fallback
try {
    if (isset($pdo)) {
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM lot_pusara WHERE status_lot = 'Penuh'");
        if ($stmt_count) {
            $occupied_count = (int)$stmt_count->fetchColumn();
            $baki_lot = max(0, 440 - $occupied_count);
        }
    }
} catch (Exception $e) {
    // Keep default fallback
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartGrave | Sistem Pengurusan Pusara Islam Bangi Lama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-gradient { 
            background: linear-gradient(rgba(6, 78, 59, 0.85), rgba(6, 78, 59, 0.85)), 
            url('https://images.unsplash.com/photo-1590076214667-cda9336186b1?q=80&w=1600'); 
            background-size: cover; 
            background-position: center; 
        }
        .islamic-pattern { 
            background-color: #f8fafc; 
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png"); 
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        @keyframes pulse-soft {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .ai-float { animation: pulse-soft 3s infinite; }
    </style>
    <!-- AI Chatbot Assistant -->
    <script src="chatbot.js" defer></script>
</head>
<body class="islamic-pattern font-sans text-gray-800">

    <nav class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-emerald-100">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="bg-emerald-800 p-2 rounded-xl text-yellow-400">
                    <i class="fas fa-mosque"></i>
                </div>
                <span class="text-2xl font-bold text-emerald-900 tracking-tight">Smart<span class="text-emerald-600">Grave</span></span>
            </div>
            <div class="hidden md:flex space-x-8 font-medium text-emerald-900">
                <a href="index.php" class="text-emerald-600 font-bold">Laman Utama</a>
                <a href="carian.php" class="hover:text-emerald-600 transition">Cari Pusara</a>
                <a href="login.php" class="hover:text-emerald-600 transition">Permohonan Lot</a>
                <a href="login.php" class="hover:text-emerald-600 transition">Daftar Khairat</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="login.php" class="text-emerald-800 font-semibold hover:text-emerald-600 transition">Log Masuk</a>
                <a href="signup.php" class="bg-emerald-800 text-white px-6 py-2.5 rounded-full font-semibold hover:bg-emerald-700 transition shadow-lg shadow-emerald-200">Daftar Masuk</a>
            </div>
        </div>
    </nav>

    <header class="hero-gradient h-[600px] flex items-center justify-center text-center text-white px-4">
        <div class="max-w-4xl">
            <div class="inline-block bg-emerald-700/50 px-4 py-1 rounded-full text-emerald-200 text-sm font-bold mb-6 tracking-widest uppercase">
                Khusus Untuk Penduduk Bangi Lama
            </div>
            <h1 class="text-5xl md:text-7xl font-bold mb-6">Satu Sistem, Urusan Pusara Lebih Teratur</h1>
            <p class="text-xl mb-10 text-emerald-50 leading-relaxed max-w-2xl mx-auto">
                Platform digital untuk carian pusara, navigasi ke lokasi kubur, dan pengurusan tempahan lot perkuburan di Masjid Kariah Bangi
            </p>
            
            <form action="carian.php" method="GET" id="carian" class="bg-white p-3 rounded-2xl shadow-2xl flex flex-col md:flex-row gap-3">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="query" placeholder="Masukkan No. Kad Pengenalan atau Nama Arwah..." class="w-full pl-12 pr-4 py-4 rounded-xl text-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 border border-gray-100" required>
                </div>
                <button type="submit" class="bg-emerald-600 text-white px-10 py-4 rounded-xl font-bold hover:bg-emerald-500 transition-all shadow-lg">
                    Cari Lokasi Pusara
                </button>
            </form>
        </div>
    </header>

    <section class="max-w-7xl mx-auto -mt-16 px-6 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-card p-8 rounded-3xl shadow-xl border border-white flex items-center space-x-6">
                <div class="bg-emerald-100 p-4 rounded-2xl text-emerald-700"><i class="fas fa-layer-group text-3xl"></i></div>
                <div>
                    <p class="text-gray-500 text-sm font-bold uppercase">Baki Kapasiti Lot</p>
                    <h3 class="text-3xl font-black text-emerald-900"><?php echo htmlspecialchars($baki_lot); ?> <span class="text-lg font-normal text-gray-400">Kekosongan</span></h3>
                </div>
            </div>
            <div class="glass-card p-8 rounded-3xl shadow-xl border border-white flex items-center space-x-6">
                <div class="bg-blue-100 p-4 rounded-2xl text-blue-700"><i class="fas fa-map-marker-alt text-3xl"></i></div>
                <div>
                    <p class="text-gray-500 text-sm font-bold uppercase">Kawasan Liputan</p>
                    <h3 class="text-3xl font-black text-emerald-900">Bangi Lama</h3>
                </div>
            </div>
            <div class="glass-card p-8 rounded-3xl shadow-xl border border-white flex items-center space-x-6">
                <div class="bg-amber-100 p-4 rounded-2xl text-amber-700"><i class="fas fa-clock text-3xl"></i></div>
                <div>
                    <p class="text-gray-500 text-sm font-bold uppercase">Urusan Permohonan</p>
                    <h3 class="text-3xl font-black text-emerald-900">Digital 24/7</h3>
                </div>
            </div>
        </div>
    </section>

    <section id="tempahan" class="max-w-7xl mx-auto py-24 px-6">
        <div class="flex flex-col md:flex-row justify-between items-end mb-16">
            <div class="max-w-2xl">
                <h2 class="text-4xl font-bold text-emerald-900 mb-4">Urusan Lebih Mudah Untuk Waris</h2>
                <p class="text-gray-600 text-lg">Hanya beberapa klik untuk memastikan pengurusan jenazah kenalan atau ahli keluarga berjalan lancar.</p>
            </div>
            <div class="mt-6 md:mt-0">
                <span class="bg-emerald-50 text-emerald-700 px-4 py-2 rounded-full text-sm font-bold border border-emerald-100">
                    <i class="fas fa-info-circle mr-2"></i>Status Permohonan Real-Time
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
            <div class="p-8 bg-white rounded-3xl hover:shadow-2xl transition-all border border-transparent hover:border-emerald-100 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fas fa-search text-2xl"></i>
                </div>
                <h4 class="font-bold text-xl mb-3 text-emerald-900">Carian Mudah</h4>
                <p class="text-gray-500 text-sm leading-relaxed">Cari lot pusara menggunakan No. IC atau Nama Si Mati dengan paparan koordinat tepat.</p>
            </div>
            <div class="p-8 bg-white rounded-3xl hover:shadow-2xl transition-all border border-transparent hover:border-emerald-100 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fas fa-route text-2xl"></i>
                </div>
                <h4 class="font-bold text-xl mb-3 text-emerald-900">Navigasi Pejalan Kaki</h4>
                <p class="text-gray-500 text-sm leading-relaxed">Navigasi berjalan kaki dengan anggaran jarak dan masa perjalanan ke lokasi lot pusara yang dicari.</p>
            </div>
            <div class="p-8 bg-white rounded-3xl hover:shadow-2xl transition-all border border-transparent hover:border-emerald-100 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fas fa-file-signature text-2xl"></i>
                </div>
                <h4 class="font-bold text-xl mb-3 text-emerald-900">Tempahan Online</h4>
                <p class="text-gray-500 text-sm leading-relaxed">Mohon lot kubur sebaik sahaja berlaku kematian. Pantas dan mengurangkan birokrasi.</p>
            </div>
            <div class="p-8 bg-white rounded-3xl hover:shadow-2xl transition-all border border-transparent hover:border-emerald-100 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fas fa-credit-card text-2xl"></i>
                </div>
                <h4 class="font-bold text-xl mb-3 text-emerald-900">Bayaran Digital</h4>
                <p class="text-gray-500 text-sm leading-relaxed">Lakukan pembayaran atas talian. Status permohonan dikemaskini secara automatik.</p>
            </div>
        </div>
    </section>

    <section id="khairat" class="max-w-5xl mx-auto pb-24 px-6">
        <div class="bg-emerald-900 rounded-[40px] shadow-2xl overflow-hidden flex flex-col md:flex-row border-4 border-emerald-800">
            <div class="p-12 md:w-1/2 text-white bg-emerald-800/50">
                <h3 class="text-3xl font-bold mb-6 italic">Keahlian Khairat Kematian</h3>
                <p class="text-emerald-100 mb-8 leading-relaxed">Pendaftaran keahlian sangat digalakkan bagi meringankan beban kos pengebumian di masa hadapan.</p>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-yellow-400"></i>
                        <span>Pengebumian Percuma (Untuk Ahli)</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-yellow-400"></i>
                        <span>Urusan Mandi & Kafan Lengkap</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-yellow-400"></i>
                        <span>Van Jenazah Disediakan</span>
                    </div>
                </div>
                <a href="login.php" class="inline-block mt-10 bg-yellow-500 text-emerald-950 px-8 py-3 rounded-full font-black hover:bg-yellow-400 transition-all uppercase tracking-wider text-sm">
                    Daftar Ahli Sekarang
                </a>
            </div>
            <div class="p-12 md:w-1/2 bg-white flex flex-col justify-center">
                <div class="mb-8">
                    <p class="text-gray-400 text-sm font-bold uppercase mb-2">Kos Pengebumian (Bukan Ahli)</p>
                    <h4 class="text-5xl font-black text-emerald-900">RM 1,100</h4>
                    <p class="text-emerald-600 font-semibold mt-2 italic text-sm">*Tertutup untuk penduduk Bangi Lama sahaja</p>
                </div>
                <div class="p-6 bg-emerald-50 rounded-2xl border-l-8 border-emerald-500">
                    <p class="text-sm text-emerald-800 leading-relaxed italic">
                        <strong>Nota Penting:</strong> Permohonan lot hanya akan diproses setelah pembayaran disahkan (bagi bukan ahli) dan dokumen permit polis dimuat naik.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- INFAQ DIGITAL SECTION -->
    <section id="infaq-digital" class="max-w-7xl mx-auto pb-24 px-6">
        <div class="glass-card rounded-[40px] shadow-2xl border border-white overflow-hidden flex flex-col md:flex-row">
            <!-- Left Side: Graphic / Info -->
            <div class="p-12 md:w-1/2 bg-gradient-to-br from-emerald-950 to-emerald-900 text-white flex flex-col justify-center relative">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/arabesque.png')] opacity-10"></div>
                <div class="relative z-10">
                    <span class="bg-emerald-800/80 text-yellow-400 px-4 py-2 rounded-full text-xs font-extrabold uppercase tracking-wider border border-emerald-700">
                        <i class="fas fa-hand-holding-heart mr-2"></i>Amalan Jariah
                    </span>
                    <h3 class="text-3xl font-black mt-6 mb-4">Sumbangan Infaq Digital</h3>
                    <p class="text-emerald-100/90 text-sm leading-relaxed mb-6">
                        Bantu kami meringankan beban kewangan golongan asnaf dan keluarga kurang berkemampuan untuk membiayai kos pengurusan jenazah.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="bg-emerald-800/80 w-8 h-8 rounded-full flex items-center justify-center text-yellow-400 text-xs">
                                <i class="fas fa-heart"></i>
                            </div>
                            <span class="text-sm font-semibold text-emerald-50">Sumbangan Ikhlas Hamba Allah</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="bg-emerald-800/80 w-8 h-8 rounded-full flex items-center justify-center text-yellow-400 text-xs">
                                <i class="fas fa-shield-check"></i>
                            </div>
                            <span class="text-sm font-semibold text-emerald-50">Telus, Selamat, & Terus ke Tabung Kebajikan</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Quick Donation Form -->
            <div class="p-12 md:w-1/2 bg-white flex flex-col justify-center">
                <h4 class="text-lg font-bold text-emerald-950 mb-6">Sumbang Secara Atas Talian</h4>
                
                <form action="payment.php" method="GET" class="space-y-4">
                    <input type="hidden" name="type" value="infaq">
                    
                    <!-- Quick Amount buttons -->
                    <div class="grid grid-cols-4 gap-2 mb-4">
                        <button type="button" onclick="setQuickAmount(10)" class="quick-btn py-2.5 border border-slate-200 rounded-xl text-xs font-bold hover:border-emerald-600 hover:bg-emerald-50/50 transition">RM 10</button>
                        <button type="button" onclick="setQuickAmount(30)" class="quick-btn py-2.5 border border-slate-200 rounded-xl text-xs font-bold hover:border-emerald-600 hover:bg-emerald-50/50 transition">RM 30</button>
                        <button type="button" onclick="setQuickAmount(50)" class="quick-btn py-2.5 border border-slate-200 rounded-xl text-xs font-bold hover:border-emerald-600 hover:bg-emerald-50/50 transition">RM 50</button>
                        <button type="button" onclick="setQuickAmount(100)" class="quick-btn py-2.5 border border-slate-200 rounded-xl text-xs font-bold hover:border-emerald-600 hover:bg-emerald-50/50 transition">RM 100</button>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Amaun Sumbangan (RM)</label>
                        <input type="number" name="amount" id="infaqAmountInput" placeholder="Masukkan amaun sumbangan..." min="1" step="any" oninput="clearQuickBtns()" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-semibold" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Nama Penderma (Biarkan kosong jika tidak ingin dinyatakan)</label>
                        <input type="text" name="name" placeholder="Nama..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm font-medium">
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3.5 rounded-xl font-bold transition shadow-lg shadow-emerald-600/10 flex items-center justify-center gap-2 mt-4 text-sm">
                        <i class="fas fa-heart text-yellow-400"></i> Sumbang Sekarang
                    </button>
                </form>
            </div>
        </div>
    </section>

    <footer class="bg-white border-t border-emerald-50 py-10 text-center">
        <p class="text-gray-400 text-sm">© 2026 SmartGrave Bangi Lama. Urusan Pusara Digital Patuh Syariah.</p>
    </footer>

    <script>
        function setQuickAmount(amount) {
            document.getElementById('infaqAmountInput').value = amount;
            clearQuickBtns();
            // Highlight the clicked button
            const btns = document.querySelectorAll('.quick-btn');
            btns.forEach(btn => {
                if (btn.innerText.includes(amount)) {
                    btn.classList.add('bg-emerald-50', 'border-emerald-600', 'text-emerald-900');
                }
            });
        }
        function clearQuickBtns() {
            document.querySelectorAll('.quick-btn').forEach(btn => {
                btn.classList.remove('bg-emerald-50', 'border-emerald-600', 'text-emerald-900');
            });
        }
    </script>
</body>
</html>