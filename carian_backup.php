<?php
// SmartGrave — Carian Pusara
// Masjid Kariah Bangi — Perkuburan Islam

$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';

$final_graves = [];
$mock_graves = [];

$graves_map = [];
foreach ($mock_graves as $mg) {
    if (isset($mg['kind'])) {
        $mg['nama'] = $mg['kind'];
        unset($mg['kind']);
    }
    $graves_map[$mg['id']] = array_merge([
        'gambar_kiri' => '',
        'gambar_kanan' => '',
        'gambar_penanda' => ''
    ], $mg);
}

try {
    include 'db.php';
    if (isset($pdo)) {
        // Upgrade table with guide image columns if not exists
        try {
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kiri VARCHAR(255)");
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kanan VARCHAR(255)");
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_penanda VARCHAR(255)");
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kiri_desc VARCHAR(255)");
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_kanan_desc VARCHAR(255)");
            $pdo->exec("ALTER TABLE lot_pusara ADD COLUMN IF NOT EXISTS gambar_penanda_desc VARCHAR(255)");
        } catch (Exception $ex) {}

        $stmt = $pdo->query("
            SELECT lp.no_lot AS id, j.nama_jenazah AS nama, j.no_ic AS ic, j.tarikh_wafat AS mati,
                   lp.gambar_kiri, lp.gambar_kanan, lp.gambar_penanda,
                   lp.gambar_kiri_desc, lp.gambar_kanan_desc, lp.gambar_penanda_desc
            FROM lot_pusara lp
            JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
            WHERE lp.status_lot = 'Penuh'
        ");
        if ($stmt) {
            $db_graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($db_graves as $g) {
                $id = $g['id'];
                $zon = substr($id, 0, 1);
                $mati = $g['mati'] ? date('d/m/Y', strtotime($g['mati'])) : '—';
                $ic = preg_replace('/[^0-9]/', '', $g['ic']);
                $lahir = '—';
                if (strlen($ic) === 12) {
                    $year_part = substr($ic, 0, 2);
                    $month_part = substr($ic, 2, 2);
                    $day_part = substr($ic, 4, 2);
                    $current_year = intval(date('Y'));
                    $century = ($year_part + 2000 > $current_year) ? 1900 : 2000;
                    $year = $century + intval($year_part);
                    $lahir = "$day_part/$month_part/$year";
                }
                $graves_map[$id] = [
                    'id' => $id,
                    'nama' => $g['nama'],
                    'ic' => $g['ic'],
                    'lahir' => $lahir,
                    'mati' => $mati,
                    'zon' => $zon,
                    'gambar_kiri' => $g['gambar_kiri'] ?? '',
                    'gambar_kanan' => $g['gambar_kanan'] ?? '',
                    'gambar_penanda' => $g['gambar_penanda'] ?? '',
                    'gambar_kiri_desc' => $g['gambar_kiri_desc'] ?? '',
                    'gambar_kanan_desc' => $g['gambar_kanan_desc'] ?? '',
                    'gambar_penanda_desc' => $g['gambar_penanda_desc'] ?? ''
                ];
            }
        }
    }
} catch (Exception $e) {}

$final_graves = array_values($graves_map);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartGrave — Carian Pusara</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#edf2ee;
  --surface:#ffffff;
  --surface-alt:#f6faf7;
  --border:rgba(196,220,208,0.75);
  --border-strong:rgba(155,198,178,0.9);

  --green:#059669;
  --green-d:#064e3b;
  --green-dd:#022c22;
  --green-l:#10b981;
  --green-xl:#ecfdf5;
  --green-xxl:#d1fae5;

  --blue:#1d4ed8;
  --blue-l:#eff6ff;
  --purple:#6d28d9;
  --purple-l:#f5f3ff;

  --text:#0d1f1a;
  --muted:#587068;
  --red:#dc2626;

  /* Layered shadow system */
  --shadow-xs:0 1px 2px rgba(8,24,18,0.04);
  --shadow-sm:
    0 1px 3px rgba(8,24,18,0.04),
    0 2px 10px rgba(8,24,18,0.05);
  --shadow:
    0 2px 6px rgba(8,24,18,0.03),
    0 6px 20px rgba(8,24,18,0.06),
    0 12px 40px rgba(8,24,18,0.04);
  --shadow-md:
    0 4px 10px rgba(8,24,18,0.05),
    0 12px 36px rgba(8,24,18,0.08),
    0 24px 64px rgba(8,24,18,0.05);
  --shadow-green:
    0 2px 8px rgba(5,150,105,0.18),
    0 4px 18px rgba(5,150,105,0.12);

  --radius:20px;
  --radius-sm:12px;
  --radius-xs:8px;
  --header-bg:linear-gradient(135deg,#064e3b 0%,#043d2e 55%,#022c22 100%);
  --font-display:'Plus Jakarta Sans',sans-serif;
  --font-sans:'Inter',sans-serif;
  --font-mono:'DM Mono',monospace;
}

*{margin:0;padding:0;box-sizing:border-box}
html,body{
  height:100%;overflow:hidden;
  font-family:var(--font-sans);color:var(--text);
  background-color:var(--bg);
  background-image:url("https://www.transparenttextures.com/patterns/arabesque.png");
}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-thumb{background:rgba(5,150,105,0.22);border-radius:10px}
::-webkit-scrollbar-thumb:hover{background:rgba(5,150,105,0.38)}
::-webkit-scrollbar-track{background:transparent}

/* ── HEADER ── */
header{
  height:64px;
  background:var(--header-bg);
  border-bottom:1px solid rgba(255,255,255,0.06);
  display:flex;align-items:center;padding:0 24px;gap:16px;
  position:relative;z-index:9999;
  box-shadow:0 4px 28px rgba(0,0,0,0.22),0 1px 0 rgba(0,0,0,0.12);
}
/* Signature element: animated shimmer line at header bottom */
header::after{
  content:'';
  position:absolute;bottom:0;left:0;right:0;height:2px;
  background:linear-gradient(
    90deg,
    transparent 0%,
    rgba(52,211,153,0) 8%,
    rgba(52,211,153,0.65) 28%,
    rgba(52,211,153,1) 50%,
    rgba(52,211,153,0.65) 72%,
    rgba(52,211,153,0) 92%,
    transparent 100%
  );
  animation:headerShimmer 5s ease-in-out infinite;
}
@keyframes headerShimmer{
  0%,100%{opacity:0.45;transform:scaleX(0.82)}
  50%{opacity:1;transform:scaleX(1)}
}

.logo{display:flex;align-items:center;gap:10px;text-decoration:none;transition:opacity .2s}
.logo:hover{opacity:0.87}
.logo-icon{
  width:38px;height:38px;
  background:linear-gradient(135deg,rgba(52,211,153,0.18) 0%,rgba(255,255,255,0.08) 100%);
  border:1px solid rgba(52,211,153,0.22);
  border-radius:var(--radius-sm);
  display:flex;align-items:center;justify-content:center;font-size:20px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.14),0 2px 8px rgba(0,0,0,0.25);
}
.logo-text{font-family:var(--font-display);font-size:18px;font-weight:800;color:#fff;letter-spacing:-.5px}
.logo-text em{color:#34d399;font-style:normal}
.h-sep{width:1px;height:22px;background:rgba(255,255,255,0.13);margin:0 2px}
.h-badge{
  font-size:11.5px;font-weight:600;
  color:rgba(255,255,255,0.92);
  background:rgba(52,211,153,0.1);
  border:1px solid rgba(52,211,153,0.2);
  padding:5px 14px;border-radius:20px;
  display:flex;align-items:center;gap:6px;
  letter-spacing:.15px;
}
.h-space{flex:1}
.h-dot{
  width:7px;height:7px;border-radius:50%;background:#4ade80;
  animation:blink 2s infinite;flex-shrink:0;
  box-shadow:0 0 8px rgba(74,222,128,0.65);
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.h-status{
  font-size:12px;color:rgba(255,255,255,0.62);
  display:flex;align-items:center;gap:6px;
  font-family:var(--font-mono);letter-spacing:.45px;
}
.h-counts{display:flex;gap:6px;margin-left:12px}
.h-count{
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.1);
  padding:5px 16px;border-radius:var(--radius-sm);
  font-size:10px;font-family:var(--font-mono);
  color:rgba(255,255,255,0.52);
  text-align:center;display:flex;flex-direction:column;justify-content:center;
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.07);
  letter-spacing:.35px;
}
.h-count strong{
  display:block;font-size:15px;font-weight:800;
  color:#fff;font-family:var(--font-mono);line-height:1.2;
}

/* ── LAYOUT ── */
.layout{
  flex:1;display:grid;
  grid-template-columns:360px 1fr;
  height:calc(100vh - 64px);overflow:hidden;
}

/* ── SIDEBAR ── */
.sidebar{
  background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;
  box-shadow:4px 0 20px rgba(8,24,18,0.04),1px 0 0 var(--border);
}

.search-box{
  padding:22px 22px 18px;
  border-bottom:1px solid var(--border);
  background:var(--surface);
  box-shadow:0 2px 10px rgba(8,24,18,0.02);
}
.s-label{
  font-family:var(--font-display);
  font-size:10.5px;font-weight:850;
  letter-spacing:1.8px;text-transform:uppercase;
  color:var(--muted);margin-bottom:12px;
  display:flex;align-items:center;gap:8px;
}
.s-label::after{content:'';flex:1;height:1px;background:var(--border)}
.s-row{display:flex;gap:8px}
.s-wrap{flex:1;position:relative}
.s-ico{
  position:absolute;left:14px;top:50%;
  transform:translateY(-50%);
  color:var(--muted);font-size:13px;pointer-events:none;
}
.s-input{
  width:100%;
  background:#f5f9f6;
  border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  padding:11px 11px 11px 38px;
  font-family:inherit;font-size:13.5px;color:var(--text);outline:none;
  transition:all .22s cubic-bezier(0.4,0,0.2,1);
}
.s-input:focus{
  border-color:var(--green);background:#fff;
  box-shadow:0 0 0 3.5px rgba(5,150,105,0.12),0 2px 8px rgba(5,150,105,0.07);
}
.s-input::placeholder{color:#9baaa5}
.s-btn{
  background:linear-gradient(135deg,var(--green-d) 0%,var(--green) 100%);
  border:none;border-radius:var(--radius-sm);
  width:46px;color:#fff;font-size:15px;cursor:pointer;flex-shrink:0;
  transition:all .2s cubic-bezier(0.4,0,0.2,1);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 12px rgba(5,150,105,0.28),inset 0 1px 0 rgba(255,255,255,0.14);
}
.s-btn:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 18px rgba(5,150,105,0.38);
  filter:brightness(1.06);
}
.s-btn:active{transform:scale(.96)}

.pills{display:flex;gap:6px;margin-top:14px}
.pill{
  flex:1;text-align:center;padding:8px 0;border-radius:var(--radius-sm);
  font-size:12px;font-weight:700;cursor:pointer;
  border:1.5px solid var(--border);color:var(--muted);background:var(--surface);
  font-family:var(--font-display);letter-spacing:.25px;
  transition:all .18s cubic-bezier(0.4,0,0.2,1);
}
.pill:hover{border-color:var(--border-strong);background:var(--surface-alt);color:var(--text)}
.pill.active-all{
  background:linear-gradient(135deg,var(--green-xl) 0%,var(--green-xxl) 100%);
  color:var(--green-d);border-color:rgba(5,150,105,0.28)!important;
  box-shadow:0 2px 8px rgba(5,150,105,0.1),inset 0 1px 0 rgba(255,255,255,0.7);
}
.pill.active-a{
  background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);
  color:var(--blue);border-color:rgba(29,78,216,0.25)!important;
  box-shadow:0 2px 8px rgba(29,78,216,0.1),inset 0 1px 0 rgba(255,255,255,0.7);
}
.pill.active-b{
  background:linear-gradient(135deg,var(--purple-l) 0%,#ede9fe 100%);
  color:var(--purple);border-color:rgba(109,40,217,0.25)!important;
  box-shadow:0 2px 8px rgba(109,40,217,0.1),inset 0 1px 0 rgba(255,255,255,0.7);
}

/* GPS badge */
.gps-badge{
  display:flex;align-items:center;gap:10px;
  background:var(--surface-alt);
  border:1px solid var(--border);
  border-radius:var(--radius-sm);
  padding:10px 14px;margin-top:14px;font-size:12px;
  box-shadow:inset 0 1px 2px rgba(0,0,0,0.025);
}
.gps-dot{
  width:8px;height:8px;border-radius:50%;background:#10b981;
  animation:blink 1.5s infinite;flex-shrink:0;
  box-shadow:0 0 7px rgba(16,185,129,0.55);
}
#gpsStatus{flex:1;color:var(--muted);font-weight:600}
#userDistBadge{font-family:var(--font-mono);font-weight:700;color:var(--green-d);font-size:11px}
.sim-btn{
  background:#fff;border:1px solid rgba(5,150,105,0.28);color:var(--green-d);
  border-radius:20px;padding:4px 12px;font-size:10px;font-weight:700;
  cursor:pointer;transition:all .15s;letter-spacing:.2px;
}
.sim-btn:hover,.sim-btn.active{
  background:var(--green);color:#fff;border-color:var(--green);
  box-shadow:0 2px 8px rgba(5,150,105,0.28);
}

/* ── RESULTS ── */
.results{flex:1;overflow-y:auto;padding:16px 14px 80px;background:var(--bg)}
.r-meta{
  display:flex;justify-content:space-between;align-items:center;
  padding:0 4px 12px;
}
.r-label{
  font-family:var(--font-display);font-size:10.5px;font-weight:800;
  letter-spacing:1.6px;text-transform:uppercase;color:var(--muted);
}
.r-count{
  font-family:var(--font-mono);font-size:11px;font-weight:700;
  color:var(--green-d);background:var(--green-xl);
  border:1px solid rgba(5,150,105,0.2);
  padding:3px 12px;border-radius:20px;
  box-shadow:0 1px 4px rgba(5,150,105,0.09);
}

/* ── GRAVE CARD ── */
@keyframes cardFadeIn{
  from{opacity:0;transform:translateY(16px)}
  to{opacity:1;transform:translateY(0)}
}
.gcard{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius-sm);padding:16px;margin-bottom:10px;
  cursor:pointer;position:relative;overflow:hidden;
  box-shadow:var(--shadow-sm);
  transition:all .25s cubic-bezier(0.4,0,0.2,1);
  animation:cardFadeIn .35s cubic-bezier(0.4,0,0.2,1) both;
  will-change:transform,box-shadow;
}
.gcard:nth-child(1){animation-delay:0ms}
.gcard:nth-child(2){animation-delay:60ms}
.gcard:nth-child(3){animation-delay:120ms}
.gcard:nth-child(4){animation-delay:180ms}
.gcard:nth-child(5){animation-delay:240ms}
.gcard:nth-child(6){animation-delay:300ms}

/* Zone-tinted card backgrounds */
.gcard.za{background:linear-gradient(130deg,rgba(239,246,255,0.38) 0%,#ffffff 42%)}
.gcard.zb{background:linear-gradient(130deg,rgba(245,243,255,0.38) 0%,#ffffff 42%)}

/* Gradient left border accent */
.gcard::before{
  content:'';position:absolute;left:0;top:0;bottom:0;
  width:4px;border-radius:4px 0 0 4px;
  transition:width .2s cubic-bezier(0.4,0,0.2,1);
}
.gcard.za::before{background:linear-gradient(180deg,#60a5fa 0%,#2563eb 50%,#1d4ed8 100%)}
.gcard.zb::before{background:linear-gradient(180deg,#a78bfa 0%,#7c3aed 50%,#6d28d9 100%)}

.gcard:hover{
  transform:translateY(-3px) translateX(2px);
  box-shadow:var(--shadow);
  border-color:rgba(0,0,0,0.09);
}
.gcard:hover::before{width:5px}

.gcard.sel{
  border-color:rgba(5,150,105,0.3);
  background:linear-gradient(130deg,#f0fdf4 0%,#ffffff 55%);
  box-shadow:0 0 0 3px rgba(5,150,105,0.1),var(--shadow-sm);
  transform:translateX(3px);
}
.gcard.sel::before{
  background:linear-gradient(180deg,#34d399 0%,#059669 50%,#065f46 100%);
  width:5px;
}

.gc-top{display:flex;justify-content:space-between;margin-bottom:10px;align-items:center}
.gc-zbadge{
  font-family:var(--font-mono);font-size:9px;font-weight:800;
  padding:3px 8px;border-radius:6px;letter-spacing:.8px;text-transform:uppercase;
}
.ba{background:var(--blue-l);color:var(--blue);border:1px solid rgba(29,78,216,0.15)}
.bb{background:var(--purple-l);color:var(--purple);border:1px solid rgba(109,40,217,0.15)}
.gc-lot{
  font-family:var(--font-mono);font-size:11px;color:var(--muted);font-weight:700;
  background:#f0f4f2;padding:2px 8px;border-radius:4px;
  border:1px solid var(--border);
}
.gc-name{
  font-family:var(--font-display);font-size:15px;font-weight:800;
  margin-bottom:6px;color:var(--text);line-height:1.35;letter-spacing:-.3px;
}
.gc-meta{font-size:11.5px;color:var(--muted);font-family:var(--font-sans);line-height:1.65}
.gc-divider{height:1px;background:var(--border);margin:12px 0}
.gc-imgs{display:flex;gap:8px;margin-bottom:12px}
.gc-img-placeholder{
  width:100%;height:64px;border-radius:var(--radius-sm);
  border:1px solid var(--border);
  background-size:cover;background-position:center;
  background-color:var(--surface-alt);
  transition:transform .2s ease;
}
.gc-img-placeholder:hover{transform:scale(1.04)}
.gc-dist{
  display:flex;align-items:center;gap:6px;
  font-family:var(--font-mono);font-size:11px;
  color:var(--green);margin-bottom:12px;font-weight:700;
}
.gc-nav-btn{
  width:100%;padding:11px 14px;
  background:linear-gradient(135deg,var(--green-xl) 0%,var(--green-xxl) 100%);
  border:1.5px solid rgba(5,150,105,0.22);
  border-radius:var(--radius-sm);
  font-family:var(--font-display);font-size:12.5px;font-weight:800;color:var(--green-d);
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:all .22s cubic-bezier(0.4,0,0.2,1);letter-spacing:.2px;
}
.gc-nav-btn:hover{
  background:linear-gradient(135deg,var(--green-d) 0%,var(--green) 100%);
  color:#fff;border-color:transparent;
  box-shadow:var(--shadow-green);transform:translateY(-1px);
}
.gc-nav-btn:active{transform:scale(.97)}

/* ── EMPTY STATE ── */
.empty{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:56px 24px;
  color:var(--muted);gap:8px;text-align:center;
}
/* Override inline styles from JS with !important */
.empty i{
  display:flex !important;align-items:center !important;justify-content:center !important;
  width:72px !important;height:72px !important;
  background:linear-gradient(135deg,var(--green-xl) 0%,var(--green-xxl) 100%) !important;
  border:1.5px solid rgba(5,150,105,0.16) !important;
  border-radius:50% !important;
  color:var(--green) !important;opacity:1 !important;
  font-size:26px !important;margin-bottom:12px !important;
  box-shadow:0 4px 18px rgba(5,150,105,0.1);
}
.empty p{font-size:13px;line-height:1.65}

/* ── MAP AREA ── */
.map-area{position:relative;overflow:hidden}
#map{width:100%;height:100%}

/* Floating layers control — glassmorphism */
.map-layers-control{
  position:absolute;top:16px;left:16px;z-index:1000;
  background:rgba(255,255,255,0.9);
  backdrop-filter:blur(24px) saturate(1.8);
  -webkit-backdrop-filter:blur(24px) saturate(1.8);
  border:1px solid rgba(255,255,255,0.62);
  border-radius:30px;padding:4px;display:flex;gap:2px;
  box-shadow:
    0 8px 32px rgba(0,0,0,0.09),
    0 2px 8px rgba(0,0,0,0.05),
    inset 0 1px 0 rgba(255,255,255,0.85);
}
.layer-btn{
  padding:8px 16px;border-radius:30px;
  font-family:var(--font-display);font-size:11.5px;font-weight:700;
  cursor:pointer;border:none;background:transparent;color:var(--muted);
  display:flex;align-items:center;gap:6px;
  transition:all .2s cubic-bezier(0.4,0,0.2,1);
}
.layer-btn.active{
  background:linear-gradient(135deg,var(--green-d) 0%,var(--green) 100%);
  color:#fff;box-shadow:0 2px 8px rgba(5,150,105,0.3);
}

/* ── COMPASS ── */
.compass{
  position:absolute;top:16px;right:16px;
  width:76px;height:76px;z-index:1000;
  filter:drop-shadow(0 4px 16px rgba(0,0,0,0.12));
}

/* ── COORD BAR — glassmorphism ── */
.coord{
  position:absolute;bottom:16px;left:16px;
  background:rgba(255,255,255,0.9);
  backdrop-filter:blur(20px) saturate(1.6);
  -webkit-backdrop-filter:blur(20px) saturate(1.6);
  border:1px solid rgba(255,255,255,0.62);
  font-family:var(--font-mono);font-size:10px;color:var(--green-d);
  padding:8px 16px;border-radius:var(--radius-sm);z-index:1000;
  box-shadow:0 4px 16px rgba(0,0,0,0.07),inset 0 1px 0 rgba(255,255,255,0.85);
  font-weight:600;letter-spacing:.3px;
}

/* ── LEGEND — glassmorphism ── */
.legend{
  position:absolute;bottom:16px;right:16px;
  background:rgba(255,255,255,0.93);
  backdrop-filter:blur(24px) saturate(1.8);
  -webkit-backdrop-filter:blur(24px) saturate(1.8);
  border:1px solid rgba(255,255,255,0.62);
  border-radius:var(--radius-sm);padding:16px;z-index:1000;min-width:170px;
  box-shadow:
    0 8px 32px rgba(0,0,0,0.09),
    0 2px 6px rgba(0,0,0,0.04),
    inset 0 1px 0 rgba(255,255,255,0.85);
}
.leg-ttl{
  font-family:var(--font-display);
  font-size:9.5px;font-weight:850;letter-spacing:1.8px;
  text-transform:uppercase;color:var(--muted);margin-bottom:10px;
}
.leg-item{
  display:flex;align-items:center;gap:8px;
  font-size:12px;font-weight:600;color:var(--text);margin-bottom:8px;
}
.leg-item:last-child{margin-bottom:0}
.ld{width:14px;height:14px;border-radius:4px;flex-shrink:0}
.leg-sep{height:1px;background:var(--border);margin:10px 0}

/* ── WALKING NAV PANEL ── */
.nav-panel{
  position:absolute;bottom:0;left:0;right:0;
  background:rgba(255,255,255,0.98);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-top:1px solid var(--border);z-index:2000;
  transform:translateY(100%);
  transition:transform .42s cubic-bezier(.16,1,.3,1);
  max-height:80%;display:flex;flex-direction:column;
  box-shadow:0 -8px 32px rgba(0,0,0,0.07);
}
.nav-panel.open{transform:translateY(0)}

@media(min-width:768px){
  .nav-panel{
    bottom:24px;left:24px;right:auto;
    width:380px;height:520px;max-height:85%;
    border:1px solid var(--border);border-radius:var(--radius);
    box-shadow:var(--shadow-md);
    transform:translateY(120%);
  }
  .nav-panel.open{transform:translateY(0)}
}

.nav-hdr{
  display:flex;align-items:center;gap:12px;
  padding:16px 20px;flex-shrink:0;
  background:var(--header-bg);
  border-radius:var(--radius) var(--radius) 0 0;
}
.nav-hdr-icon{
  width:40px;height:40px;border-radius:var(--radius-sm);
  background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;flex-shrink:0;
}
.nav-hdr-info{flex:1}
.nav-hdr-sub{
  font-family:var(--font-display);font-size:9px;font-weight:850;
  letter-spacing:1.8px;text-transform:uppercase;
  color:rgba(255,255,255,0.48);margin-bottom:2px;
}
.nav-hdr-name{
  font-family:var(--font-display);font-size:14px;font-weight:800;
  color:#fff;letter-spacing:-.3px;
}
.nav-close{
  width:34px;height:34px;border-radius:var(--radius-sm);
  background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);
  color:rgba(255,255,255,0.8);font-size:13px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .2s;
}
.nav-close:hover{background:rgba(255,255,255,0.18);color:#fff}

.nav-prog{height:3px;background:rgba(16,185,129,0.08);flex-shrink:0}
.nav-prog-bar{
  height:100%;
  background:linear-gradient(90deg,var(--green-d) 0%,var(--green) 50%,#34d399 100%);
  width:0%;transition:width .3s ease;
}

.nav-eta{
  display:flex;align-items:center;background:#022c22;
  padding:16px 20px;flex-shrink:0;
  border-bottom:1px solid rgba(255,255,255,0.05);
}
.nav-eta-time{font-size:32px;font-weight:800;color:#fff;font-family:var(--font-mono);line-height:1}
.nav-eta-unit{font-size:12px;color:rgba(255,255,255,0.52);margin-left:4px;margin-top:12px;font-weight:700}
.nav-eta-sep{width:1px;height:36px;background:rgba(255,255,255,0.12);margin:0 20px}
.nav-eta-dist{font-size:18px;font-weight:800;color:#fff;font-family:var(--font-mono)}
.nav-eta-dlabel{
  font-family:var(--font-display);font-size:9px;
  color:rgba(255,255,255,0.42);font-weight:800;letter-spacing:1px;margin-top:2px;
}
.nav-eta-lot{margin-left:auto;text-align:right}
.nav-eta-lot strong{display:block;font-size:18px;font-weight:800;color:#fff;font-family:var(--font-mono)}
.nav-eta-lot span{font-family:var(--font-display);font-size:9px;color:rgba(255,255,255,0.42);font-weight:800;letter-spacing:1px}
.nav-live-dist{
  background:rgba(16,185,129,0.04);border-top:1px solid rgba(16,185,129,0.08);
  padding:10px 20px;font-size:11px;color:var(--muted);
  display:flex;align-items:center;gap:8px;flex-shrink:0;font-weight:600;
}
.nav-live-dot{
  width:8px;height:8px;border-radius:50%;background:#10b981;
  animation:blink 1.2s infinite;flex-shrink:0;
  box-shadow:0 0 6px rgba(16,185,129,0.55);
}

/* Timeline navigation layout */
.nav-steps{
  flex:1;overflow-y:auto;
  padding:20px 12px 20px 28px;position:relative;background:#f9fbfa;
}
.nav-steps::before{
  content:'';position:absolute;left:41px;top:28px;bottom:28px;
  width:2px;background:linear-gradient(180deg,var(--green) 0%,#dde8e2 100%);z-index:1;
}
.step{
  position:relative;z-index:2;display:flex;align-items:flex-start;gap:14px;
  padding:14px;border-radius:var(--radius-sm);margin-bottom:10px;
  background:var(--surface);border:1px solid var(--border);
  box-shadow:var(--shadow-xs);transition:all .2s ease;
}
.step.s-first{
  border-color:rgba(5,150,105,0.22);
  background:linear-gradient(135deg,#f0fdf4 0%,#ffffff 100%);
}
.step.s-dest{
  border-color:rgba(220,38,38,0.2);
  background:linear-gradient(135deg,#fef2f2 0%,#ffffff 100%);
}
.step.s-active{
  border-color:rgba(5,150,105,0.3);
  box-shadow:0 0 0 3px rgba(5,150,105,0.08),var(--shadow-sm);
  background:linear-gradient(135deg,#f0fdf4 0%,#ffffff 100%);
}
.step-icon{
  width:28px;height:28px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:13px;flex-shrink:0;
  background:#f0f4f2;border:2px solid #d8e4de;z-index:3;
}
.step.s-first .step-icon{
  background:var(--green);color:#fff;border-color:var(--green);
  box-shadow:0 2px 8px rgba(5,150,105,0.32);
}
.step.s-dest .step-icon{
  background:var(--red);color:#fff;border-color:var(--red);
  box-shadow:0 2px 8px rgba(220,38,38,0.3);
}
.step.s-active .step-icon{
  background:var(--green-d);color:#fff;border-color:var(--green-d);
  box-shadow:0 2px 8px rgba(5,150,105,0.28);
}
.step-body{flex:1}
.step-act{
  font-family:var(--font-display);font-size:13px;font-weight:800;
  color:var(--text);margin-bottom:3px;letter-spacing:-.2px;
}
.step-det{font-size:11px;color:var(--muted);line-height:1.5}
.step-dist-badge{
  font-family:var(--font-mono);font-size:10px;font-weight:700;
  color:var(--green-d);background:var(--green-xl);
  border:1px solid rgba(5,150,105,0.16);
  padding:3px 8px;border-radius:12px;white-space:nowrap;
  flex-shrink:0;margin-top:2px;
}

/* ── POPUP ── */
.pw{font-family:var(--font-sans);width:280px;color:var(--text)}
.ph{padding:14px 16px;display:flex;align-items:center;gap:12px}
.ph-icon{
  width:40px;height:40px;border-radius:var(--radius-sm);
  display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;
}
.ph-id{
  font-family:var(--font-mono);font-size:10px;font-weight:700;
  letter-spacing:1.2px;text-transform:uppercase;margin-bottom:2px;
}
.ph-name{
  font-family:var(--font-display);font-size:14px;font-weight:800;
  line-height:1.3;letter-spacing:-.3px;
}
.pb{padding:16px}
.pg{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.pf{
  background:#f5f9f7;border-radius:var(--radius-sm);
  padding:8px 10px;border:1px solid var(--border);
}
.pf-l{
  font-size:8px;color:var(--muted);text-transform:uppercase;
  letter-spacing:.8px;margin-bottom:3px;font-family:var(--font-mono);font-weight:700;
}
.pf-v{font-size:11px;font-weight:700;font-family:var(--font-mono);color:var(--text)}
.pgps{
  background:var(--green-xl);border:1px solid rgba(5,150,105,0.16);
  border-radius:var(--radius-sm);padding:8px 10px;margin-bottom:12px;
}
.pgps-l{
  font-size:8px;color:var(--green);text-transform:uppercase;
  letter-spacing:.8px;margin-bottom:3px;font-family:var(--font-mono);font-weight:700;
}
.pgps-v{font-size:10px;font-weight:700;font-family:var(--font-mono);color:var(--green-d)}
.p-imgs{display:flex;gap:8px;margin-bottom:12px}
.p-img-ph{
  width:100%;height:76px;border-radius:var(--radius-sm);border:1px solid var(--border);
  background-size:cover;background-position:center;background-color:var(--surface-alt);
  transition:transform .2s;
}
.p-img-ph:hover{transform:scale(1.04)}
.p-live-dist{
  display:flex;align-items:center;gap:6px;
  background:var(--green-xl);border:1px solid rgba(5,150,105,0.16);
  border-radius:var(--radius-sm);padding:8px 12px;margin-bottom:12px;
  font-size:11px;font-weight:600;
}
.pld-dot{
  width:6px;height:6px;border-radius:50%;background:#10b981;
  animation:blink 1.5s infinite;flex-shrink:0;
  box-shadow:0 0 5px rgba(16,185,129,0.55);
}
.pld-val{font-weight:700;color:var(--green-d);font-family:var(--font-mono)}
.pld-lbl{color:var(--muted)}
.pnav{
  width:100%;padding:11px 14px;
  background:linear-gradient(135deg,var(--green-d) 0%,var(--green) 100%);
  border:none;border-radius:var(--radius-sm);
  font-family:var(--font-display);font-size:12.5px;font-weight:800;color:#fff;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:all .2s cubic-bezier(0.4,0,0.2,1);
  box-shadow:0 4px 14px rgba(5,150,105,0.3),inset 0 1px 0 rgba(255,255,255,0.14);
  letter-spacing:.2px;
}
.pnav:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 20px rgba(5,150,105,0.42),inset 0 1px 0 rgba(255,255,255,0.14);
  filter:brightness(1.04);
}

/* ── MARKERS ── */
@keyframes pulse{
  0%{box-shadow:0 0 0 0 rgba(5,150,105,0.48)}
  70%{box-shadow:0 0 0 12px transparent}
  100%{box-shadow:0 0 0 0 transparent}
}
.pm{
  width:22px;height:22px;background:#10b981;border:3.5px solid #fff;border-radius:50%;
  animation:pulse 1.6s infinite;
  box-shadow:0 3px 10px rgba(5,150,105,0.38);
}
.user-loc-dot{
  width:18px;height:18px;background:#2563eb;border:3.5px solid #fff;border-radius:50%;
  box-shadow:0 0 0 4px rgba(37,99,235,0.25),0 3px 10px rgba(37,99,235,0.3);
  animation:pulse 2s infinite;
}
.walk-dot{
  width:24px;height:24px;background:#064e3b;border:3px solid #fff;border-radius:50%;
  box-shadow:0 3px 12px rgba(0,0,0,0.28);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:11px;font-weight:bold;
  transition:transform 0.1s linear;
}
</style>
<!-- AI Chatbot Assistant -->
<script src="chatbot.js" defer></script>
</head>
<body>

<header>
  <a href="#" class="logo">
    <div class="logo-icon">🕌</div>
    <div class="logo-text">Smart<em>Grave</em></div>
  </a>
  <div class="h-sep"></div>
  <div class="h-badge"><i class="fas fa-location-dot" style="font-size:10px;opacity:.8"></i> Masjid Kariah Bangi</div>
  <div class="h-space"></div>
  <div class="h-status"><div class="h-dot"></div>Sistem Aktif</div>
  <div class="h-counts">
    <div class="h-count"><strong id="headerTotalLots">440</strong>Jumlah Lot</div>
    <div class="h-count"><strong style="color:var(--green)" id="headerEmptyLots">428</strong>Kosong</div>
    <div class="h-count"><strong style="color:var(--red)" id="headerFilledLots">12</strong>Terisi</div>
  </div>
</header>

<div class="layout">
  <div class="sidebar">
    <div class="search-box">
      <div class="s-label">Carian Pusara</div>
      <div class="s-row">
        <div class="s-wrap">
          <i class="fas fa-search s-ico"></i>
          <input type="text" id="sInput" class="s-input" placeholder="Nama atau No. IC..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button class="s-btn" onclick="doSearch()"><i class="fas fa-arrow-right"></i></button>
      </div>
      <div class="pills">
        <button class="pill active-all" id="pill-all" onclick="setZone('all')">Semua</button>
        <button class="pill" id="pill-A" onclick="setZone('A')">Zon A</button>
        <button class="pill" id="pill-B" onclick="setZone('B')">Zon B</button>
      </div>
      <div class="gps-badge">
        <div class="gps-dot"></div>
        <span id="gpsStatus" style="font-size: 10px;">Mendapatkan lokasi...</span>
        <button class="sim-btn" id="simBtn" onclick="toggleGPSSimulation()">Simulasi GPS</button>
        <span id="userDistBadge"></span>
      </div>
    </div>
    <div class="results">
      <div class="r-meta">
        <span class="r-label">Rekod Ditemui</span>
        <span class="r-count" id="rCount">0</span>
      </div>
      <div id="rList">
        <div class="empty">
          <i class="fas fa-magnifying-glass-location" style="font-size: 40px; color: var(--muted); opacity: 0.3; margin-bottom: 8px;"></i>
          <p style="font-weight: 700; font-size: 13px; color: var(--text);">Carian Pusara</p>
          <p style="font-size: 11px; color: var(--muted); max-width: 240px; line-height: 1.5;">Sila masukkan nama atau No. IC arwah untuk memulakan carian navigasi.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="map-area">
    <div id="map"></div>
    
    <div class="map-layers-control">
      <button class="layer-btn active" id="layer-light" onclick="setMapLayer('light')">
        <i class="fas fa-map"></i> Peta
      </button>
      <button class="layer-btn" id="layer-satellite" onclick="setMapLayer('satellite')">
        <i class="fas fa-satellite"></i> Satelit
      </button>
    </div>

    <svg class="compass" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="47" fill="rgba(255,255,255,.97)" stroke="#dde6de" stroke-width="1.5"/>
      <polygon points="50,15 53,50 50,44 47,50" fill="#dc2626"/>
      <polygon points="50,85 53,50 50,56 47,50" fill="#9ca3af"/>
      <circle cx="50" cy="50" r="5" fill="white" stroke="#dde6de"/>
      <circle cx="50" cy="50" r="2.5" fill="#1a6e38"/>
      <text x="50" y="11" text-anchor="middle" font-size="9" font-weight="800" fill="#dc2626" font-family="DM Mono,monospace">U</text>
      <text x="50" y="95" text-anchor="middle" font-size="9" font-weight="800" fill="#9ca3af" font-family="DM Mono,monospace">S</text>
      <text x="95" y="54" text-anchor="middle" font-size="9" font-weight="800" fill="#9ca3af" font-family="DM Mono,monospace">T</text>
      <text x="5" y="54" text-anchor="middle" font-size="9" font-weight="800" fill="#9ca3af" font-family="DM Mono,monospace">B</text>
    </svg>

    <div class="coord" id="coordBar">📡 Gerakkan tetikus...</div>

    <div class="legend">
      <div class="leg-ttl">Petunjuk</div>
      <div class="leg-item"><div class="ld" style="background:rgba(37,99,235,.12);border:1.5px solid #2563eb"></div>Zon A</div>
      <div class="leg-item"><div class="ld" style="background:rgba(124,58,237,.12);border:1.5px solid #7c3aed"></div>Zon B</div>
      <div class="leg-sep"></div>
      <div class="leg-item"><div class="ld" style="background:#f0f4f0;border:1px solid #9ca3af"></div>Kosong</div>
      <div class="leg-item"><div class="ld" style="background:#fde8e8;border:1px solid #dc2626"></div>Terisi</div>
      <div class="leg-sep"></div>
      <div class="leg-item">🌳 Pokok</div>
      <div class="leg-item">🕌 Masjid</div>
      <div class="leg-item">🚪 Pintu Masuk</div>
      <div class="leg-item">🚿 Tap Air</div>
    </div>

    <!-- Walking Nav Panel -->
    <div class="nav-panel" id="navPanel">
      <div class="nav-hdr">
        <div class="nav-hdr-icon">🚶</div>
        <div class="nav-hdr-info">
          <div class="nav-hdr-sub">Navigasi Berjalan Kaki</div>
          <div class="nav-hdr-name" id="navName">—</div>
        </div>
        <button class="nav-close" onclick="closeNav()"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="nav-prog"><div class="nav-prog-bar" id="navProgBar"></div></div>
      <div class="nav-eta">
        <div>
          <div style="display:flex;align-items:baseline;gap:3px">
            <div class="nav-eta-time" id="navTime">—</div>
            <div class="nav-eta-unit">min</div>
          </div>
          <div style="font-size:9px;color:rgba(255,255,255,.45);font-family:'DM Mono',monospace;margin-top:2px">MASA BERJALAN</div>
        </div>
        <div class="nav-eta-sep"></div>
        <div>
          <div class="nav-eta-dist" id="navDist">—</div>
          <div class="nav-eta-dlabel">JARAK KE PUSARA</div>
        </div>
        <div class="nav-eta-lot">
          <strong id="navLotNum">—</strong>
          <span>NO. LOT</span>
        </div>
      </div>
      <div class="nav-live-dist" id="navLiveDist" style="display:none">
        <div class="nav-live-dot"></div>
        <span>Jarak sebenar dari anda: </span>
        <span id="navLiveDistVal" style="font-weight:700;color:var(--green);margin-left:3px">—</span>
      </div>
      <div class="nav-steps" id="navSteps"></div>
    </div>
  </div>
</div>

<script>
// ── GRAVE DATA ──
const GRAVES = <?php echo json_encode($final_graves); ?>;

// ── MAP SETUP ──
const MASJID_POS  = [2.90050, 101.77336];
const CENTER      = [2.89980, 101.77490];
const ENTRY_GATE  = [2.89992, 101.77490];

const map = L.map('map',{center:CENTER, zoom:19, zoomControl:false, attributionControl:false});

const lightTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',{
  maxZoom:22, maxNativeZoom:19
});
const satelliteTiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{
  maxZoom:22, maxNativeZoom:19,
  attribution: 'Esri World Imagery'
});

lightTiles.addTo(map);

let currentLayer = 'light';
function setMapLayer(type) {
  if (type === currentLayer) return;
  if (type === 'light') {
    map.removeLayer(satelliteTiles);
    lightTiles.addTo(map);
    document.getElementById('layer-light').classList.add('active');
    document.getElementById('layer-satellite').classList.remove('active');
  } else {
    map.removeLayer(lightTiles);
    satelliteTiles.addTo(map);
    document.getElementById('layer-satellite').classList.add('active');
    document.getElementById('layer-light').classList.remove('active');
  }
  currentLayer = type;
}

L.control.zoom({position:'topright'}).addTo(map);

map.on('mousemove',e=>{
  document.getElementById('coordBar').textContent=
    `📡 ${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`;
});

// ── LOT LAYOUT ──
// Lot dimensions (metres approx): W≈6.1m, H≈8.9m, gapX≈2.2m, gapY≈1.8m
const LOT_W  = 0.000055;
const LOT_H  = 0.000080;
const GAP_X  = 0.000020;
const GAP_Y  = 0.000016;
const STEP_X = LOT_W + GAP_X;  // 0.000075 per column
const STEP_Y = LOT_H + GAP_Y;  // 0.000096 per row
const ZONE_COLS = 11;           // columns per zone  → zone width ≈91m
const ZONE_ROWS = 20;           // rows per zone     → zone height ≈213m
// Total lots: 11 × 20 × 2 zones = 440

// ── ZONE STARTS (kawasan lapang barat masjid) ──
// Zone A kiri koridor, Zone B kanan koridor (gap ~44m)
// Lot membesar ke utara (lat meningkat) dari sempadan selatan
const ZONE_START = {
  A: {lat: 2.89800, lng: 101.77400},
  B: {lat: 2.89800, lng: 101.774975},
};

const LOT_COORDS = {};

// ── Seeded PRNG (Linear Congruential Generator) ──
function seededRandom(seed) {
  let s = seed % 2147483647;
  if (s <= 0) s += 2147483646;
  return function () {
    s = (s * 16807) % 2147483647;
    return (s - 1) / 2147483646;
  };
}

// ── GENERATE ALL LOTS — grid + jitter terhad ──
// Jitter max ±GAP*0.3 → dijamin tidak bertindih:
//   X: min sep = STEP_X − 2×(GAP_X×0.3) = 0.000063 > LOT_W 0.000055 ✓
//   Y: min sep = STEP_Y − 2×(GAP_Y×0.3) = 0.000086 > LOT_H 0.000080 ✓
function generateLots(zon) {
  const coords = {};
  const s = ZONE_START[zon];
  const rnd = seededRandom(zon === 'A' ? 31 : 67);
  let n = 1;
  for (let r = 0; r < ZONE_ROWS; r++) {
    for (let c = 0; c < ZONE_COLS; c++) {
      const id = `${zon}${String(n).padStart(3,'0')}`;
      const jx = (rnd() - 0.5) * GAP_X * 0.6;   // ±0.000006 max
      const jy = (rnd() - 0.5) * GAP_Y * 0.6;   // ±0.000005 max
      coords[id] = [
        s.lat + r * STEP_Y + LOT_H / 2 + jy,
        s.lng + c * STEP_X + LOT_W / 2 + jx,
      ];
      n++;
    }
  }
  return coords;
}

const ZONE_A_COORDS = generateLots('A');
const ZONE_B_COORDS = generateLots('B');
Object.assign(LOT_COORDS, ZONE_A_COORDS, ZONE_B_COORDS);

GRAVES.forEach(g => {
  if (LOT_COORDS[g.id]) {
    g.lat = LOT_COORDS[g.id][0];
    g.lng = LOT_COORDS[g.id][1];
  }
});

// ── ZONE BOUNDARY & LOTS ──
const ZONE_CFG = {
  A:{color:'#2563eb',fill:'rgba(37,99,235,.04)',label:'ZON A'},
  B:{color:'#7c3aed',fill:'rgba(124,58,237,.04)',label:'ZON B'},
};

function drawZoneBoundary(zon) {
  const cfg = ZONE_CFG[zon];
  const s = ZONE_START[zon];
  const totalW = ZONE_COLS * STEP_X;
  const totalH = ZONE_ROWS * STEP_Y;
  const pad = 0.00005;

  L.rectangle([
    [s.lat - pad, s.lng - pad],
    [s.lat + totalH + pad, s.lng + totalW + pad]
  ],{
    color:cfg.color,weight:2,
    fill:true,fillColor:cfg.fill,fillOpacity:1,opacity:.5
  }).addTo(map);

  // Label di bahagian selatan zon supaya tidak menindih pintu masuk
  L.marker(
    [s.lat - 0.00010, s.lng + totalW / 2],
    {icon:L.divIcon({
      html:`<div style="
        background:white;color:${cfg.color};
        font-size:10px;font-weight:800;
        padding:3px 10px;border-radius:6px;
        border:1.5px solid ${cfg.color}40;
        white-space:nowrap;font-family:'DM Mono',monospace;
        box-shadow:0 2px 6px rgba(0,0,0,.12)
      ">${cfg.label}</div>`,
      className:'',iconAnchor:[28,8]
    })}
  ).addTo(map);
}

// ── LANDMARKS ──
function drawLandmarks() {
  // Koridor tengah antara Zon A dan Zon B
  const corridorLng = (ZONE_START.A.lng + ZONE_COLS * STEP_X + ZONE_START.B.lng) / 2;
  const pathPts = [
    ENTRY_GATE,
    [ZONE_START.A.lat, corridorLng],
  ];

  // Bayang koridor
  L.polyline(pathPts,{color:'#d97706',weight:14,opacity:.15,lineCap:'round'}).addTo(map);
  // Garisan dash koridor
  L.polyline(pathPts,{color:'#d97706',weight:2,opacity:.55,dashArray:'9,7',lineCap:'round'}).addTo(map);

  [
    {lat:MASJID_POS[0],  lng:MASJID_POS[1],     icon:'🕌', label:'Masjid Kariah Bangi'},
    {lat:2.90055,        lng:101.77308,         icon:'🅿️', label:'Tempat Letak Kereta'},
    {lat:ENTRY_GATE[0],  lng:ENTRY_GATE[1],     icon:'🚪', label:'Pintu Masuk Utama'},
    // Water Hydrants (Pili Air) near the lots
    {lat:2.89950,        lng:101.77485,         icon:'🚿', label:'Pili Air (Zon A - Utara)'},
    {lat:2.89840,        lng:101.77485,         icon:'🚿', label:'Pili Air (Zon A - Selatan)'},
    {lat:2.89950,        lng:101.77502,         icon:'🚿', label:'Pili Air (Zon B - Utara)'},
    {lat:2.89840,        lng:101.77502,         icon:'🚿', label:'Pili Air (Zon B - Selatan)'},
    // Trees near the lots
    {lat:2.89990,        lng:101.77390,         icon:'🌳', label:'Pokok (Barat Laut Zon A)'},
    {lat:2.89800,        lng:101.77390,         icon:'🌳', label:'Pokok (Barat Daya Zon A)'},
    {lat:2.89990,        lng:101.77590,         icon:'🌳', label:'Pokok (Timur Laut Zon B)'},
    {lat:2.89800,        lng:101.77590,         icon:'🌳', label:'Pokok (Tenggara Zon B)'},
    {lat:2.89900,        lng:101.77385,         icon:'🌳', label:'Pokok (Barat Zon A)'},
    {lat:2.89900,        lng:101.77595,         icon:'🌳', label:'Pokok (Timur Zon B)'},
    {lat:2.89875,        lng:101.77490,         icon:'🌳', label:'Pokok Besar (Koridor Tengah)'},
  ].forEach(lm=>{
    L.marker([lm.lat,lm.lng],{
      icon:L.divIcon({
        html:`<div style="font-size:20px;filter:drop-shadow(0 2px 4px rgba(0,0,0,.2))">${lm.icon}</div>`,
        className:'',iconAnchor:[10,20]
      })
    })
    .bindTooltip(`<b style="font-family:'Plus Jakarta Sans',sans-serif;font-size:12px">${lm.label}</b>`,{direction:'top',offset:[0,-16],opacity:.97})
    .addTo(map);
  });
}

// ── DRAW ALL LOTS ──
function drawAllLots() {
  drawZoneBoundary('A');
  drawZoneBoundary('B');

  Object.entries(LOT_COORDS).forEach(([id,coord]) => {
    const occupied = GRAVES.some(g => g.id === id);
    const zone = id.startsWith('A') ? 'A' : 'B';
    const cfg = ZONE_CFG[zone];

    L.rectangle([
      [coord[0]-LOT_H/2, coord[1]-LOT_W/2],
      [coord[0]+LOT_H/2, coord[1]+LOT_W/2]
    ],{
      color: occupied ? '#dc2626' : cfg.color,
      weight: 1.2,
      fillOpacity: occupied ? 0.18 : 0.08,
      fillColor: occupied ? '#fecaca' : cfg.color,
    }).addTo(map)
    .bindTooltip(`<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:700">Lot ${id}<br>${occupied?'Terisi':'Kosong'}</div>`,{direction:'top',offset:[0,-5]});
  });
}

// ── USER GPS ──
let userLatLng = null;
let userMarker = null;

function haversineMeters(lat1,lng1,lat2,lng2) {
  const R=6371000, r=Math.PI/180;
  const dLat=(lat2-lat1)*r, dLng=(lng2-lng1)*r;
  const a=Math.sin(dLat/2)**2+Math.cos(lat1*r)*Math.cos(lat2*r)*Math.sin(dLng/2)**2;
  return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}

function fmtDist(m) {
  if(m<1000) return `${Math.round(m)} m`;
  return `${(m/1000).toFixed(1)} km`;
}

function updateUserMarker(lat,lng) {
  if(userMarker) map.removeLayer(userMarker);
  userMarker = L.marker([lat,lng],{
    icon:L.divIcon({html:`<div class="user-loc-dot"></div>`,className:'',iconAnchor:[8,8]})
  }).bindTooltip('Lokasi Anda',{direction:'top',offset:[0,-10]}).addTo(map);
}

function updateDistances() {
  if(!userLatLng) return;
  const {lat,lng} = userLatLng;
  const gateDist = haversineMeters(lat,lng,ENTRY_GATE[0],ENTRY_GATE[1]);
  document.getElementById('userDistBadge').textContent = `📏 ${fmtDist(gateDist)} dari pintu`;

  GRAVES.forEach(g=>{
    if(!g.lat) return;
    const d = haversineMeters(lat,lng,g.lat,g.lng);
    const el = document.getElementById(`dist-${g.id}`);
    if(el) el.textContent = `📍 ${fmtDist(d)} dari anda`;
  });

  const navPanel = document.getElementById('navPanel');
  if(navPanel.classList.contains('open') && _currentNavId) {
    const g = GRAVES.find(x=>x.id===_currentNavId);
    if(g&&g.lat) {
      const d = haversineMeters(lat,lng,g.lat,g.lng);
      document.getElementById('navLiveDistVal').textContent = fmtDist(d);
      document.getElementById('navLiveDist').style.display='flex';
    }
  }

  const pldEl = document.getElementById('popup-live-dist');
  if(pldEl && _currentPopupId) {
    const g = GRAVES.find(x=>x.id===_currentPopupId);
    if(g&&g.lat) {
      const d = haversineMeters(lat,lng,g.lat,g.lng);
      pldEl.textContent = fmtDist(d);
    }
  }
}

let isSimulatingGPS = false;
const SIMULATED_START = [2.90055, 101.77308]; // parking lot

function toggleGPSSimulation() {
  const btn = document.getElementById('simBtn');
  if (!isSimulatingGPS) {
    isSimulatingGPS = true;
    btn.textContent = "Henti Simulasi";
    btn.classList.add('active');
    document.getElementById('gpsStatus').textContent = 'Simulasi Lokasi';
    userLatLng = {lat: SIMULATED_START[0], lng: SIMULATED_START[1]};
    updateUserMarker(userLatLng.lat, userLatLng.lng);
    updateDistances();
  } else {
    isSimulatingGPS = false;
    btn.textContent = "Simulasi GPS";
    btn.classList.remove('active');
    document.getElementById('gpsStatus').textContent = 'Mendapatkan lokasi...';
    startGPS();
  }
}

function startGPS() {
  if (isSimulatingGPS) return;
  if(!navigator.geolocation) {
    document.getElementById('gpsStatus').textContent='GPS tidak disokong';
    return;
  }
  navigator.geolocation.watchPosition(
    pos => {
      if (isSimulatingGPS) return;
      userLatLng = {lat:pos.coords.latitude, lng:pos.coords.longitude};
      document.getElementById('gpsStatus').textContent='Lokasi aktif';
      updateUserMarker(userLatLng.lat, userLatLng.lng);
      updateDistances();
    },
    err => {
      if (isSimulatingGPS) return;
      document.getElementById('gpsStatus').textContent='GPS tidak dapat diakses';
      document.getElementById('userDistBadge').textContent='';
    },
    {enableHighAccuracy:true, maximumAge:5000, timeout:10000}
  );
}

// ── FOCUS & POPUP ──
let _marker=null, _currentPopupId=null, _currentNavId=null;

function getGraveImagesHTML(d, className = 'p-img-ph') {
  let html = '';
  if (d.gambar_kiri && d.gambar_kiri.trim() !== '') {
    const desc = (d.gambar_kiri_desc && d.gambar_kiri_desc.trim() !== '') ? d.gambar_kiri_desc : 'Batu Nisan';
    html += `<div class="p-img-container" style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;">
               <div class="${className}" style="width: 100%; height:76px; border-radius:var(--radius-sm); border: 1px solid var(--border); background-image: url('${d.gambar_kiri}'); background-size: cover; background-position: center; background-color:var(--surface-alt); transition:transform .2s;" title="${desc}"></div>
               <span style="color: #64748b; font-size: 8px; font-weight: 700; text-align: center; width: 100%; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px;">${desc}</span>
             </div>`;
  }
  if (d.gambar_kanan && d.gambar_kanan.trim() !== '') {
    const desc = (d.gambar_kanan_desc && d.gambar_kanan_desc.trim() !== '') ? d.gambar_kanan_desc : 'Kawasan Sekitar';
    html += `<div class="p-img-container" style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;">
               <div class="${className}" style="width: 100%; height:76px; border-radius:var(--radius-sm); border: 1px solid var(--border); background-image: url('${d.gambar_kanan}'); background-size: cover; background-position: center; background-color:var(--surface-alt); transition:transform .2s;" title="${desc}"></div>
               <span style="color: #64748b; font-size: 8px; font-weight: 700; text-align: center; width: 100%; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px;">${desc}</span>
             </div>`;
  }
  if (d.gambar_penanda && d.gambar_penanda.trim() !== '') {
    const desc = (d.gambar_penanda_desc && d.gambar_penanda_desc.trim() !== '') ? d.gambar_penanda_desc : 'Penanda Laluan';
    html += `<div class="p-img-container" style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;">
               <div class="${className}" style="width: 100%; height:76px; border-radius:var(--radius-sm); border: 1px solid var(--border); background-image: url('${d.gambar_penanda}'); background-size: cover; background-position: center; background-color:var(--surface-alt); transition:transform .2s;" title="${desc}"></div>
               <span style="color: #64748b; font-size: 8px; font-weight: 700; text-align: center; width: 100%; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px;">${desc}</span>
             </div>`;
  }
  if (html !== '') {
    const wrapperClass = className === 'gc-img-placeholder' ? 'gc-imgs' : 'p-imgs';
    return `<div class="${wrapperClass}">${html}</div>`;
  }
  return '';
}

function makePopup(d) {
  const isA=d.zon==='A';
  const zc=isA?'#2563eb':'#7c3aed';
  const zl=isA?'#eff6ff':'#f5f3ff';
  const liveDistHTML = userLatLng
    ? `<div class="p-live-dist"><div class="pld-dot"></div><span class="pld-lbl">Jarak dari anda:&nbsp;</span><span class="pld-val" id="popup-live-dist">${fmtDist(haversineMeters(userLatLng.lat,userLatLng.lng,d.lat,d.lng))}</span></div>`
    : `<div class="p-live-dist" style="opacity:.5"><div class="pld-dot" style="background:#aaa;animation:none"></div><span class="pld-lbl">GPS diperlukan untuk jarak sebenar</span></div>`;
  
  const imgsHTML = getGraveImagesHTML(d, 'p-img-ph');
  
  return `<div class="pw">
    <div class="ph" style="background:${zl};border-bottom:1px solid ${zc}22">
      <div class="ph-icon" style="background:${zl};border-color:${zc}30">🪦</div>
      <div>
        <div class="ph-id" style="color:${zc}">Lot ${d.id}</div>
        <div class="ph-name">${d.nama}</div>
      </div>
    </div>
    <div class="pb">
      ${imgsHTML}
      <div class="pg">
        <div class="pf"><div class="pf-l">Tarikh Lahir</div><div class="pf-v">${d.lahir}</div></div>
        <div class="pf"><div class="pf-l">Tarikh Wafat</div><div class="pf-v">${d.mati}</div></div>
        <div class="pf"><div class="pf-l">Zon</div><div class="pf-v" style="color:${zc}">Zon ${d.zon}</div></div>
      </div>
      ${liveDistHTML}
      <div class="pgps">
        <div class="pgps-l">📡 Koordinat GPS Lot</div>
        <div class="pgps-v">${d.lat.toFixed(7)}, ${d.lng.toFixed(7)}</div>
      </div>
      <button onclick="startNav('${d.id}')" class="pnav">🧭 Navigasi ke Pusara</button>
    </div>
  </div>`;
}

function focusGrave(id) {
  const d=GRAVES.find(g=>g.id===id);
  if(!d||!d.lat) return;
  _currentPopupId=id;
  if(_marker) map.removeLayer(_marker);
  map.flyTo([d.lat,d.lng],21,{duration:1.3,easeLinearity:.3});
  _marker=L.marker([d.lat,d.lng],{
    icon:L.divIcon({html:`<div class="pm"></div>`,className:'',iconAnchor:[10,10]})
  }).addTo(map).bindPopup(makePopup(d),{maxWidth:292}).openPopup();
  document.querySelectorAll('.gcard').forEach(c=>c.classList.remove('sel'));
  const card=document.getElementById(`gc-${id}`);
  if(card){card.classList.add('sel');card.scrollIntoView({behavior:'smooth',block:'nearest'});}
  if(document.getElementById('navPanel').classList.contains('open')) startNav(id);
}

// ── WALKING NAVIGATION ──
function bearing(a,b) {
  const dL=(b[1]-a[1])*Math.PI/180;
  const la1=a[0]*Math.PI/180, la2=b[0]*Math.PI/180;
  return((Math.atan2(
    Math.sin(dL)*Math.cos(la2),
    Math.cos(la1)*Math.sin(la2)-Math.sin(la1)*Math.cos(la2)*Math.cos(dL)
  )*180/Math.PI)+360)%360;
}

let _walkInterval=null,_navLineLayer=null,_arrowLayer=null,_walkMkr=null,_startMkr=null,_destMkr=null;

function startNav(id) {
  const d = GRAVES.find(g=>g.id===id);
  if(!d||!d.lat) return;
  _currentNavId=id;
  map.closePopup();

  [_navLineLayer,_arrowLayer,_walkMkr,_startMkr,_destMkr].forEach(l=>{if(l)map.removeLayer(l)});
  _navLineLayer=_arrowLayer=_walkMkr=_startMkr=_destMkr=null;
  if(_walkInterval){clearInterval(_walkInterval);_walkInterval=null;}

  const row_gap_lat = d.lat - (LOT_H + GAP_Y) / 2;
  const corridorLng = 101.77490;

  const routePts = [
    ENTRY_GATE,
    [row_gap_lat, corridorLng],
    [row_gap_lat, d.lng],
    [d.lat, d.lng]
  ];

  _navLineLayer = L.polyline(routePts,{
    color:'#1a6e38',weight:5,opacity:.9,
    dashArray:'14,9',lineCap:'round',lineJoin:'round'
  }).addTo(map);

  _startMkr = L.marker(ENTRY_GATE,{icon:L.divIcon({
    html:`<div style="background:#10b981;color:white;border:2.5px solid white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);font-weight:800;font-size:9px;font-family:'DM Mono',monospace">MULA</div>`,
    className:'',iconAnchor:[16,16]
  })}).bindTooltip('Pintu Masuk Perkuburan',{direction:'top',offset:[0,-10]}).addTo(map);

  _destMkr = L.marker([d.lat,d.lng],{icon:L.divIcon({
    html:`<div style="background:#dc2626;color:white;border:2.5px solid white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);font-weight:800;font-size:9px;font-family:'DM Mono',monospace">LOT</div>`,
    className:'',iconAnchor:[16,16]
  })}).bindTooltip(`Destinasi: Lot ${d.id}`,{direction:'top',offset:[0,-10]}).addTo(map);

  _walkMkr = L.marker(ENTRY_GATE,{
    icon:L.divIcon({
      html:`<div class="walk-dot"><i class="fas fa-location-arrow" style="transform:rotate(-45deg);font-size:9px"></i></div>`,
      className:'',iconAnchor:[11,11]
    })
  }).addTo(map);

  map.fitBounds(_navLineLayer.getBounds(),{padding:[80,80]});

  const pt0=ENTRY_GATE;
  const pt1=[row_gap_lat, corridorLng];
  const pt2=[row_gap_lat, d.lng];
  const pt3=[d.lat, d.lng];

  const dist0_1=Math.round(haversineMeters(pt0[0],pt0[1],pt1[0],pt1[1]));
  const dist1_2=Math.round(haversineMeters(pt1[0],pt1[1],pt2[0],pt2[1]));
  const dist2_3=Math.round(haversineMeters(pt2[0],pt2[1],pt3[0],pt3[1]));

  const totalDist=dist0_1+dist1_2+dist2_3;
  const walkMins=Math.max(1,Math.ceil(totalDist/65));

  const turnDir = d.lng < corridorLng ? 'kanan' : 'kiri';
  const isA = d.zon === 'A';
  const enterDir = isA ? 'kanan' : 'kiri';

  // Calculate coordinates-like grave count
  const num_id = parseInt(d.id.substring(1), 10);
  const col_idx = (num_id - 1) % ZONE_COLS;
  const row_idx = Math.floor((num_id - 1) / ZONE_COLS);
  
  let num_lots_to_walk = 0;
  let walk_direction_relative = "";
  
  if (isA) {
      num_lots_to_walk = (ZONE_COLS - 1) - col_idx; // Walking West (relative right from South-facing corridor)
      walk_direction_relative = "ke kanan (arah Barat)";
  } else {
      num_lots_to_walk = col_idx; // Walking East (relative left from South-facing corridor)
      walk_direction_relative = "ke kiri (arah Timur)";
  }

  const steps = [
    {icon:'🚪',act:'Pintu Masuk Utama',det:'Titik permulaan antara Zon A dan Zon B',dist:'',cls:'s-first'},
    {icon:'⬇️',act:'Koridor Tengah',det:`Jalan terus ke Selatan melepasi ${19 - row_idx} baris kubur`,dist:dist0_1+' m',cls:''},
    {icon:turnDir==='kiri'?'⬅️':'➡️',act:`Lorong Barisan`,det:`Belok ${turnDir} melintasi ${num_lots_to_walk} lot kubur`,dist:dist1_2+' m',cls:''},
    {icon:enterDir==='kanan'?'➡️':'⬅️',act:`Masuk ke Lot`,det:`Langkah ke lot destinasi di Barisan ke-${row_idx + 1}`,dist:dist2_3+' m',cls:''},
    {icon:'📍',act:`Destinasi Tiba`,det:`Lot ${d.id} (${d.nama}) berada di hadapan anda`,dist:totalDist+' m',cls:'s-dest'}
  ];

  document.getElementById('navName').textContent=d.nama;
  document.getElementById('navTime').textContent=walkMins;
  document.getElementById('navDist').textContent=`${totalDist} m`;
  document.getElementById('navLotNum').textContent=d.id;
  document.getElementById('navProgBar').style.width='0%';

  if(userLatLng){
    document.getElementById('navLiveDistVal').textContent=fmtDist(totalDist);
    document.getElementById('navLiveDist').style.display='flex';
  } else {
    document.getElementById('navLiveDist').style.display='none';
  }

  const stepEls = steps.map((s,i)=>`
    <div class="step ${s.cls}" id="step-${i}">
      <div class="step-icon">${s.icon}</div>
      <div class="step-body">
        <div class="step-act">${s.act}</div>
        <div class="step-det">${s.det}</div>
      </div>
      ${s.dist?`<div class="step-dist-badge">${s.dist}</div>`:''}
    </div>`).join('');

  document.getElementById('navSteps').innerHTML=stepEls;
  document.getElementById('navPanel').classList.add('open');

  const totalPts=routePts.length;
  let seg=0, t=0;

  _walkInterval=setInterval(()=>{
    t+=0.02;
    if(t>=1){t=0;seg++;}
    if(seg>=totalPts-1){
      clearInterval(_walkInterval);
      if (isSimulatingGPS) {
        userLatLng = {lat: d.lat, lng: d.lng};
        updateUserMarker(userLatLng.lat, userLatLng.lng);
        updateDistances();
      }
      // Tambah gambar kubur di bawah senarai arahan setelah tiba, tanpa memadamkan arahan sebelum ini
      if (!document.getElementById('arrival-photo-card')) {
        const arrivalCard = document.createElement('div');
        arrivalCard.id = 'arrival-photo-card';
        if (d.gambar_penanda && d.gambar_penanda.trim() !== '') {
          arrivalCard.innerHTML = `
            <div class="arrival-card" style="padding: 1.5rem 1rem; text-align: center; border-top: 1.5px dashed #059669; margin-top: 15px; animation: fadeIn 0.5s ease;">
              <div style="font-size: 13px; font-weight: 800; color: #064e3b; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                ✨ Anda Telah Sampai!
              </div>
              <p style="font-size: 10px; color: #64748b; margin-bottom: 12px;">Berikut ialah gambar panduan fizikal bagi kubur arwah:</p>
              <div style="width: 100%; height: 180px; border-radius: 12px; border: 1.5px solid #059669; background-image: url('${d.gambar_penanda}'); background-size: cover; background-position: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 10px;"></div>
              <div style="font-size: 11px; font-weight: 700; color: #0f172a;">${d.gambar_penanda_desc || 'Kubur ' + d.nama}</div>
            </div>
          `;
        } else {
          arrivalCard.innerHTML = `
            <div class="arrival-card" style="padding: 1.5rem 1rem; text-align: center; border-top: 1.5px dashed #059669; margin-top: 15px;">
              <div style="font-size: 13px; font-weight: 800; color: #064e3b; margin-bottom: 6px;">
                ✨ Anda Telah Sampai!
              </div>
              <p style="font-size: 10px; color: #64748b;">Lot ${d.id} (${d.nama}) berada di hadapan anda.</p>
            </div>
          `;
        }
        document.getElementById('navSteps').appendChild(arrivalCard);
        // Auto scroll ke bawah untuk tunjukkan gambar kubur
        document.getElementById('navSteps').scrollTop = document.getElementById('navSteps').scrollHeight;
      }
      return;
    }

    const from=routePts[seg], to=routePts[seg+1];
    const currentLatLng = [from[0]+(to[0]-from[0])*t, from[1]+(to[1]-from[1])*t];
    _walkMkr.setLatLng(currentLatLng);

    if (isSimulatingGPS) {
      userLatLng = {lat: currentLatLng[0], lng: currentLatLng[1]};
      updateUserMarker(userLatLng.lat, userLatLng.lng);
      updateDistances();
    }

    const segBrng=bearing(from,to);
    _walkMkr.setIcon(L.divIcon({
      html:`<div class="walk-dot" style="transform:rotate(${segBrng}deg)"><i class="fas fa-location-arrow" style="transform:rotate(-45deg);font-size:9px"></i></div>`,
      className:'',iconAnchor:[11,11]
    }));

    document.querySelectorAll('.step').forEach((el,i)=>{
      el.classList.remove('s-active');
      if(i!==0 && i!==steps.length-1 && i===seg+1) el.classList.add('s-active');
    });

    document.getElementById('navProgBar').style.width=((seg+t)/(totalPts-1)*100)+'%';
  },50);
}

function closeNav() {
  document.getElementById('navPanel').classList.remove('open');
  _currentNavId=null;
  [_navLineLayer,_arrowLayer,_walkMkr,_startMkr,_destMkr].forEach(l=>{if(l)map.removeLayer(l)});
  _navLineLayer=_arrowLayer=_walkMkr=_startMkr=_destMkr=null;
  if(_walkInterval){clearInterval(_walkInterval);_walkInterval=null;}
  
  if (isSimulatingGPS) {
    // Reset simulated position to start
    userLatLng = {lat: SIMULATED_START[0], lng: SIMULATED_START[1]};
    updateUserMarker(userLatLng.lat, userLatLng.lng);
    updateDistances();
  }
}

// ── SEARCH ──
let _zone='all';
function setZone(z) {
  _zone=z;
  document.querySelectorAll('.pill').forEach(p=>p.className='pill');
  const p=document.getElementById(`pill-${z}`);
  if(z==='all') p.classList.add('active-all');
  else if(z==='A') p.classList.add('active-a');
  else p.classList.add('active-b');
  doSearch();
}

function doSearch() {
  const kw=document.getElementById('sInput').value.toLowerCase().trim();
  const list=document.getElementById('rList');
  
  if (!kw) {
    list.innerHTML=`<div class="empty">
      <i class="fas fa-magnifying-glass-location" style="font-size: 40px; color: var(--muted); opacity: 0.3; margin-bottom: 8px;"></i>
      <p style="font-weight: 700; font-size: 13px; color: var(--text);">Carian Pusara</p>
      <p style="font-size: 11px; color: var(--muted); max-width: 240px; line-height: 1.5;">Sila masukkan nama atau No. IC arwah untuk memulakan carian navigasi.</p>
    </div>`;
    document.getElementById('rCount').textContent=0;
    if(_marker) map.removeLayer(_marker);
    return;
  }

  let res=GRAVES.filter(g=>g.lat);
  res=res.filter(g=>g.nama.toLowerCase().includes(kw)||g.ic.includes(kw));
  if(_zone!=='all') res=res.filter(g=>g.zon===_zone);
  
  document.getElementById('rCount').textContent=res.length;
  if(!res.length){
    list.innerHTML=`<div class="empty"><i class="fas fa-circle-xmark" style="font-size: 32px; opacity: 0.3;"></i><p style="font-size: 12px; font-weight: 600;">Tiada rekod ditemui.<br>Cuba carian lain.</p></div>`;
    return;
  }
  
  list.innerHTML=res.map(d=>{
    const distHTML=userLatLng
      ?`<div class="gc-dist" id="dist-${d.id}"><i class="fas fa-location-arrow"></i>📍 ${fmtDist(haversineMeters(userLatLng.lat,userLatLng.lng,d.lat,d.lng))} dari anda</div>`
      :`<div class="gc-dist" id="dist-${d.id}" style="opacity:.4"><i class="fas fa-location-arrow"></i>Mendapatkan jarak...</div>`;
    const gcImgsHTML = getGraveImagesHTML(d, 'gc-img-placeholder');
    return `
    <div class="gcard z${d.zon.toLowerCase()}" id="gc-${d.id}" onclick="focusGrave('${d.id}')">
      <div class="gc-top">
        <span class="gc-zbadge b${d.zon.toLowerCase()}">ZON ${d.zon}</span>
        <span class="gc-lot">${d.id}</span>
      </div>
      <div class="gc-name">${d.nama}</div>
      <div class="gc-meta">Lahir: ${d.lahir} · Wafat: ${d.mati}</div>
      ${gcImgsHTML ? `<div class="gc-divider"></div>${gcImgsHTML}` : ''}
      <div class="gc-divider"></div>
      ${distHTML}
      <button class="gc-nav-btn" onclick="event.stopPropagation();startNav('${d.id}')">
        <i class="fas fa-person-walking"></i> Navigasi ke Pusara
      </button>
    </div>`;
  }).join('');
}

document.getElementById('sInput').addEventListener('keydown',e=>{if(e.key==='Enter')doSearch();});

function updateHeaderCounts() {
  const totalCount=Object.keys(LOT_COORDS).length;
  const occupiedCount=GRAVES.filter(g=>LOT_COORDS[g.id]).length;
  const emptyCount=totalCount-occupiedCount;
  document.getElementById('headerTotalLots').textContent=totalCount;
  document.getElementById('headerEmptyLots').textContent=emptyCount;
  document.getElementById('headerFilledLots').textContent=occupiedCount;
}

// ── INIT ──
drawAllLots();
drawLandmarks();
updateHeaderCounts();
doSearch();
startGPS();

setInterval(()=>{if(userLatLng)updateDistances();},5000);
</script>
</body>
</html>
