<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> | SmartGrave Bangi Lama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
        }
        .sidebar-premium { background: linear-gradient(180deg, #064e3b 0%, #022c22 100%); }
        .glass-card { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .status-step-active { border-color: rgb(5, 150, 105); color: rgb(5, 150, 105); }
        .status-step-pending { border-color: rgb(229, 231, 235); color: rgb(156, 163, 175); }
        .profile-dropdown-menu.show-dropdown {
            visibility: visible !important;
            opacity: 1 !important;
            transform: translateY(0) scale(1) !important;
        }
    </style>
    <!-- AI Chatbot Assistant -->
    <script src="chatbot.js?v=<?= time() ?>" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.profile-dropdown-btn');
                if (btn) {
                    const container = btn.closest('.group');
                    if (container) {
                        const menu = container.querySelector('.profile-dropdown-menu');
                        if (menu) {
                            menu.classList.toggle('show-dropdown');
                            document.querySelectorAll('.profile-dropdown-menu').forEach(m => {
                                if (m !== menu) m.classList.remove('show-dropdown');
                            });
                        }
                    }
                } else {
                    document.querySelectorAll('.profile-dropdown-menu').forEach(m => {
                        m.classList.remove('show-dropdown');
                    });
                }
            });
        });
    </script>
</head>
<body class="text-slate-900">

<!-- Global Receipt Modal Overlay with blurred background -->
<div id="receiptModal" class="fixed inset-0 z-[3000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md transition-all duration-300">
    <div class="bg-transparent w-full max-w-xl max-h-[90vh] flex flex-col relative animate-in fade-in zoom-in-95 duration-200">
        <!-- Close Button -->
        <button onclick="closeReceiptModal()" class="absolute -top-12 right-0 bg-white/90 hover:bg-white text-slate-800 w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition z-[3100] no-print">
            <i class="fas fa-times text-lg"></i>
        </button>
        <!-- Iframe loading resit.php -->
        <iframe id="receiptIframe" src="" class="w-full h-[85vh] border-none rounded-[2.5rem] shadow-2xl" style="background: transparent;"></iframe>
    </div>
</div>

<script>
function openReceiptModal(type, id) {
    const modal = document.getElementById('receiptModal');
    const iframe = document.getElementById('receiptIframe');
    if (modal && iframe) {
        iframe.src = `resit.php?type=${type}&id=${id}&embed=1`;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}
function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    const iframe = document.getElementById('receiptIframe');
    if (modal && iframe) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        iframe.src = '';
        document.body.style.overflow = '';
    }
}
// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReceiptModal();
    }
});
</script>

<div class="flex min-h-screen">