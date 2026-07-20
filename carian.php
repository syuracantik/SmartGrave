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
        // Schema migrations for image columns and constraints are already applied.

        $stmt = $pdo->query("
            SELECT lp.no_lot AS id, lp.status_lot, j.nama_jenazah AS nama, j.no_ic AS ic, j.tarikh_wafat AS mati,
                   lp.gambar_kiri, lp.gambar_kanan, lp.gambar_penanda,
                   lp.gambar_kiri_desc, lp.gambar_kanan_desc, lp.gambar_penanda_desc
            FROM lot_pusara lp
            JOIN maklumat_jenazah j ON lp.jenazah_id = j.id
            WHERE lp.status_lot IN ('Penuh', 'Ditetapkan')
        ");
        if ($stmt) {
            $db_graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($db_graves as $g) {
                $id = $g['id'];
                $zon = substr($id, 0, 1);
                $mati = $g['mati'] ? date('d/m/Y', strtotime($g['mati'])) : '—';
                $ic = preg_replace('/[^0-9]/', '', $g['ic']);
                $lahir = '—';
                $umur = '—';
                if (strlen($ic) === 12) {
                    $year_part = substr($ic, 0, 2);
                    $month_part = substr($ic, 2, 2);
                    $day_part = substr($ic, 4, 2);
                    $current_year = intval(date('Y'));
                    $death_year = !empty($g['mati']) ? intval(date('Y', strtotime($g['mati']))) : $current_year;
                    $century = ($year_part + 2000 > $death_year) ? 1900 : 2000;
                    $year = $century + intval($year_part);
                    $lahir = "$day_part/$month_part/$year";
                    
                    // Hitung umur semasa meninggal
                    if (!empty($g['mati'])) {
                        try {
                            $birthDate = new DateTime("$year-$month_part-$day_part");
                            $deathDate = new DateTime($g['mati']);
                            $diff = $birthDate->diff($deathDate);
                            $umur = $diff->y . " Tahun";
                        } catch (Exception $e) {
                            // Fallback jika tarikh salah
                            $death_year = intval(date('Y', strtotime($g['mati'])));
                            $umur = max(0, $death_year - $year) . " Tahun";
                        }
                    }
                }
                $graves_map[$id] = [
                    'id' => $id,
                    'status_lot' => $g['status_lot'],
                    'nama' => $g['nama'],
                    'ic' => $g['ic'],
                    'lahir' => $lahir,
                    'mati' => $mati,
                    'umur' => $umur,
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
        
        $all_lots_status = [];
        $q_status = $pdo->query("SELECT no_lot, status_lot FROM lot_pusara");
        if ($q_status) {
            while($r = $q_status->fetch(PDO::FETCH_ASSOC)) {
                $all_lots_status[$r['no_lot']] = $r['status_lot'];
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
.pill.active-c{
  background:linear-gradient(135deg,#fff1f2 0%,#ffe4e6 100%);
  color:#e11d48;border-color:rgba(225,29,72,0.25)!important;
  box-shadow:0 2px 8px rgba(225,29,72,0.1),inset 0 1px 0 rgba(255,255,255,0.7);
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
.toggle-steps-btn {
  display: none !important;
}

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

.back-home-btn {
  display: none;
}
.s-ico {
  display: block;
}

.mobile-toggle-btn {
  display: none;
}

@media (max-width: 767px) {
  header {
    display: none !important; /* Hide header completely */
  }
  .layout {
    position: relative;
    display: block;
    height: 100vh; /* Take full viewport height */
  }
  .sidebar {
    position: absolute;
    top: 16px;
    left: 16px;
    right: 16px;
    z-index: 2000;
    background: transparent; /* Make container transparent */
    border: none;
    box-shadow: none;
    max-height: calc(100% - 32px - 80px);
    pointer-events: none; /* Let clicks pass through empty spaces of transparent sidebar container */
  }
  .search-box {
    background: white;
    border-radius: 24px;
    padding: 10px 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid var(--border);
    pointer-events: auto; /* Enable clicks on search box */
  }
  .s-label {
    display: none !important; /* Hide title */
  }
  .gps-badge {
    display: none !important; /* Hide GPS text badge */
  }
  .pills {
    margin-top: 8px;
    gap: 4px;
  }
  .pill {
    padding: 6px 12px;
    font-size: 11px;
    border-radius: 16px;
  }
  .results {
    background: white;
    border-radius: 24px;
    margin-top: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    border: 1px solid var(--border);
    max-height: 50vh;
    pointer-events: auto; /* Enable clicks on results */
  }
  .sidebar.minimized .results {
    display: none !important;
  }
  .sidebar.minimized .pills {
    display: none !important;
  }
  .sidebar.minimized {
    max-height: 140px;
    overflow: hidden;
  }
  .back-home-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: 16px;
    z-index: 10;
    width: 24px;
    height: 24px;
  }
  .s-ico {
    display: none !important;
  }
  .s-input {
    padding-left: 36px !important;
  }
  .map-area {
    width: 100%;
    height: 100%;
  }
  .compass {
    width: 48px;
    height: 48px;
    top: 80px;
    right: 16px;
  }
  .map-layers-control {
    top: 80px;
    left: 16px;
    border-radius: 20px;
  }
  .layer-btn {
    padding: 6px 12px;
    font-size: 10px;
  }
  .legend {
    bottom: 80px;
    right: 16px;
    padding: 10px;
    min-width: 120px;
  }
  .leg-ttl {
    font-size: 8px;
    margin-bottom: 6px;
  }
  .leg-item {
    font-size: 10px;
    margin-bottom: 4px;
  }
  .coord {
    bottom: 80px;
    left: 16px;
    padding: 6px 10px;
  }
  .mobile-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2500;
    background: linear-gradient(135deg, var(--green-d) 0%, var(--green) 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 30px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(5,150,105,0.4);
    cursor: pointer;
    transition: all 0.2s;
  }
  .mobile-toggle-btn:active {
    transform: translateX(-50%) scale(0.95);
  }
  .toggle-steps-btn {
    display: flex !important;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px 0;
    background: #022c22;
    color: rgba(255,255,255,0.7);
    border: none;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
  }
  .toggle-steps-btn:hover {
    color: white;
    background: #011c16;
  }
  
  .nav-panel:not(.expanded) .nav-steps {
    display: none !important;
  }
  .nav-panel:not(.expanded) .nav-live-dist {
    display: none !important;
  }
}
</style>
<!-- AI Chatbot Assistant -->
<script src="chatbot.js" defer></script>
</head>
<body>

<header>
  <a href="index.php" class="logo">
    <div class="logo-icon">🕌</div>
    <div class="logo-text">Smart<em>Grave</em></div>
  </a>
  <div class="h-sep"></div>
  <a href="index.php" style="color: rgba(255,255,255,0.75); text-decoration: none; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(255,255,255,0.08); border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.12); transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='rgba(255,255,255,0.75)';">
    <i class="fas fa-arrow-left"></i> Kembali ke Utama
  </a>
  <div class="h-sep"></div>
  <div class="h-badge"><i class="fas fa-location-dot" style="font-size:10px;opacity:.8"></i> Masjid Kariah Bangi</div>
  <div class="h-space"></div>
  <div class="h-status"><div class="h-dot"></div>Sistem Aktif</div>
  <div class="h-counts">
    <div class="h-count"><strong id="headerTotalLots">830</strong>Jumlah Lot</div>
    <div class="h-count"><strong style="color:var(--green)" id="headerEmptyLots">830</strong>Kosong</div>
    <div class="h-count"><strong style="color:var(--red)" id="headerFilledLots">12</strong>Terisi</div>
  </div>
</header>

<div class="layout">
  <div class="sidebar">
    <div class="search-box">
      <div class="s-label">Carian Pusara</div>
      <div class="s-row">
        <div class="s-wrap">
          <a href="index.php" class="back-home-btn"><i class="fas fa-arrow-left"></i></a>
          <i class="fas fa-search s-ico"></i>
          <input type="text" id="sInput" class="s-input" placeholder="Nama atau No. IC..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button class="s-btn" onclick="doSearch()"><i class="fas fa-arrow-right"></i></button>
      </div>
      <div class="pills">
        <button class="pill active-all" id="pill-all" onclick="setZone('all')">Semua</button>
        <button class="pill" id="pill-A" onclick="setZone('A')">Zon A</button>
        <button class="pill" id="pill-B" onclick="setZone('B')">Zon B</button>
        <button class="pill" id="pill-C" onclick="setZone('C')">Zon C</button>
      </div>
      <div class="gps-badge">
        <div class="gps-dot"></div>
        <span id="gpsStatus" style="font-size: 10px;">Mendapatkan lokasi...</span>
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
      <div class="leg-item"><div class="ld" style="background:rgba(37,99,235,.12);border:1.5px solid #2563eb"></div>Zon A (Dewasa)</div>
      <div class="leg-item"><div class="ld" style="background:rgba(124,58,237,.12);border:1.5px solid #7c3aed"></div>Zon B (Dewasa)</div>
      <div class="leg-item"><div class="ld" style="background:rgba(13,148,136,.12);border:1.5px solid #0d9488"></div>Zon C (Kanak-Kanak)</div>
      <div class="leg-item"><div class="ld" style="background:#fde8e8;border:1px solid #dc2626"></div>Lot Terisi</div>
      <div class="leg-item"><div class="ld" style="background:#3f3f46;border:1px solid #000000"></div>Tanah Rosak (Tidak Sesuai)</div>
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
      <!-- Toggle button for steps (only on mobile) -->
      <button type="button" id="toggleStepsBtn" class="toggle-steps-btn" onclick="toggleNavSteps()">
        <i class="fas fa-chevron-up"></i> Lihat Langkah
      </button>
      <div class="nav-live-dist" id="navLiveDist" style="display:none">
        <div class="nav-live-dot"></div>
        <span>Jarak sebenar dari anda: </span>
        <span id="navLiveDistVal" style="font-weight:700;color:var(--green);margin-left:3px">—</span>
      </div>
      <div class="nav-steps" id="navSteps"></div>
    </div>
  </div>
</div>

<!-- Mobile Toggle View Button -->
<button id="mobileToggleView" class="mobile-toggle-btn" onclick="toggleMobileView()">
  <i class="fas fa-list"></i> Papar Senarai
</button>

<script>
// ── GRAVE DATA ──
const GRAVES = <?php echo json_encode($final_graves); ?>;
const ALL_LOT_STATUS = <?php echo json_encode($all_lots_status ?? []); ?>;

// ── MAP SETUP ──
const MASJID_POS  = [2.90061, 101.78549];
const CENTER      = [2.89966, 101.77551];
const ENTRY_GATE  = [2.90016, 101.77537];

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
const LOT_W  = 0.000025;
const LOT_H  = 0.000042;
const GAP_X  = 0.000008;
const GAP_Y  = 0.000008;
const STEP_X = LOT_W + GAP_X;  // 0.000033 per column
const STEP_Y = LOT_H + GAP_Y;  // 0.000050 per row
const ZONE_COLS = 11;
const ZONE_ROWS = 26;

// ── ZONE STARTS (kawasan lapang barat masjid) ──
const ZONE_START = {
  A: {lat: 2.89886, lng: 101.77490},
  B: {lat: 2.89886, lng: 101.77542},
  C: {lat: 2.89992, lng: 101.77542}, // Zon C di atas Zon B
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
function generateLots(zon) {
  const coords = {};
  const s = ZONE_START[zon];
  const rnd = seededRandom(zon === 'A' ? 31 : (zon === 'B' ? 67 : 89));
  let n = 1;
  const rows = zon === 'C' ? 5 : (zon === 'B' ? 21 : ZONE_ROWS);
  const cols = zon === 'C' ? 27 : (zon === 'B' ? 17 : 13);
  const step_x = zon === 'C' ? 0.000021 : STEP_X;
  const step_y = zon === 'C' ? 0.000033 : STEP_Y;
  const lot_w = zon === 'C' ? 0.000016 : LOT_W;
  const lot_h = zon === 'C' ? 0.000028 : LOT_H;
  const gap_x = zon === 'C' ? 0.000005 : GAP_X;
  const gap_y = zon === 'C' ? 0.000005 : GAP_Y;
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const id = `${zon}${String(n).padStart(3,'0')}`;
      const jx = (rnd() - 0.5) * gap_x * 0.6;
      const jy = (rnd() - 0.5) * gap_y * 0.6;
      coords[id] = [
        s.lat + r * step_y + lot_h / 2 + jy,
        s.lng + c * step_x + lot_w / 2 + jx,
      ];
      n++;
    }
  }
  return coords;
}

const ZONE_A_COORDS = generateLots('A');
const ZONE_B_COORDS = generateLots('B');
const ZONE_C_COORDS = generateLots('C');
Object.assign(LOT_COORDS, ZONE_A_COORDS, ZONE_B_COORDS, ZONE_C_COORDS);

GRAVES.forEach(g => {
  if (LOT_COORDS[g.id]) {
    g.lat = LOT_COORDS[g.id][0];
    g.lng = LOT_COORDS[g.id][1];
  }
});

// ── ZONE BOUNDARY & LOTS ──
const ZONE_CFG = {
  A:{color:'#2563eb',fill:'rgba(37,99,235,.04)',label:'ZON A (DEWASA)'},
  B:{color:'#7c3aed',fill:'rgba(124,58,237,.04)',label:'ZON B (DEWASA)'},
  C:{color:'#0d9488',fill:'rgba(13,148,136,.04)',label:'ZON C (KANAK-KANAK)'},
};

function drawZoneBoundary(zon) {
  const cfg = ZONE_CFG[zon];
  const s = ZONE_START[zon];
  const cols = zon === 'C' ? 27 : (zon === 'B' ? 17 : 13);
  const step_x = zon === 'C' ? 0.000021 : STEP_X;
  const totalW = cols * step_x;
  const totalH = zon === 'C' ? 5 * 0.000033 : (zon === 'B' ? 21 * STEP_Y : ZONE_ROWS * STEP_Y);
  const pad = 0.00003;

  L.rectangle([
    [s.lat - pad, s.lng - pad],
    [s.lat + totalH + pad, s.lng + totalW + pad]
  ],{
    color:cfg.color,weight:2,
    fill:true,fillColor:cfg.fill,fillOpacity:1,opacity:.5
  }).addTo(map);

  // Label: Letak label C di utara (atas) dan label B di selatan (bawah) supaya tidak bertindih
  const labelLat = zon === 'C' ? s.lat + totalH + 0.00004 : s.lat - 0.00004;
  L.marker(
    [labelLat, s.lng + totalW / 2],
    {icon:L.divIcon({
      html:`<div style="
        background:white;color:${cfg.color};
        font-size:10px;font-weight:800;
        padding:4px 10px;border-radius:6px;
        border:1.5px solid ${cfg.color}40;
        white-space:nowrap;font-family:'DM Mono',monospace;
        box-shadow:0 2px 6px rgba(0,0,0,.12);
        transform: translate(-50%, -50%);
        display: inline-block;
      ">${cfg.label}</div>`,
      className:'',
      iconSize: null,
      iconAnchor:[0,0]
    })}
  ).addTo(map);
}

// ── LANDMARKS ──
function drawLandmarks() {
  // Koridor tengah antara Zon A dan Zon B/C
  const corridorLng = (ZONE_START.A.lng + 13 * STEP_X + ZONE_START.B.lng) / 2;
  const pathPts = [
    ENTRY_GATE,
    [ZONE_START.A.lat, corridorLng],
  ];

  // Garisan dash koridor tunggal
  L.polyline(pathPts,{color:'#d97706',weight:3,opacity:.7,dashArray:'7,5',lineCap:'round'}).addTo(map);

  [
    {lat:MASJID_POS[0],  lng:MASJID_POS[1],     icon:'🕌', label:'Masjid Kariah Bangi'},
    {lat:2.90025,        lng:101.77520,         icon:'🅿️', label:'Tempat Letak Kereta'},
    {lat:ENTRY_GATE[0],  lng:ENTRY_GATE[1],     icon:'🚪', label:'Pintu Masuk Utama'},
    // Water Hydrants (Pili Air) near the lots
    {lat:2.89986,        lng:101.77486,         icon:'🚿', label:'Pili Air (Zon A - Barat)'},
    {lat:2.89936,        lng:101.77486,         icon:'🚿', label:'Pili Air (Zon A - Barat)'},
    {lat:2.89986,        lng:101.77601,         icon:'🚿', label:'Pili Air (Zon B - Timur)'},
    {lat:2.89936,        lng:101.77601,         icon:'🚿', label:'Pili Air (Zon B - Timur)'},
    // Trees near the lots
    {lat:2.90016,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Laut Zon A)'},
    {lat:2.89916,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Daya Zon A)'},
    {lat:2.90016,        lng:101.77601,         icon:'🌳', label:'Pokok (Timur Laut Zon B/C)'},
    {lat:2.89916,        lng:101.77601,         icon:'🌳', label:'Pokok (Tenggara Zon B)'},
    {lat:2.89966,        lng:101.77486,         icon:'🌳', label:'Pokok (Barat Zon A)'},
    {lat:2.89966,        lng:101.77601,         icon:'🌳', label:'Pokok (Timur Zon B)'},
    {lat:2.89966,        lng:corridorLng,       icon:'🌳', label:'Pokok Besar (Koridor Tengah)'},
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
  drawZoneBoundary('C');

  Object.entries(LOT_COORDS).forEach(([id,coord]) => {
    const status = ALL_LOT_STATUS[id] || 'Tersedia';
    const occupied = GRAVES.find(g => g.id === id);
    const zone = id.startsWith('A') ? 'A' : (id.startsWith('B') ? 'B' : 'C');
    const cfg = ZONE_CFG[zone];
    const lot_w = zone === 'C' ? 0.000016 : LOT_W;
    const lot_h = zone === 'C' ? 0.000028 : LOT_H;

    let lotColor = cfg.color;
    let lotFill = cfg.color;
    let lotOpacity = 0.08;
    let tooltipText = `Lot ${id} (${cfg.label})<br><b>Kosong</b>`;

    if (status === 'Penuh' || status === 'Ditetapkan') {
      lotColor = '#dc2626';
      lotFill = '#fecaca';
      lotOpacity = 0.35;
      tooltipText = `Lot ${id}<br><b style="color:#dc2626">Lot Terisi</b>`;
    } else if (status === 'Mendap') {
      lotColor = '#000000';
      lotFill = '#3f3f46';
      lotOpacity = 0.85;
      tooltipText = `Lot ${id}<br><b style="color:#ef4444">🚫 TIDAK BOLEH DIGUNAKAN (Tanah Mendap)</b>`;
    } else if (status === 'Tidak Sesuai') {
      lotColor = '#000000';
      lotFill = '#3f3f46';
      lotOpacity = 0.85;
      tooltipText = `Lot ${id}<br><b style="color:#ef4444">🚫 KAWASAN TIDAK SESUAI (Berbatu/Keras)</b>`;
    }

    L.rectangle([
      [coord[0]-lot_h/2, coord[1]-lot_w/2],
      [coord[0]+lot_h/2, coord[1]+lot_w/2]
    ],{
      color: lotColor,
      weight: 1.2,
      fillOpacity: lotOpacity,
      fillColor: lotFill,
    }).addTo(map)
    .bindTooltip(`<div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:700">${tooltipText}</div>`,{direction:'top',offset:[0,-5]});
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
const SIMULATED_START = [2.90025, 101.77520]; // parking lot dekat kubur

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
    : '';
  
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
        <div class="pf"><div class="pf-l">Umur (Wafat)</div><div class="pf-v">${d.umur}</div></div>
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

  if (window.innerWidth < 768) {
    document.querySelector('.sidebar').classList.add('minimized');
    const toggleBtn = document.getElementById('mobileToggleView');
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-list"></i> Papar Senarai';
    }
  }
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
  startGPS();
  const d = GRAVES.find(g=>g.id===id);
  if(!d||!d.lat) return;
  _currentNavId=id;
  map.closePopup();

  const toggleBtn = document.getElementById('mobileToggleView');
  if (toggleBtn) toggleBtn.style.display = 'none';

  if (window.innerWidth < 768) {
    document.querySelector('.sidebar').classList.add('minimized');
  }

  [_navLineLayer,_arrowLayer,_walkMkr,_startMkr,_destMkr].forEach(l=>{if(l)map.removeLayer(l)});
  _navLineLayer=_arrowLayer=_walkMkr=_startMkr=_destMkr=null;
  if(_walkInterval){clearInterval(_walkInterval);_walkInterval=null;}

  const isC = d.id.startsWith('C');
  const lot_h = isC ? 0.000028 : LOT_H;
  const gap_y = isC ? 0.000005 : GAP_Y;
  const row_gap_lat = d.lat - (lot_h + gap_y) / 2;
  const corridorLng = (ZONE_START.A.lng + 13 * STEP_X + ZONE_START.B.lng) / 2;

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

  // Calculate distance segments dynamically
  let totalDist = 0;
  const steps = [];

  const dist1 = Math.round(haversineMeters(ENTRY_GATE[0],ENTRY_GATE[1],row_gap_lat,corridorLng));
  totalDist += dist1;

  // Row calculation for display details
  const num_id = parseInt(d.id.substring(1), 10);
  let z_cols = 13;
  let rows_total = ZONE_ROWS; // 26
  if (d.zon === 'B') {
      z_cols = 17;
      rows_total = 21;
  } else if (d.zon === 'C') {
      z_cols = 27;
      rows_total = 5;
  }
  const col_idx = (num_id - 1) % z_cols;
  const row_idx = Math.floor((num_id - 1) / z_cols);

  let rows_passed = rows_total - 1 - row_idx;
  if (d.zon === 'B') {
      // Add the 5 rows of Zone C stacked above Zone B on the East side
      rows_passed += 5;
  }

  let det1 = `Jalan terus ke Selatan melepasi ${rows_passed} baris kubur`;
  if (rows_passed === 0) {
      det1 = `Jalan terus ke Selatan dan bersedia untuk belok terus ke barisan pertama`;
  }

  steps.push({
    icon: '🚪',
    act: 'Pintu Masuk Utama',
    det: det1,
    dist: dist1 + ' m',
    cls: 's-first'
  });

  const dist2 = Math.round(haversineMeters(row_gap_lat,corridorLng,row_gap_lat,d.lng));
  const dist3 = Math.round(haversineMeters(row_gap_lat,d.lng,d.lat,d.lng));
  const row_walk_dist = dist2 + dist3;
  totalDist = dist1 + row_walk_dist;
  
  // Walking south down the corridor, Zone A (West) is on the RIGHT (Kanan), Zone B/C (East) is on the LEFT (Kiri)
  const turnDir = d.lng < corridorLng ? 'kanan' : 'kiri';
  let num_lots_to_walk = 0;
  if (d.zon === 'A') {
      num_lots_to_walk = (z_cols - 1) - col_idx;
  } else {
      num_lots_to_walk = col_idx;
  }

  steps.push({
    icon: turnDir === 'kiri' ? '⬅️' : '➡️',
    act: 'Belok ' + (turnDir === 'kiri' ? 'Kiri' : 'Kanan'),
    det: `Belok masuk ke lorong barisan melintasi ${num_lots_to_walk} lot kubur`,
    dist: row_walk_dist + ' m',
    cls: ''
  });

  // Grave is on the Right (Kanan) for Zone A (facing West), Left (Kiri) for Zone B/C (facing East)
  const sideDir = d.zon === 'A' ? 'kanan' : 'kiri';

  steps.push({
    icon: '📍',
    act: 'Destinasi Tiba',
    det: `Lot ${d.id} (${d.nama}) berada di sebelah ${sideDir} anda`,
    dist: totalDist + ' m',
    cls: 's-dest'
  });

  const walkMins=Math.max(1,Math.ceil(totalDist/65));

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
  
  const toggleBtn = document.getElementById('mobileToggleView');
  if (toggleBtn && window.innerWidth < 768) toggleBtn.style.display = 'flex';

  const panel = document.getElementById('navPanel');
  if (panel) panel.classList.remove('expanded');
  const stepsBtn = document.getElementById('toggleStepsBtn');
  if (stepsBtn) stepsBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Lihat Langkah';

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
  else if(z==='B') p.classList.add('active-b');
  else p.classList.add('active-c');
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
      :'';
    const gcImgsHTML = getGraveImagesHTML(d, 'gc-img-placeholder');
    return `
    <div class="gcard z${d.zon.toLowerCase()}" id="gc-${d.id}" onclick="focusGrave('${d.id}')">
      <div class="gc-top">
        <span class="gc-zbadge b${d.zon.toLowerCase()}">ZON ${d.zon}</span>
        <span class="gc-lot">${d.id}</span>
      </div>
      <div class="gc-name">${d.nama}</div>
      <div class="gc-meta">Lahir: ${d.lahir} · Wafat: ${d.mati} · Umur: ${d.umur}</div>
      ${gcImgsHTML ? `<div class="gc-divider"></div>${gcImgsHTML}` : ''}
      <div class="gc-divider"></div>
      ${distHTML}
      <button class="gc-nav-btn" onclick="event.stopPropagation();startNav('${d.id}')">
        <i class="fas fa-person-walking"></i> Navigasi ke Pusara
      </button>
    </div>`;
  }).join('');
  if (window.innerWidth < 768) {
    document.querySelector('.sidebar').classList.remove('minimized');
    const toggleBtn = document.getElementById('mobileToggleView');
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-map"></i> Papar Peta';
    }
  }
}

function toggleMobileView() {
  const sidebar = document.querySelector('.sidebar');
  const btn = document.getElementById('mobileToggleView');
  if (sidebar.classList.contains('minimized')) {
    sidebar.classList.remove('minimized');
    btn.innerHTML = '<i class="fas fa-map"></i> Papar Peta';
  } else {
    sidebar.classList.add('minimized');
    btn.innerHTML = '<i class="fas fa-list"></i> Papar Senarai';
  }
}

function toggleNavSteps() {
  const panel = document.getElementById('navPanel');
  const btn = document.getElementById('toggleStepsBtn');
  if (panel.classList.contains('expanded')) {
    panel.classList.remove('expanded');
    btn.innerHTML = '<i class="fas fa-chevron-up"></i> Lihat Langkah';
  } else {
    panel.classList.add('expanded');
    btn.innerHTML = '<i class="fas fa-chevron-down"></i> Sembunyi Langkah';
  }
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

setInterval(()=>{if(userLatLng)updateDistances();},5000);
</script>
</body>
</html>