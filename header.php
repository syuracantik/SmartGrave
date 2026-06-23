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
        .status-step-active { @apply border-emerald-600 text-emerald-600; }
        .status-step-pending { @apply border-gray-200 text-gray-400; }
    </style>
</head>
<body class="text-slate-900">
<div class="flex min-h-screen">