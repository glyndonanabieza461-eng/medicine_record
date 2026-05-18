<?php
include 'db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE mname LIKE ? OR category LIKE ? OR supplier LIKE ? ORDER BY id DESC");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");
}

$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;

$total_items = count($rows);
$total_qty   = array_sum(array_column($rows, 'quantity'));
$total_value = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));

function categoryIcon(string $cat): string {
    $cat = strtolower($cat);
    if (str_contains($cat, 'analg') || str_contains($cat, 'pain'))   return '🩹';
    if (str_contains($cat, 'antibi'))                                  return '🦠';
    if (str_contains($cat, 'vitamin') || str_contains($cat, 'suppl')) return '💊';
    if (str_contains($cat, 'antih') || str_contains($cat, 'allerg'))  return '🤧';
    if (str_contains($cat, 'cardio') || str_contains($cat, 'heart'))  return '❤️';
    if (str_contains($cat, 'diab'))                                    return '🩸';
    if (str_contains($cat, 'antac') || str_contains($cat, 'gastro'))  return '🫃';
    return '💉';
}

function expiryStatus(string $date): array {
    if (empty($date)) return ['ok', ''];
    $diff = (strtotime($date) - time()) / 86400;
    if ($diff < 0)  return ['expired', 'Expired'];
    if ($diff < 30) return ['near',    'Expiring soon'];
    if ($diff < 90) return ['caution', '< 3 months'];
    return ['ok', ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MedInventory — System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ════════════════════════════════════════════════════
   MIDNIGHT AURORA — Animated mesh gradient bg,
   deep teal-indigo-violet palette, frosted glass UI
════════════════════════════════════════════════════ */
:root {
    --bg-base:   #060810;
    --glass:     rgba(255,255,255,.042);
    --glass-md:  rgba(255,255,255,.07);
    --glass-hi:  rgba(255,255,255,.11);
    --border:    rgba(255,255,255,.08);
    --border-hi: rgba(255,255,255,.18);

    /* Aurora palette */
    --teal:    #2dd4bf;
    --teal-dk: #0f766e;
    --violet:  #a78bfa;
    --indigo:  #6366f1;
    --rose:    #fb7185;
    --amber:   #fbbf24;
    --emerald: #34d399;

    /* Text */
    --ink:  #f1f0ee;
    --ink2: #94918a;
    --ink3: #52504c;

    /* Danger */
    --rust:    #ef4444;
    --rust-bg: rgba(239,68,68,.12);

    --r:  14px;
    --r2: 9px;
    --r3: 6px;

    --shadow: 0 8px 32px rgba(0,0,0,.5), 0 2px 0 rgba(255,255,255,.04);
    --glow-t: 0 0 40px rgba(45,212,191,.18);
    --glow-v: 0 0 40px rgba(167,139,250,.15);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
    font-family: 'Jost', sans-serif;
    background: var(--bg-base);
    color: var(--ink);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ═══════════ ANIMATED AURORA BACKGROUND ═══════════ */
.aurora-bg {
    position: fixed; inset: 0; z-index: 0;
    overflow: hidden; pointer-events: none;
}

/* Rotating mesh blobs */
.aurora-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0;
    animation: blobIn .8s ease forwards;
}
.blob-1 {
    width: 55vw; height: 55vw;
    background: radial-gradient(circle, rgba(99,102,241,.45) 0%, rgba(99,102,241,.0) 70%);
    top: -15%; left: -10%;
    animation: blobIn .6s ease forwards, driftA 18s ease-in-out infinite alternate;
    animation-delay: 0s, .6s;
}
.blob-2 {
    width: 45vw; height: 45vw;
    background: radial-gradient(circle, rgba(45,212,191,.4) 0%, rgba(45,212,191,.0) 70%);
    top: 10%; right: -8%;
    animation: blobIn .7s .1s ease forwards, driftB 22s ease-in-out infinite alternate;
    animation-delay: .1s, .8s;
}
.blob-3 {
    width: 40vw; height: 40vw;
    background: radial-gradient(circle, rgba(167,139,250,.35) 0%, rgba(167,139,250,.0) 70%);
    bottom: -5%; left: 25%;
    animation: blobIn .7s .2s ease forwards, driftC 26s ease-in-out infinite alternate;
    animation-delay: .2s, .9s;
}
.blob-4 {
    width: 30vw; height: 30vw;
    background: radial-gradient(circle, rgba(251,191,36,.22) 0%, rgba(251,191,36,.0) 70%);
    bottom: 20%; right: 5%;
    animation: blobIn .8s .3s ease forwards, driftD 20s ease-in-out infinite alternate;
    animation-delay: .3s, 1.1s;
}
.blob-5 {
    width: 35vw; height: 35vw;
    background: radial-gradient(circle, rgba(52,211,153,.2) 0%, rgba(52,211,153,.0) 70%);
    top: 50%; left: 35%;
    animation: blobIn .8s .4s ease forwards, driftE 30s ease-in-out infinite alternate;
    animation-delay: .4s, 1.2s;
}

@keyframes blobIn { to { opacity: 1; } }
@keyframes driftA { from { transform: translate(0,0) scale(1);    } to { transform: translate(6vw, 4vh) scale(1.12); } }
@keyframes driftB { from { transform: translate(0,0) scale(1);    } to { transform: translate(-5vw, 6vh) scale(.9); } }
@keyframes driftC { from { transform: translate(0,0) scale(1);    } to { transform: translate(3vw,-5vh) scale(1.08); } }
@keyframes driftD { from { transform: translate(0,0) scale(1);    } to { transform: translate(-4vw,-3vh) scale(1.1); } }
@keyframes driftE { from { transform: translate(0,0) scale(1);    } to { transform: translate(5vw, 3vh) scale(.92); } }

/* Noise grain overlay */
.aurora-bg::after {
    content: '';
    position: absolute; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    opacity: .6; pointer-events: none;
}

/* Scanline shimmer */
.aurora-bg::before {
    content: '';
    position: absolute; inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(255,255,255,.008) 2px,
        rgba(255,255,255,.008) 4px
    );
    pointer-events: none;
}

/* ═══════════ NAV ═══════════ */
.nav {
    position: sticky; top: 0; z-index: 200;
    height: 64px;
    background: rgba(6,8,16,.75);
    backdrop-filter: blur(24px) saturate(1.8);
    border-bottom: 1px solid var(--border);
    padding: 0 2.5rem;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 1px 0 rgba(99,102,241,.25), 0 4px 32px rgba(0,0,0,.5);
}

/* Animated top border */
.nav::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1.5px;
    background: linear-gradient(90deg,
        transparent 0%,
        var(--indigo) 20%,
        var(--teal) 50%,
        var(--violet) 80%,
        transparent 100%
    );
    animation: shimmerLine 4s ease-in-out infinite;
}
@keyframes shimmerLine {
    0%,100% { opacity: .6; background-position: 0% 0%; }
    50%      { opacity: 1; }
}

.brand { display: flex; align-items: center; gap: .9rem; text-decoration: none; }
.brand-mark {
    width: 36px; height: 36px; border-radius: 10px;
    background: linear-gradient(145deg, rgba(99,102,241,.6), rgba(45,212,191,.4));
    border: 1px solid rgba(99,102,241,.5);
    display: grid; place-items: center; font-size: 1rem; flex-shrink: 0;
    box-shadow: var(--glow-v), inset 0 1px 0 rgba(255,255,255,.12);
    transition: box-shadow .3s;
}
.brand:hover .brand-mark { box-shadow: var(--glow-t), inset 0 1px 0 rgba(255,255,255,.15); }
.brand-label {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem; font-weight: 700;
    color: var(--ink); letter-spacing: .01em;
}
.brand-sub {
    font-family: 'DM Mono', monospace; font-size: .58rem;
    color: var(--teal); letter-spacing: .2em; text-transform: uppercase;
    margin-top: 2px; display: block;
}

.nav-mid { flex: 1; max-width: 400px; margin: 0 2rem; }
.search-box { position: relative; }
.search-box::before {
    content: '⌕';
    position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
    color: var(--ink3); font-size: .95rem; pointer-events: none; z-index: 1;
}
#searchInput {
    width: 100%; padding: .58rem 1rem .58rem 2.5rem;
    background: var(--glass); border: 1px solid var(--border);
    border-radius: var(--r2); color: var(--ink);
    font-family: 'Jost', sans-serif; font-size: .875rem; outline: none;
    backdrop-filter: blur(8px);
    transition: border-color .25s, box-shadow .25s, background .25s;
}
#searchInput:focus {
    background: var(--glass-md); border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(45,212,191,.12), var(--glow-t);
}
#searchInput::placeholder { color: var(--ink3); }

.btn-add {
    display: inline-flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg, var(--indigo), var(--teal-dk));
    color: #fff; font-family: 'Jost', sans-serif; font-weight: 600; font-size: .85rem;
    padding: .55rem 1.3rem; border-radius: var(--r2); text-decoration: none;
    border: 1px solid rgba(99,102,241,.4);
    box-shadow: var(--glow-v), inset 0 1px 0 rgba(255,255,255,.12);
    transition: all .22s; white-space: nowrap;
    position: relative; overflow: hidden;
}
.btn-add::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,.08), transparent);
    opacity: 0; transition: opacity .2s;
}
.btn-add:hover { transform: translateY(-1px); box-shadow: 0 0 50px rgba(99,102,241,.35), inset 0 1px 0 rgba(255,255,255,.2); }
.btn-add:hover::before { opacity: 1; }

/* ═══════════ MAIN ═══════════ */
.main {
    position: relative; z-index: 1;
    max-width: 1520px; margin: 0 auto; padding: 2.5rem 2.5rem 4rem;
}

/* PAGE HEADER */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 2.25rem; padding-bottom: 1.75rem;
    border-bottom: 1px solid var(--border);
    animation: fadeUp .7s cubic-bezier(.22,1,.36,1) both;
}
.page-eyebrow {
    font-family: 'DM Mono', monospace; font-size: .6rem;
    color: var(--teal); letter-spacing: .25em; text-transform: uppercase; margin-bottom: .5rem;
    display: flex; align-items: center; gap: .5rem;
}
.page-eyebrow::before {
    content: '';
    width: 20px; height: 1px; background: var(--teal); display: inline-block;
}
.page-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2.6rem; font-weight: 700;
    color: var(--ink); line-height: 1; letter-spacing: -.01em;
}
.page-title em { font-style: italic; color: var(--teal); }
.page-date {
    font-family: 'DM Mono', monospace; font-size: .65rem;
    color: var(--ink3); letter-spacing: .06em; text-align: right; line-height: 1.6;
}
.page-date span { display: block; color: var(--ink2); font-size: .72rem; margin-bottom: 2px; }

/* ═══════════ STATS ═══════════ */
.stats {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 1.25rem; margin-bottom: 2.5rem;
}

.stat-card {
    position: relative; overflow: hidden;
    background: var(--glass);
    backdrop-filter: blur(20px) saturate(1.5);
    border: 1px solid var(--border);
    border-radius: var(--r); padding: 1.6rem 1.8rem;
    box-shadow: var(--shadow);
    animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both;
    transition: transform .2s, border-color .3s, box-shadow .3s;
    cursor: default;
}
.stat-card::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--border-hi), transparent);
}
/* Unique glow per card */
.stat-card:nth-child(1) { animation-delay: .08s; }
.stat-card:nth-child(1)::after {
    content: ''; position: absolute; top: -40%; left: -20%;
    width: 80%; height: 80%;
    background: radial-gradient(circle, rgba(99,102,241,.12) 0%, transparent 70%);
    pointer-events: none;
}
.stat-card:nth-child(2) { animation-delay: .14s; }
.stat-card:nth-child(2)::after {
    content: ''; position: absolute; top: -40%; right: -20%;
    width: 80%; height: 80%;
    background: radial-gradient(circle, rgba(45,212,191,.1) 0%, transparent 70%);
    pointer-events: none;
}
.stat-card:nth-child(3) { animation-delay: .20s; }
.stat-card:nth-child(3)::after {
    content: ''; position: absolute; bottom: -40%; right: -10%;
    width: 80%; height: 80%;
    background: radial-gradient(circle, rgba(251,191,36,.1) 0%, transparent 70%);
    pointer-events: none;
}
.stat-card:hover { transform: translateY(-3px); border-color: var(--border-hi); }
.stat-card:nth-child(1):hover { box-shadow: var(--shadow), var(--glow-v); }
.stat-card:nth-child(2):hover { box-shadow: var(--shadow), var(--glow-t); }
.stat-card:nth-child(3):hover { box-shadow: var(--shadow), 0 0 40px rgba(251,191,36,.15); }

.stat-num {
    font-family: 'Cormorant Garamond', serif;
    font-size: 3rem; font-weight: 700; line-height: 1;
    letter-spacing: -.02em; margin-bottom: .45rem;
    color: var(--ink);
}
.stat-num.c-teal   { color: var(--teal); }
.stat-num.c-amber  { color: var(--amber); }

.stat-label {
    font-family: 'DM Mono', monospace; font-size: .58rem;
    color: var(--ink3); letter-spacing: .18em; text-transform: uppercase;
}
.stat-icon {
    position: absolute; right: 1.5rem; bottom: 1.2rem;
    font-size: 2.8rem; opacity: .07; user-select: none;
    pointer-events: none;
}

/* ═══════════ TOOLBAR ═══════════ */
.toolbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem;
    animation: fadeUp .5s .28s cubic-bezier(.22,1,.36,1) both;
}
.tbl-heading {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.15rem; font-weight: 600; color: var(--ink);
    display: flex; align-items: center; gap: .75rem;
}
.rec-pill {
    font-family: 'DM Mono', monospace; font-size: .6rem;
    background: rgba(99,102,241,.15); color: var(--violet);
    border: 1px solid rgba(99,102,241,.25);
    padding: .18rem .65rem; border-radius: 100px; letter-spacing: .06em;
}

/* ═══════════ TABLE ═══════════ */
.table-wrap {
    background: var(--glass);
    backdrop-filter: blur(24px) saturate(1.6);
    border: 1px solid var(--border);
    border-radius: var(--r); overflow: hidden;
    box-shadow: var(--shadow);
    animation: fadeUp .5s .33s cubic-bezier(.22,1,.36,1) both;
}

table { width: 100%; border-collapse: collapse; }

thead tr {
    background: rgba(6,8,16,.6);
    border-bottom: 1px solid var(--border-hi);
}
thead th {
    padding: 1rem 1.3rem;
    font-family: 'DM Mono', monospace;
    font-size: .58rem; font-weight: 400;
    letter-spacing: .16em; text-transform: uppercase;
    color: var(--ink3); text-align: left; white-space: nowrap;
}

tbody tr {
    border-bottom: 1px solid rgba(255,255,255,.04);
    transition: background .15s;
    animation: fadeUp .45s ease both;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--glass-md); }

td { padding: .95rem 1.3rem; font-size: .875rem; vertical-align: middle; }

/* Row ID */
.row-num {
    font-family: 'DM Mono', monospace;
    font-size: .65rem; color: var(--ink3); letter-spacing: .04em;
}

/* Medicine cell */
.med-cell { display: flex; align-items: center; gap: .9rem; }
.med-avatar {
    width: 46px; height: 46px; border-radius: var(--r2); flex-shrink: 0;
    border: 1px solid var(--border); background: var(--glass-md);
    display: grid; place-items: center; overflow: hidden; position: relative;
    transition: border-color .2s, transform .2s, box-shadow .2s;
}
.med-avatar.has-img { border-color: rgba(45,212,191,.25); cursor: zoom-in; }
.med-avatar.has-img:hover {
    border-color: var(--teal); transform: scale(1.1);
    box-shadow: 0 0 24px rgba(45,212,191,.3);
}
.med-avatar.has-img::after {
    content: '⊕'; position: absolute; inset: 0;
    background: rgba(15,118,110,.7); display: grid; place-items: center;
    font-size: 1rem; color: var(--teal); opacity: 0; transition: opacity .18s;
}
.med-avatar.has-img:hover::after { opacity: 1; }
.med-avatar img { width:100%;height:100%;object-fit:cover;display:block; }
.avatar-emoji { font-size: 1.2rem; }

.med-name { font-weight: 500; color: var(--ink); font-size: .875rem; line-height: 1.3; }
.med-id {
    font-family: 'DM Mono', monospace; font-size: .58rem;
    color: var(--ink3); margin-top: 3px; letter-spacing: .05em;
}

/* Category badge */
.cat-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .7rem; font-weight: 400; padding: .25rem .7rem;
    border-radius: 100px;
    background: var(--glass-md); border: 1px solid var(--border);
    color: var(--ink2); white-space: nowrap;
    backdrop-filter: blur(4px);
    transition: border-color .2s;
}
tbody tr:hover .cat-badge { border-color: rgba(45,212,191,.2); color: var(--ink); }

/* Qty */
.qty-cell { display: flex; align-items: center; gap: .6rem; }
.qty-num { font-family: 'DM Mono', monospace; font-size: .8rem; color: var(--ink); min-width: 34px; }
.qty-rail { flex:1; min-width:42px; max-width:52px; height:3px; background:rgba(255,255,255,.08); border-radius:99px; overflow:hidden; }
.qty-fill { height:100%; border-radius:99px; background: linear-gradient(90deg, var(--teal-dk), var(--teal)); transition: width .4s ease; }
.qty-low .qty-fill { background: linear-gradient(90deg, #b91c1c, var(--rose)); }
.qty-low .qty-num  { color: var(--rose); }

/* Price */
.price-col { font-family: 'DM Mono', monospace; font-size: .8rem; color: var(--amber); }

/* Expiry */
.exp-col { display: flex; flex-direction: column; gap: 3px; }
.exp-date { font-family: 'DM Mono', monospace; font-size: .73rem; color: var(--ink2); }
.exp-tag {
    display: inline-flex; align-items: center; gap: .25rem;
    font-family: 'DM Mono', monospace;
    font-size: .58rem; font-weight: 500;
    padding: .1rem .48rem; border-radius: 100px;
    letter-spacing: .04em; text-transform: uppercase;
}
.exp-tag.expired { background: var(--rust-bg); color: #f87171; border: 1px solid rgba(239,68,68,.3); }
.exp-tag.near    { background: rgba(251,191,36,.1); color: var(--amber); border: 1px solid rgba(251,191,36,.3); }
.exp-tag.caution { background: rgba(99,102,241,.1); color: var(--violet); border: 1px solid rgba(99,102,241,.25); }

/* Supplier */
.sup-col { font-size: .8rem; color: var(--ink2); }

/* Actions */
.act-cell { display: flex; gap: .4rem; }
.btn-act {
    display: inline-flex; align-items: center; gap: .28rem;
    font-family: 'Jost', sans-serif; font-size: .72rem; font-weight: 500;
    padding: .35rem .8rem; border-radius: var(--r3);
    border: 1px solid transparent; cursor: pointer;
    text-decoration: none; transition: all .18s; white-space: nowrap;
    backdrop-filter: blur(4px);
}
.btn-edit {
    background: rgba(99,102,241,.1); color: var(--violet);
    border-color: rgba(99,102,241,.2);
}
.btn-edit:hover {
    background: rgba(99,102,241,.22); border-color: var(--indigo);
    box-shadow: 0 0 18px rgba(99,102,241,.25);
    transform: translateY(-1px);
}
.btn-del {
    background: rgba(239,68,68,.08); color: #f87171;
    border-color: rgba(239,68,68,.18);
}
.btn-del:hover {
    background: rgba(239,68,68,.18); border-color: rgba(239,68,68,.5);
    box-shadow: 0 0 18px rgba(239,68,68,.18);
    transform: translateY(-1px);
}

/* Empty state */
.empty-state { padding: 7rem 2rem; text-align: center; }
.empty-icon { font-size: 3rem; display: block; margin-bottom: 1.2rem; opacity: .3; }
.empty-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem; color: var(--ink); margin-bottom: .5rem;
}
.empty-sub { color: var(--ink3); font-size: .9rem; }
.empty-sub a { color: var(--teal); text-decoration: none; }

#noResults { display:none; padding:4rem 2rem; text-align:center; color:var(--ink3); }
#noResults .ni { font-size:2rem; display:block; margin-bottom:.5rem; opacity:.3; }

/* ═══════════ LIGHTBOX ═══════════ */
#lightbox {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(2,4,12,.92); backdrop-filter: blur(20px);
    place-items: center; cursor: zoom-out;
}
#lightbox.open { display: grid; animation: fadeIn .2s ease; }
#lightbox-img {
    max-width: min(86vw,520px); max-height: 80vh;
    object-fit: contain; border-radius: 16px;
    box-shadow: 0 40px 80px rgba(0,0,0,.9), 0 0 0 1px rgba(255,255,255,.08), var(--glow-t);
    animation: popIn .25s cubic-bezier(.34,1.56,.64,1) both;
}
#lightbox-caption {
    position: fixed; bottom: 2.5rem; left: 50%; transform: translateX(-50%);
    font-family: 'DM Mono', monospace; font-size: .75rem;
    color: rgba(255,255,255,.6); letter-spacing: .1em;
    background: rgba(255,255,255,.07); backdrop-filter: blur(16px);
    padding: .5rem 1.5rem; border-radius: 100px;
    border: 1px solid rgba(255,255,255,.1); white-space: nowrap;
}
#lightbox-close {
    position: fixed; top: 1.5rem; right: 2rem;
    background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.65); border-radius: 50%;
    width: 38px; height: 38px; font-size: 1rem;
    cursor: pointer; display: grid; place-items: center;
    transition: background .15s;
}
#lightbox-close:hover { background: rgba(255,255,255,.16); color: #fff; }

/* ═══════════ TOAST ═══════════ */
.toast {
    position: fixed; bottom: 2rem; right: 2rem; z-index: 1000;
    display: flex; align-items: center; gap: .65rem;
    background: rgba(15,118,110,.85);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(45,212,191,.4);
    color: #ccfbf1; font-size: .875rem; font-weight: 500;
    padding: .85rem 1.4rem; border-radius: var(--r);
    box-shadow: var(--glow-t), 0 8px 32px rgba(0,0,0,.5);
    animation: slideUp .4s cubic-bezier(.34,1.56,.64,1) both;
    opacity: 1; transition: opacity .4s;
}
.toast-pip { width: 6px; height: 6px; background: var(--teal); border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 8px var(--teal); }
.toast.hide { opacity: 0; }

/* ═══════════ SCROLLBAR ═══════════ */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }

/* ═══════════ KEYFRAMES ═══════════ */
@keyframes fadeUp  { from { opacity:0; transform:translateY(22px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
@keyframes popIn   { from { opacity:0; transform:scale(.82); } to { opacity:1; transform:scale(1); } }
@keyframes slideUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }

/* Stagger rows */
tbody tr:nth-child(1)  { animation-delay: .05s; }
tbody tr:nth-child(2)  { animation-delay: .09s; }
tbody tr:nth-child(3)  { animation-delay: .13s; }
tbody tr:nth-child(4)  { animation-delay: .17s; }
tbody tr:nth-child(5)  { animation-delay: .21s; }
tbody tr:nth-child(6)  { animation-delay: .25s; }
tbody tr:nth-child(7)  { animation-delay: .29s; }
tbody tr:nth-child(8)  { animation-delay: .33s; }
tbody tr:nth-child(n+9){ animation-delay: .36s; }

/* ═══════════ RESPONSIVE ═══════════ */
@media (max-width: 1100px) { .stats { grid-template-columns:1fr 1fr; } .stats .stat-card:last-child { grid-column:1/-1; } }
@media (max-width: 900px) {
    .nav { padding: 0 1.25rem; } .nav-mid { display: none; }
    .main { padding: 1.5rem 1.25rem; }
    td:nth-child(6),th:nth-child(6),td:nth-child(7),th:nth-child(7) { display:none; }
    .page-title { font-size: 2rem; }
}
@media (max-width: 620px) {
    .stats { grid-template-columns: 1fr; } .stats .stat-card:last-child { grid-column:1; }
    td:nth-child(4),th:nth-child(4),td:nth-child(5),th:nth-child(5) { display:none; }
    .page-header { flex-direction:column; align-items:flex-start; gap:.6rem; }
}
</style>
</head>
<body>

<!-- ═══════ AURORA BACKGROUND ═══════ -->
<div class="aurora-bg" aria-hidden="true">
    <div class="aurora-blob blob-1"></div>
    <div class="aurora-blob blob-2"></div>
    <div class="aurora-blob blob-3"></div>
    <div class="aurora-blob blob-4"></div>
    <div class="aurora-blob blob-5"></div>
</div>

<!-- ═══════ LIGHTBOX ═══════ -->
<div id="lightbox" role="dialog" aria-modal="true">
    <button id="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightbox-img" src="" alt="">
    <div id="lightbox-caption"></div>
</div>

<!-- ═══════ NAV ═══════ -->
<nav class="nav">
    <a href="index.php" class="brand">
        <div class="brand-mark">⚕</div>
        <div>
            <div class="brand-label">MedInventory</div>
            <span class="brand-sub">Pharmacy System</span>
        </div>
    </a>

    <div class="nav-mid">
        <div class="search-box">
            <input type="text" id="searchInput"
                   placeholder="Search medicine, category, supplier…"
                   autocomplete="off" value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>

    <a href="add.php" class="btn-add">＋ Add Medicine</a>
</nav>

<!-- ═══════ MAIN ═══════ -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-eyebrow">Inventory Management</div>
            <div class="page-title">Medicine <em>Registry</em></div>
        </div>
        <div class="page-date">
            <span id="liveTime"></span>
            <span id="liveDate"></span>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num" id="statItems"><?= $total_items ?></div>
            <div class="stat-label">Total Items</div>
            <div class="stat-icon">🧾</div>
        </div>
        <div class="stat-card">
            <div class="stat-num c-teal" id="statQty"><?= number_format($total_qty) ?></div>
            <div class="stat-label">Total Units</div>
            <div class="stat-icon">📦</div>
        </div>
        <div class="stat-card">
            <div class="stat-num c-amber" id="statValue">₱<?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Inventory Value</div>
            <div class="stat-icon">💰</div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="tbl-heading">
            Medicine List
            <span class="rec-pill" id="countBadge"><?= $total_items ?> records</span>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <span class="empty-icon">⚗️</span>
            <div class="empty-title">No medicines found</div>
            <p class="empty-sub">Try a different search or <a href="add.php">add a new record</a>.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Expiry</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php
            $quantities = array_column($rows, 'quantity');
            $maxQty     = !empty($quantities) ? max(max($quantities), 1) : 1;

            foreach ($rows as $i => $row):
                [$expClass, $expLabel] = expiryStatus($row['expiry_date'] ?? '');
                $icon    = categoryIcon($row['category'] ?? '');
                $qtyPct  = min(100, round(($row['quantity'] / $maxQty) * 100));
                $qtyLow  = $row['quantity'] < 10 ? 'qty-low' : '';
                $medName = htmlspecialchars($row['mname']);
                $medId   = (int)$row['id'];
                $rawImg  = $row['image'] ?? '';
                $imgPath = ($rawImg !== '' && file_exists($rawImg)) ? htmlspecialchars($rawImg) : '';
            ?>
            <tr data-name="<?= htmlspecialchars(strtolower($row['mname'])) ?>"
                data-cat="<?= htmlspecialchars(strtolower($row['category'])) ?>"
                data-sup="<?= htmlspecialchars(strtolower($row['supplier'])) ?>">

                <td><span class="row-num"><?= str_pad($medId, 4, '0', STR_PAD_LEFT) ?></span></td>

                <td>
                    <div class="med-cell">
                        <?php if ($imgPath): ?>
                        <div class="med-avatar has-img" onclick="openLightbox('<?= $imgPath ?>','<?= $medName ?>')">
                            <img src="<?= $imgPath ?>" alt="<?= $medName ?>" loading="lazy">
                        </div>
                        <?php else: ?>
                        <div class="med-avatar"><span class="avatar-emoji"><?= $icon ?></span></div>
                        <?php endif; ?>
                        <div>
                            <div class="med-name"><?= $medName ?></div>
                            <div class="med-id">ID-<?= str_pad($medId, 4, '0', STR_PAD_LEFT) ?></div>
                        </div>
                    </div>
                </td>

                <td><span class="cat-badge"><?= $icon ?> <?= htmlspecialchars($row['category']) ?></span></td>

                <td>
                    <div class="qty-cell <?= $qtyLow ?>">
                        <span class="qty-num"><?= number_format($row['quantity']) ?></span>
                        <div class="qty-rail"><div class="qty-fill" style="width:<?= $qtyPct ?>%"></div></div>
                    </div>
                </td>

                <td class="price-col">₱<?= number_format($row['price'], 2) ?></td>

                <td>
                    <?php if (!empty($row['expiry_date'])): ?>
                    <div class="exp-col">
                        <span class="exp-date"><?= htmlspecialchars($row['expiry_date']) ?></span>
                        <?php if ($expLabel): ?>
                        <span class="exp-tag <?= $expClass ?>"><?= $expLabel ?></span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--ink3);font-family:'DM Mono',monospace;font-size:.7rem">—</span>
                    <?php endif; ?>
                </td>

                <td class="sup-col"><?= htmlspecialchars($row['supplier']) ?></td>

                <td>
                    <div class="act-cell">
                        <a class="btn-act btn-edit" href="edit.php?id=<?= $medId ?>">✎ Edit</a>
                        <a class="btn-act btn-del" href="delete.php?id=<?= $medId ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['mname'])) ?>?')">✕ Del</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="noResults">
            <span class="ni">⌕</span>
            No results for "<strong id="noTerm" style="color:var(--teal)"></strong>"
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if (isset($_GET['updated'])): ?>
<div class="toast" id="toast"><span class="toast-pip"></span> Record updated successfully</div>
<?php endif; ?>
<?php if (isset($_GET['added'])): ?>
<div class="toast" id="toast"><span class="toast-pip"></span> Medicine added successfully</div>
<?php endif; ?>

<script>
// ── Live clock ──
(function tick() {
    const now  = new Date();
    const dt   = document.getElementById('liveDate');
    const tm   = document.getElementById('liveTime');
    if (dt) dt.textContent = now.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'}).toUpperCase();
    if (tm) tm.textContent = now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    setTimeout(tick, 1000);
})();

// ── Lightbox ──
const lb   = document.getElementById('lightbox');
const lbI  = document.getElementById('lightbox-img');
const lbC  = document.getElementById('lightbox-caption');
function openLightbox(src, name) { lbI.src=src; lbI.alt=name; lbC.textContent=name; lb.classList.add('open'); document.body.style.overflow='hidden'; }
function closeLightbox()          { lb.classList.remove('open'); document.body.style.overflow=''; lbI.src=''; }
lb.addEventListener('click', e => { if (e.target===lb) closeLightbox(); });
document.addEventListener('keydown', e => { if (e.key==='Escape') closeLightbox(); });

// ── Live search ──
const si    = document.getElementById('searchInput');
const trows = document.querySelectorAll('#tableBody tr');
const badge = document.getElementById('countBadge');
const noRes = document.getElementById('noResults');
const noT   = document.getElementById('noTerm');
const sI    = document.getElementById('statItems');
const sQ    = document.getElementById('statQty');
const sV    = document.getElementById('statValue');

function filterTable(q) {
    q = q.toLowerCase().trim();
    let vis=0, tQ=0, tV=0;
    trows.forEach(row => {
        const m = !q
            || (row.dataset.name||'').includes(q)
            || (row.dataset.cat ||'').includes(q)
            || (row.dataset.sup ||'').includes(q);
        row.style.display = m ? '' : 'none';
        if (m) {
            vis++;
            const qty   = parseFloat(row.cells[3]?.querySelector('.qty-num')?.textContent.replace(/,/g,'')||0);
            const price = parseFloat(row.cells[4]?.textContent.replace(/[₱,]/g,'')||0);
            tQ += qty; tV += qty * price;
        }
    });
    badge.textContent = vis + ' record' + (vis!==1?'s':'');
    if(sI) sI.textContent = vis;
    if(sQ) sQ.textContent = tQ.toLocaleString();
    if(sV) sV.textContent = '₱' + Math.round(tV).toLocaleString();
    noRes.style.display = vis===0 ? 'block' : 'none';
    if(noT) noT.textContent = q;
    const url = new URL(window.location);
    q ? url.searchParams.set('search',q) : url.searchParams.delete('search');
    history.replaceState({}, '', url);
}

if (si) {
    let t;
    si.addEventListener('input', e => { clearTimeout(t); t=setTimeout(()=>filterTable(e.target.value),100); });
    if (si.value) filterTable(si.value);
}

// ── Toast ──
const toast = document.getElementById('toast');
if (toast) { setTimeout(()=>toast.classList.add('hide'),3000); setTimeout(()=>toast.remove(),3500); }
</script>
</body>
</html> 