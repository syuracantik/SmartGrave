<?php
session_start();
$title = "Infaq Digital";
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infaq Digital | SmartGrave Bangi Lama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
        }
        .bg-islamic {
            background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
        }
        .btn-amount.active {
            background-color: #dcfce7;
            border-color: #059669;
            color: #065f46;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
        }
    </style>
</head>
<body class="text-slate-800 min-h-screen flex flex-col">

    <!-- HEADER / NAVIGATION -->
    <nav class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-emerald-100 no-print">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center space-x-3">
                <div class="bg-emerald-800 p-2 rounded-xl text-yellow-400">
                    <i class="fas fa-mosque"></i>
                </div>
                <span class="text-2xl font-bold text-emerald-900 tracking-tight">Smart<span class="text-emerald-600">Grave</span></span>
            </a>
            <a href="index.php" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:text-emerald-950 transition">
                <i class="fas fa-arrow-left"></i> Kembali ke Laman Utama
            </a>
        </div>
    </nav>

    <!-- CONTENT LAYOUT -->
    <div class="flex-1 flex items-center justify-center p-4 md:p-8">
        <div class="w-full max-w-2xl bg-white rounded-[2.5rem] shadow-2xl border border-emerald-100 overflow-hidden">
            
            <!-- Banner / Header -->
            <div class="bg-islamic p-8 text-center text-white relative">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/arabesque.png')] opacity-25"></div>
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-white/10 text-yellow-400 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-white/20 shadow-inner">
                        <i class="fas fa-hand-holding-heart text-2xl animate-pulse"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Tabung Infaq Pengurusan Jenazah</h2>
                    <p class="text-emerald-200/95 text-xs font-semibold uppercase tracking-widest mt-2">Masjid Kariah Bangi Lama</p>
                    <p class="text-emerald-100/80 text-xs mt-3 max-w-md mx-auto leading-relaxed">
                        Sumbangan anda akan disalurkan terus bagi menampung kos pengurusan & pengebumian jenazah golongan asnaf dan waris yang kurang berkemampuan.
                    </p>
                </div>
            </div>

            <!-- Form -->
            <form action="payment.php" method="GET" class="p-8 md:p-10 space-y-6" id="infaqForm">
                <input type="hidden" name="type" value="infaq">

                <!-- 1. Pilihan Amaun -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pilih Jumlah Sumbangan (RM)</label>
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-3 mb-4">
                        <button type="button" onclick="setAmount(10, this)" class="btn-amount py-3 border-2 border-slate-200 rounded-2xl text-sm font-semibold hover:border-emerald-500 hover:bg-emerald-50/50 transition">10</button>
                        <button type="button" onclick="setAmount(30, this)" class="btn-amount py-3 border-2 border-slate-200 rounded-2xl text-sm font-semibold hover:border-emerald-500 hover:bg-emerald-50/50 transition">30</button>
                        <button type="button" onclick="setAmount(50, this)" class="btn-amount py-3 border-2 border-slate-200 rounded-2xl text-sm font-semibold hover:border-emerald-500 hover:bg-emerald-50/50 transition">50</button>
                        <button type="button" onclick="setAmount(100, this)" class="btn-amount py-3 border-2 border-slate-200 rounded-2xl text-sm font-semibold hover:border-emerald-500 hover:bg-emerald-50/50 transition">100</button>
                        <button type="button" onclick="setAmount(200, this)" class="btn-amount py-3 border-2 border-slate-200 rounded-2xl text-sm font-semibold hover:border-emerald-500 hover:bg-emerald-50/50 transition">200</button>
                    </div>
                    
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">RM</div>
                        <input type="number" name="amount" id="customAmount" placeholder="Atau masukkan amaun tersendiri..." min="1" step="any" oninput="clearActiveButtons()" class="w-full pl-12 pr-4 py-4 rounded-2xl border-2 border-slate-200 focus:border-emerald-500 focus:outline-none font-semibold text-slate-800 placeholder-slate-400 transition" required>
                    </div>
                </div>

                <div style="height:1px; background:#f1f5f9; margin: 1.5rem 0;"></div>

                <!-- 2. Maklumat Penderma -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Maklumat Penderma</label>
                        <label class="flex items-center space-x-2 cursor-pointer select-none">
                            <input type="checkbox" id="anonymousCheck" onchange="toggleAnonymous(this.checked)" class="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-500">Sumbang sebagai Hamba Allah (SULIT)</span>
                        </label>
                    </div>

                    <div id="pendermaFields" class="space-y-4 transition-all duration-300">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Nama Penuh Penderma</label>
                            <input type="text" name="name" id="donorName" placeholder="Masukkan nama penuh anda..." class="w-full px-4 py-3.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:outline-none text-sm font-medium transition">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-2">Alamat E-mel (Optional)</label>
                                <input type="email" name="email" id="donorEmail" placeholder="contoh@gmail.com" class="w-full px-4 py-3.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:outline-none text-sm font-medium transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-2">No. Telefon Bimbit (Optional)</label>
                                <input type="tel" name="phone" id="donorPhone" placeholder="01XXXXXXXX" class="w-full px-4 py-3.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:outline-none text-sm font-medium transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="w-full py-4 bg-emerald-700 hover:bg-emerald-800 text-white rounded-2xl font-bold transition shadow-lg shadow-emerald-700/10 flex items-center justify-center gap-2">
                    <i class="fas fa-heart text-yellow-400"></i> Teruskan ke Pembayaran
                </button>
            </form>

        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-emerald-50 py-6 text-center mt-auto no-print">
        <p class="text-gray-400 text-xs">© 2026 SmartGrave Bangi Lama. Urusan Pusara Digital Patuh Syariah.</p>
    </footer>

    <!-- JS -->
    <script>
        function setAmount(amount, button) {
            document.getElementById('customAmount').value = amount;
            clearActiveButtons();
            button.classList.add('active');
        }

        function clearActiveButtons() {
            document.querySelectorAll('.btn-amount').forEach(btn => btn.classList.remove('active'));
        }

        function toggleAnonymous(isAnonymous) {
            const fields = document.getElementById('pendermaFields');
            const nameInput = document.getElementById('donorName');
            const emailInput = document.getElementById('donorEmail');
            const phoneInput = document.getElementById('donorPhone');

            if (isAnonymous) {
                fields.style.opacity = '0.5';
                nameInput.value = 'Hamba Allah';
                nameInput.readOnly = true;
                emailInput.readOnly = true;
                phoneInput.readOnly = true;
            } else {
                fields.style.opacity = '1';
                nameInput.value = '';
                nameInput.readOnly = false;
                emailInput.readOnly = false;
                phoneInput.readOnly = false;
                nameInput.focus();
            }
        }
    </script>
</body>
</html>
