<?php
session_start();
// Include fail sambungan database
include 'db.php';

// Check if user is logged in and is admin / pentadbir
if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'pentadbir' && strtolower($_SESSION['role'] ?? '') !== 'admin')) {
    header("Location: login.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Admin';

// ── AJAX: Delete ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM daftar_khairat WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID tidak sah']);
    }
    exit;
}

// ── AJAX: Update ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json');
    $id          = intval($_POST['id'] ?? 0);
    $nama        = trim($_POST['nama_ahli']    ?? '');
    $no_ic       = trim($_POST['no_ic']        ?? '');
    $telefon     = trim($_POST['telefon']      ?? '');
    $pekerjaan   = trim($_POST['pekerjaan']    ?? '');
    $status      = trim($_POST['status_yuran'] ?? '');
    $tarikh      = trim($_POST['tarikh_daftar']?? '');

    if ($id > 0 && $nama !== '') {
        try {
            $stmt = $pdo->prepare("
                UPDATE daftar_khairat
                SET nama_ahli=?, no_ic=?, telefon=?, pekerjaan=?, status_yuran=?, tarikh_daftar=?
                WHERE id=?
            ");
            $stmt->execute([$nama, $no_ic, $telefon, $pekerjaan, $status, $tarikh, $id]);
            echo json_encode(['success' => true,
                'data' => compact('id','nama','no_ic','telefon','pekerjaan','status','tarikh')]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    }
    exit;
}

// ── Normal page load ──────────────────────────────────────────────────────────
try {
    // Check if member has passed away by checking if their IC exists in maklumat_jenazah table (ignoring dashes)
    $query = "
        SELECT dk.*, 
               EXISTS (
                   SELECT 1 FROM maklumat_jenazah mj 
                   WHERE REPLACE(mj.no_ic, '-', '') = REPLACE(dk.no_ic, '-', '')
               ) AS telah_meninggal
        FROM daftar_khairat dk
        ORDER BY dk.created_at DESC
    ";
    $stmt  = $pdo->prepare($query);
    $stmt->execute();
    $ahli_khairat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<?php
$title = "Senarai Ahli Khairat";
include 'header.php';
?>
<style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    background-color: #f8fafc;
    background-image: url("https://www.transparenttextures.com/patterns/arabesque.png");
}


    .mono { font-family: 'DM Mono', monospace; }

    mark { background:#fef08a; color:#713f12; border-radius:2px; padding:0 1px; }

    @keyframes rowIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
    .row-animate { animation: rowIn .25s ease both; }
     tbody tr {
    transition:
        background .18s ease,
        transform .18s ease,
        box-shadow .18s ease;
}

tbody tr:hover{
    transform: translateY(-1px);
    box-shadow: inset 0 0 0 9999px rgba(59,130,246,0.05);
}

    /* ── Stat cards ── */
    .stat-card {
    background: rgba(255,255,255,0.72);
    backdrop-filter: blur(14px);

    border-radius: 18px;
    border: 1px solid rgba(255,255,255,0.5);

    box-shadow:
        0 4px 20px rgba(15,23,42,.06);

    padding: 16px 22px;

    display:flex;
    align-items:center;
    gap:14px;

    min-width:120px;
}
    
    .stat-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 16px;
    }

    /* ── Badges ── */
    .badge-bayar {
        display:inline-flex; align-items:center; gap:5px;
        background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;
        font-size:11px; font-weight:700; padding:4px 11px; border-radius:20px; white-space:nowrap;
    }
    .badge-tunggak {
        display:inline-flex; align-items:center; gap:5px;
        background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;
        font-size:11px; font-weight:700; padding:4px 11px; border-radius:20px; white-space:nowrap;
    }
    .badge-bayar::before  { content:''; width:6px; height:6px; border-radius:50%; background:#22c55e; flex-shrink:0; }
    .badge-tunggak::before{ content:''; width:6px; height:6px; border-radius:50%; background:#ef4444; flex-shrink:0; }

    /* ── Buttons ── */
    .btn-edit {
        display:inline-flex; align-items:center; gap:5px;
        background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;
        font-size:11px; font-weight:700; padding:6px 13px; border-radius:7px;
        cursor:pointer; transition:all .15s; white-space:nowrap;
    }
    .btn-edit:hover { background:#1d4ed8; color:#fff; border-color:#1d4ed8; box-shadow: 0 3px 8px rgba(29,78,216,.25); }

    .btn-del {
        display:inline-flex; align-items:center; gap:5px;
        background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;
        font-size:11px; font-weight:700; padding:6px 13px; border-radius:7px;
        cursor:pointer; transition:all .15s; white-space:nowrap;
    }
    .btn-del:hover { background:#b91c1c; color:#fff; border-color:#b91c1c; box-shadow: 0 3px 8px rgba(185,28,28,.25); }

    /* ── Table card ── */
    .table-card {
    background: rgba(255,255,255,0.78);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);

    border-radius: 24px;
    border: 1px solid rgba(255,255,255,0.5);

    box-shadow:
        0 10px 30px rgba(15,23,42,0.08),
        0 2px 10px rgba(15,23,42,0.05);

    overflow: hidden;
    width: 100%;

    position: relative;
}

    /* ── Table styles ── */
    #khairatTable { width: 100%; border-collapse: collapse; table-layout: auto; }
    thead th { position: sticky; top: 0; z-index: 10; background: #f8fafc; }
    tbody tr:last-child td { border-bottom: none; }

    /* ── Search ── */
    .search-wrap { position: relative; flex: 1; min-width: 0; }
    .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px; pointer-events:none; }
    .search-input {
        width: 100%; background: white; border: 1.5px solid #e2e8f0;
        border-radius: 10px; padding: 10px 14px 10px 36px;
        font-size: 13px; color: #374151; font-family: 'Inter', sans-serif;
        transition: all .15s; box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .search-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
    .search-input::placeholder { color: #94a3b8; }

    #emptyState { display:none; }

    @keyframes pop { 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }
    .pop { animation:pop .2s ease; }

    /* ── Row delete animation ── */
    @keyframes rowOut { from{opacity:1;max-height:80px;} to{opacity:0;max-height:0;padding-top:0;padding-bottom:0;} }
    .row-deleting td { animation:rowOut .3s ease forwards; overflow:hidden; padding-top:0; padding-bottom:0; }

    /* ── Avatar ── */
    .avatar {
        width:36px; height:36px; border-radius:10px;
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
        font-size:13px; font-weight:800; text-transform:uppercase;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1d4ed8;
        box-shadow: 0 2px 6px rgba(29,78,216,.15);
    }

    /* ── Modal overlay ── */
    #editModal {
        display:none; position:fixed; inset:0; z-index:50;
        background:rgba(0,0,0,.45); backdrop-filter:blur(3px);
        align-items:center; justify-content:center;
    }
    #editModal.open { display:flex; }

    @keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal-box { animation:modalIn .22s cubic-bezier(.34,1.56,.64,1) both; }

    /* ── Form fields ── */
    .field-label { display:block; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; }
    .field-input {
        width:100%; border:1.5px solid #e5e7eb; border-radius:8px;
        padding:9px 12px; font-size:13px; color:#1f2937; background:#fafafa;
        transition:border-color .15s, box-shadow .15s;
        font-family:'Inter',sans-serif;
    }
    .field-input:focus { outline:none; border-color:#1d4ed8; box-shadow:0 0 0 3px rgba(29,78,216,.1); background:#fff; }
    select.field-input { cursor:pointer; }

    /* ── Toast ── */
    #toast {
        position:fixed; bottom:28px; right:28px; z-index:999;
        background:#1a1a2e; color:#fff; padding:12px 20px; border-radius:10px;
        font-size:13px; font-weight:600; display:flex; align-items:center; gap:10px;
        box-shadow:0 8px 32px rgba(0,0,0,.25);
        transform:translateY(80px); opacity:0; transition:all .3s cubic-bezier(.34,1.56,.64,1);
        pointer-events:none;
    }
    #toast.show { transform:translateY(0); opacity:1; }
    #toast.success .toast-icon { color:#22c55e; }
    #toast.error   .toast-icon { color:#ef4444; }

    /* ── Delete confirm modal ── */
    #confirmModal {
        display:none; position:fixed; inset:0; z-index:60;
        background:rgba(0,0,0,.5); backdrop-filter:blur(4px);
        align-items:center; justify-content:center;
    }
    #confirmModal.open { display:flex; }

    /* Loading spinner on save button */
    .btn-saving { pointer-events:none; opacity:.7; }
</style>
<?php include 'sidebar2.php'; ?>

<div class="flex-1 min-w-0">

        <main class="px-8 py-6 lg:px-10 xl:px-14 max-w-[1700px] mx-auto w-full">

            <!-- Page Title Row -->
             <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-emerald-950">Senarai Ahli Khairat Kematian</h1>
            <p class="text-emerald-700 font-medium tracking-tight">Urus dan semak semua rekod ahli berdaftar</p>
        </div>
                <div class="flex items-center gap-2">
                    <div class="bg-white border border-gray-200 rounded-lg px-4 py-2 flex items-center gap-3 shadow-sm">
                        <div class="text-center">
                            <div class="mono text-lg font-700 text-gray-800 leading-tight" id="totalCount"><?php echo count($ahli_khairat); ?></div>
                            <div class="text-xs text-gray-400">Jumlah</div>
                        </div>
                        <div class="w-px h-8 bg-gray-100"></div>
                        <div class="text-center">
                            <div class="mono text-lg font-700 text-green-600 leading-tight" id="totalBayar">
                                <?php echo count(array_filter($ahli_khairat, fn($a) => $a['status_yuran'] == 'Dibayar')); ?>
                            </div>
                            <div class="text-xs text-gray-400">Dibayar</div>
                        </div>
                        <div class="w-px h-8 bg-gray-100"></div>
                        <div class="text-center">
                            <div class="mono text-lg font-700 text-red-500 leading-tight" id="totalTunggak">
                                <?php echo count(array_filter($ahli_khairat, fn($a) => $a['status_yuran'] != 'Dibayar')); ?>
                            </div>
                            <div class="text-xs text-gray-400">Tunggakan</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search + Filter Row -->
            <div class="mb-6 flex flex-wrap items-center gap-4 bg-white/60 backdrop-blur-md border border-white/40 rounded-2xl px-5 py-4 shadow-sm">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input type="text" id="searchInput" class="search-input w-full bg-white border border-gray-200 rounded-lg py-2.5 pl-9 pr-4 text-sm text-gray-700 shadow-sm transition-all"
                        placeholder="Cari nama, No. IC, telefon..." oninput="doSearch()">
                </div>
                <select id="statusFilter" onchange="doSearch()"
                    class="bg-white border border-gray-200 rounded-lg py-2.5 px-3 text-sm text-gray-600 shadow-sm cursor-pointer focus:outline-none focus:border-blue-500">
                    <option value="">Semua Status</option>
                    <option value="Dibayar">Dibayar</option>
                    <option value="Tunggakan">Tunggakan</option>
                </select>
                <span class="text-sm text-gray-400 mono">
                    Papar: <span id="showCount" class="font-700 text-gray-700"><?php echo count($ahli_khairat); ?></span> rekod
                </span>
            </div>

            <!-- Table Card -->
            <div class="table-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="khairatTable">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50 w-12">No.</th>
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">Nama Ahli</th>
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">No. IC</th>
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">No. Telefon</th>
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">Status Yuran</th>
                                <th class="px-5 py-3.5 text-left text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">Tarikh Daftar</th>
                                <th class="px-5 py-3.5 text-center text-xs font-700 text-gray-500 uppercase tracking-wider bg-gray-50">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (count($ahli_khairat) > 0): ?>
                                <?php $no = 1; foreach ($ahli_khairat as $ahli): ?>
                                <?php 
                                    $is_deceased = !empty($ahli['telah_meninggal']);
                                    $row_class = $is_deceased 
                                        ? 'bg-rose-50/70 hover:bg-rose-100/70 border-l-4 border-l-rose-500' 
                                        : 'hover:bg-blue-50/40';
                                    $avatar_bg = $is_deceased 
                                        ? 'bg-rose-100 text-rose-700' 
                                        : 'bg-blue-100 text-blue-700';
                                ?>
                                <tr class="border-b border-gray-50 row-animate <?php echo $row_class; ?>"
                                    id="row-<?php echo $ahli['id']; ?>"
                                    data-id="<?php echo $ahli['id']; ?>"
                                    data-nama="<?php echo strtolower(htmlspecialchars($ahli['nama_ahli'])); ?>"
                                    data-ic="<?php echo htmlspecialchars($ahli['no_ic']); ?>"
                                    data-tel="<?php echo htmlspecialchars($ahli['telefon']); ?>"
                                    data-status="<?php echo htmlspecialchars($ahli['status_yuran']); ?>"
                                    style="animation-delay:<?php echo ($no-1)*0.03; ?>s">

                                    <td class="px-5 py-4">
                                        <span class="mono text-xs text-gray-400 font-500 row-no"><?php echo str_pad($no++, 2, '0', STR_PAD_LEFT); ?></span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full <?php echo $avatar_bg; ?> flex items-center justify-center flex-shrink-0 avatar-circle">
                                                <span class="text-xs font-700 uppercase avatar-initial"><?php echo mb_substr($ahli['nama_ahli'],0,1); ?></span>
                                            </div>
                                            <div>
                                                <p class="font-600 text-gray-800 searchable-nama cell-nama">
                                                    <?php echo htmlspecialchars($ahli['nama_ahli']); ?>
                                                    <?php if ($is_deceased): ?>
                                                        <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 text-[10px] font-bold px-2 py-0.5 rounded-full ml-2 border border-rose-200 shadow-sm">
                                                            <i class="fas fa-ribbon"></i> Meninggal Dunia
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-gray-400 mt-0.5 cell-pekerjaan"><?php echo htmlspecialchars($ahli['pekerjaan']); ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="mono text-xs text-gray-600 searchable-ic cell-ic"><?php echo htmlspecialchars($ahli['no_ic']); ?></span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="mono text-xs text-gray-600 searchable-tel cell-tel"><?php echo htmlspecialchars($ahli['telefon']); ?></span>
                                    </td>

                                    <td class="px-5 py-4 cell-status-td">
                                        <?php if ($ahli['status_yuran'] == 'Dibayar'): ?>
                                            <span class="badge-bayar">Dibayar</span>
                                        <?php else: ?>
                                            <span class="badge-tunggak">Tunggakan</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="mono text-xs text-gray-500 cell-tarikh"><?php echo date('d/m/Y', strtotime($ahli['tarikh_daftar'])); ?></span>
                                        <span class="hidden cell-tarikh-raw"><?php echo htmlspecialchars($ahli['tarikh_daftar']); ?></span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="openEditModal(<?php echo $ahli['id']; ?>)" class="btn-edit">
                                                <i class="fas fa-pen-to-square text-xs"></i> Edit
                                            </button>
                                            <button onclick="confirmDelete(<?php echo $ahli['id']; ?>, '<?php echo addslashes(htmlspecialchars($ahli['nama_ahli'])); ?>')" class="btn-del">
                                                <i class="fas fa-trash-can text-xs"></i> Padam
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                                        <i class="fas fa-users-slash text-3xl mb-3 opacity-30 block"></i>
                                        Tiada rekod ahli khairat dijumpai.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Empty search state -->
                <div id="emptyState" class="py-16 text-center text-gray-400">
                    <i class="fas fa-magnifying-glass text-3xl mb-3 opacity-30 block"></i>
                    <p class="text-sm font-500">Tiada rekod sepadan dengan carian.</p>
                    <p class="text-xs mt-1 text-gray-300">Cuba kata kunci lain atau tukar penapis.</p>
                </div>

                <!-- Table footer -->
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                    <span class="text-xs text-gray-400 mono">
                        Jumlah keseluruhan: <strong class="text-gray-600" id="footerTotal"><?php echo count($ahli_khairat); ?></strong> ahli berdaftar
                    </span>
                    <span class="text-xs text-gray-300">Dikemaskini: <?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
    <footer class="text-center text-slate-400 text-[10px] mt-10 tracking-widest uppercase pb-6">
        &copy; 2026 SmartGrave Bangi Lama. Sistem Pengurusan Jenazah Digital Patuh Syariah.
    </footer>
        </main>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     EDIT MODAL
════════════════════════════════════════════════════════════════ -->
<div id="editModal">
    <div class="modal-box bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-user-pen text-blue-600 text-sm"></i>
                </div>
                <div>
                    <h2 class="text-base font-700 text-gray-800">Kemaskini Maklumat Ahli</h2>
                    <p class="text-xs text-gray-400 mt-0.5">ID: <span id="modalIdLabel" class="mono font-600 text-gray-600"></span></p>
                </div>
            </div>
            <button onclick="closeEditModal()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                <i class="fas fa-xmark text-gray-500 text-sm"></i>
            </button>
        </div>

        <!-- Modal body -->
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="editId">

            <!-- Nama Ahli -->
            <div>
                <label class="field-label" for="editNama">Nama Penuh</label>
                <input type="text" id="editNama" class="field-input" placeholder="Nama penuh ahli...">
            </div>

            <!-- Row: IC + Telefon -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label" for="editIc">No. Kad Pengenalan</label>
                    <input type="text" id="editIc" class="field-input mono" placeholder="000000-00-0000">
                </div>
                <div>
                    <label class="field-label" for="editTel">No. Telefon</label>
                    <input type="text" id="editTel" class="field-input mono" placeholder="01X-XXXXXXX">
                </div>
            </div>

            <!-- Pekerjaan -->
            <div>
                <label class="field-label" for="editPekerjaan">Pekerjaan</label>
                <input type="text" id="editPekerjaan" class="field-input" placeholder="Jawatan / pekerjaan ahli...">
            </div>

            <!-- Row: Status + Tarikh -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label" for="editStatus">Status Yuran</label>
                    <select id="editStatus" class="field-input">
                        <option value="Dibayar">Dibayar</option>
                        <option value="Tunggakan">Tunggakan</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="editTarikh">Tarikh Daftar</label>
                    <input type="date" id="editTarikh" class="field-input">
                </div>
            </div>
        </div>

        <!-- Modal footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
            <button onclick="closeEditModal()"
                class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-600 text-gray-600 hover:bg-gray-100 transition-colors">
                Batal
            </button>
            <button id="saveBtn" onclick="saveEdit()"
                class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-700 transition-colors flex items-center gap-2">
                <i class="fas fa-floppy-disk text-xs"></i>
                Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════════════════════════════════ -->
<div id="confirmModal">
    <div class="modal-box bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 pt-6 pb-4 text-center">
            <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-triangle-exclamation text-red-500 text-xl"></i>
            </div>
            <h3 class="text-base font-700 text-gray-800 mb-1">Padam Rekod Ahli?</h3>
            <p class="text-sm text-gray-500">Anda akan memadam rekod bagi:</p>
            <p class="text-sm font-700 text-gray-800 mt-1" id="confirmName"></p>
            <p class="text-xs text-red-400 mt-3 bg-red-50 rounded-lg px-3 py-2">
                <i class="fas fa-circle-info mr-1"></i> Tindakan ini tidak boleh dibatalkan.
            </p>
        </div>
        <div class="px-6 pb-5 flex gap-3">
            <button onclick="closeConfirm()"
                class="flex-1 py-2.5 rounded-lg border border-gray-200 text-sm font-600 text-gray-600 hover:bg-gray-100 transition-colors">
                Batal
            </button>
            <button id="confirmDelBtn" onclick="doDelete()"
                class="flex-1 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-700 transition-colors flex items-center justify-center gap-2">
                <i class="fas fa-trash-can text-xs"></i> Padam
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     TOAST
════════════════════════════════════════════════════════════════ -->
<div id="toast">
    <i class="fas fa-circle-check toast-icon"></i>
    <span id="toastMsg"></span>
</div>

<script>
// ── Helpers ────────────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    const icon = t.querySelector('.toast-icon');
    document.getElementById('toastMsg').textContent = msg;
    t.className = '';
    t.classList.add(type);
    icon.className = `fas ${type==='success'?'fa-circle-check':'fa-circle-xmark'} toast-icon`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

function reNumberRows() {
    const rows = document.querySelectorAll('#tableBody tr[data-id]');
    rows.forEach((row, i) => {
        const noEl = row.querySelector('.row-no');
        if (noEl) noEl.textContent = String(i+1).padStart(2,'0');
    });
}

function refreshCounters() {
    const rows   = [...document.querySelectorAll('#tableBody tr[data-id]')];
    const total  = rows.length;
    const bayar  = rows.filter(r => r.dataset.status === 'Dibayar').length;
    const tunggak= total - bayar;

    document.getElementById('totalCount').textContent  = total;
    document.getElementById('totalBayar').textContent  = bayar;
    document.getElementById('totalTunggak').textContent= tunggak;
    document.getElementById('footerTotal').textContent = total;
    document.getElementById('showCount').textContent   = document.querySelectorAll('#tableBody tr[data-id]:not([style*="display: none"])').length;
}

// ── Search ─────────────────────────────────────────────────────────────────────
function doSearch() {
    const kw      = document.getElementById('searchInput').value.toLowerCase().trim();
    const statusF = document.getElementById('statusFilter').value.toLowerCase();
    const rows    = document.querySelectorAll('#tableBody tr[data-id]');
    let visible   = 0;

    rows.forEach(row => {
        const nama   = row.dataset.nama   || '';
        const ic     = (row.dataset.ic    || '').toLowerCase();
        const tel    = (row.dataset.tel   || '').toLowerCase();
        const status = row.dataset.status || '';
        const matchKw     = !kw || nama.includes(kw) || ic.includes(kw) || tel.includes(kw);
        const matchStatus = !statusF || status.toLowerCase() === statusF;

        if (matchKw && matchStatus) {
            row.style.display = '';
            visible++;
            if (kw) {
                highlightCell(row.querySelector('.searchable-nama'), kw);
                highlightCell(row.querySelector('.searchable-ic'),  kw);
                highlightCell(row.querySelector('.searchable-tel'), kw);
            } else {
                clearHighlight(row.querySelector('.searchable-nama'));
                clearHighlight(row.querySelector('.searchable-ic'));
                clearHighlight(row.querySelector('.searchable-tel'));
            }
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('showCount').textContent = visible;
    document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    const sc = document.getElementById('showCount');
    sc.classList.remove('pop'); void sc.offsetWidth; sc.classList.add('pop');
}

function highlightCell(el, kw) {
    if (!el) return;
    const original = el.getAttribute('data-original') || el.textContent;
    el.setAttribute('data-original', original);
    const regex = new RegExp(`(${kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi');
    el.innerHTML = original.replace(regex, '<mark>$1</mark>');
}
function clearHighlight(el) {
    if (!el) return;
    const original = el.getAttribute('data-original');
    if (original) { el.textContent = original; el.removeAttribute('data-original'); }
}

// ── Edit Modal ─────────────────────────────────────────────────────────────────
function openEditModal(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    document.getElementById('editId').value           = id;
    document.getElementById('modalIdLabel').textContent = '#' + String(id).padStart(4,'0');
    document.getElementById('editNama').value         = row.querySelector('.cell-nama').textContent.trim();
    document.getElementById('editIc').value           = row.querySelector('.cell-ic').textContent.trim();
    document.getElementById('editTel').value          = row.querySelector('.cell-tel').textContent.trim();
    document.getElementById('editPekerjaan').value    = row.querySelector('.cell-pekerjaan').textContent.trim();
    document.getElementById('editStatus').value       = row.dataset.status;
    document.getElementById('editTarikh').value       = row.querySelector('.cell-tarikh-raw').textContent.trim();

    document.getElementById('editModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('editNama').focus(), 150);
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    document.body.style.overflow = '';
}

async function saveEdit() {
    const id        = document.getElementById('editId').value;
    const nama      = document.getElementById('editNama').value.trim();
    const no_ic     = document.getElementById('editIc').value.trim();
    const telefon   = document.getElementById('editTel').value.trim();
    const pekerjaan = document.getElementById('editPekerjaan').value.trim();
    const status    = document.getElementById('editStatus').value;
    const tarikh    = document.getElementById('editTarikh').value;

    if (!nama) { document.getElementById('editNama').focus(); return; }

    const btn = document.getElementById('saveBtn');
    btn.classList.add('btn-saving');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Menyimpan...';

    try {
        const fd = new FormData();
        fd.append('action',        'update');
        fd.append('id',            id);
        fd.append('nama_ahli',     nama);
        fd.append('no_ic',         no_ic);
        fd.append('telefon',       telefon);
        fd.append('pekerjaan',     pekerjaan);
        fd.append('status_yuran',  status);
        fd.append('tarikh_daftar', tarikh);

        const res  = await fetch('', { method:'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            const row = document.getElementById('row-' + id);

            // Update cells
            row.querySelector('.cell-nama').textContent      = nama;
            row.querySelector('.cell-ic').textContent        = no_ic;
            row.querySelector('.cell-tel').textContent       = telefon;
            row.querySelector('.cell-pekerjaan').textContent = pekerjaan;
            row.querySelector('.cell-tarikh-raw').textContent= tarikh;
            row.querySelector('.cell-tarikh').textContent    = formatDate(tarikh);

            // Status badge
            const td = row.querySelector('.cell-status-td');
            td.innerHTML = status === 'Dibayar'
                ? '<span class="badge-bayar">Dibayar</span>'
                : '<span class="badge-tunggak">Tunggakan</span>';

            // Avatar initial
            row.querySelector('.avatar-initial').textContent = nama.charAt(0).toUpperCase();

            // Dataset
            row.dataset.nama   = nama.toLowerCase();
            row.dataset.ic     = no_ic;
            row.dataset.tel    = telefon;
            row.dataset.status = status;

            // Clear search highlights for this row
            clearHighlight(row.querySelector('.searchable-nama'));
            clearHighlight(row.querySelector('.searchable-ic'));
            clearHighlight(row.querySelector('.searchable-tel'));

            refreshCounters();
            closeEditModal();
            showToast('Rekod berjaya dikemaskini!', 'success');

            // Flash row
            row.style.background = '#eff6ff';
            setTimeout(() => row.style.background = '', 1200);
        } else {
            showToast('Ralat: ' + (json.message || 'Kemaskini gagal'), 'error');
        }
    } catch(e) {
        showToast('Ralat sambungan. Cuba lagi.', 'error');
    }

    btn.classList.remove('btn-saving');
    btn.innerHTML = '<i class="fas fa-floppy-disk text-xs"></i> Simpan Perubahan';
}

// ── Delete ─────────────────────────────────────────────────────────────────────
let _deleteId = null;

function confirmDelete(id, nama) {
    _deleteId = id;
    document.getElementById('confirmName').textContent = nama;
    document.getElementById('confirmModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    document.body.style.overflow = '';
    _deleteId = null;
}

async function doDelete() {
    if (!_deleteId) return;
    const id  = _deleteId;
    const btn = document.getElementById('confirmDelBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Memadam...';
    btn.disabled  = true;

    try {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        const res  = await fetch('', { method:'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            closeConfirm();
            const row = document.getElementById('row-' + id);
            if (row) {
                row.classList.add('row-deleting');
                setTimeout(() => {
                    row.remove();
                    reNumberRows();
                    refreshCounters();
                    const remaining = document.querySelectorAll('#tableBody tr[data-id]').length;
                    if (remaining === 0) {
                        document.getElementById('tableBody').innerHTML =
                            `<tr><td colspan="7" class="px-5 py-16 text-center text-gray-400">
                                <i class="fas fa-users-slash text-3xl mb-3 opacity-30 block"></i>
                                Tiada rekod ahli khairat dijumpai.
                            </td></tr>`;
                    }
                }, 320);
            }
            showToast('Rekod berjaya dipadam.', 'success');
        } else {
            showToast('Ralat: ' + (json.message || 'Padam gagal'), 'error');
            btn.innerHTML = '<i class="fas fa-trash-can text-xs"></i> Padam';
            btn.disabled  = false;
        }
    } catch(e) {
        showToast('Ralat sambungan. Cuba lagi.', 'error');
        btn.innerHTML = '<i class="fas fa-trash-can text-xs"></i> Padam';
        btn.disabled  = false;
    }
}

// ── Utilities ──────────────────────────────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

// Close modals on backdrop click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeEditModal();
        closeConfirm();
    }
});
</script>
</div> <!-- close flex-1 min-w-0 -->
</div> <!-- close flex min-h-screen from header.php -->
</body>
</html>