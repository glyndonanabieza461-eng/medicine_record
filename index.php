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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --night:     #0e0f0c;
    --night2:    #141510;
    --night3:    #1c1d18;
    --night4:    #252620;
    --moss:      #2a3d2e;
    --sage:      #4a7c59;
    --mint:      #7ab893;
    --foam:      #b8dfc8;
    --ivory:     #f5f0e8;
    --gold:      #c9a84c;
    --gold2:     #e8c96a;
    --rust:      #c0513a;
    --rust-lt:   #3d1a13;
    --amber:     #d4843a;
    --ink:       #f0ece2;
    --ink2:      #9b9689;
    --ink3:      #5a5750;
    --border:    rgba(255,255,255,.07);
    --border2:   rgba(255,255,255,.12);
    --glow-sage: 0 0 40px rgba(74,124,89,.25);
    --shadow-card: 0 2px 0 rgba(255,255,255,.04), 0 20px 60px rgba(0,0,0,.5);
    --r: 12px; --r2: 8px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Outfit', sans-serif;
    background: var(--night);
    color: var(--ink);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(ellipse 70% 50% at 0% 0%, rgba(42,61,46,.55) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 100% 100%, rgba(20,21,16,.9) 0%, transparent 55%),
        radial-gradient(ellipse 60% 30% at 50% 115%, rgba(74,124,89,.1) 0%, transparent 50%);
}

/* NAV */
.nav {
    position: sticky; top: 0; z-index: 200;
    height: 66px;
    background: rgba(14,15,12,.9);
    backdrop-filter: blur(20px) saturate(1.4);
    border-bottom: 1px solid var(--border);
    padding: 0 2.5rem;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 1px 0 rgba(74,124,89,.25), 0 4px 24px rgba(0,0,0,.4);
}
.nav::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent 0%, var(--sage) 30%, var(--gold) 60%, var(--sage) 80%, transparent 100%);
    opacity: .7;
}
.brand { display: flex; align-items: center; gap: 1rem; text-decoration: none; }
.brand-emblem {
    width: 38px; height: 38px;
    background: linear-gradient(145deg, var(--moss), var(--night3));
    border: 1px solid rgba(74,124,89,.45);
    border-radius: 10px; display: grid; place-items: center;
    font-size: 1.1rem; flex-shrink: 0;
    box-shadow: var(--glow-sage), inset 0 1px 0 rgba(255,255,255,.08);
}
.brand-text-wrap { display: flex; flex-direction: column; line-height: 1; }
.brand-name {
    font-family: 'Playfair Display', serif; font-weight: 700; font-size: 1.15rem;
    color: var(--ivory); letter-spacing: -.01em;
}
.brand-sub {
    font-family: 'DM Mono', monospace; font-size: .58rem;
    color: var(--sage); letter-spacing: .2em; text-transform: uppercase; margin-top: 3px;
}
.nav-center { flex: 1; max-width: 380px; margin: 0 2rem; }
.search-wrap { position: relative; }
.search-icon { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--ink3); font-size: .85rem; pointer-events: none; }
#searchInput {
    width: 100%; padding: .55rem 1rem .55rem 2.4rem;
    background: var(--night3); border: 1px solid var(--border2);
    border-radius: var(--r2); color: var(--ink);
    font-family: 'Outfit', sans-serif; font-size: .875rem; outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
#searchInput:focus { background: var(--night4); border-color: var(--sage); box-shadow: 0 0 0 3px rgba(74,124,89,.15); }
#searchInput::placeholder { color: var(--ink3); }
.nav-actions { display: flex; align-items: center; gap: .75rem; }
.btn-add {
    display: inline-flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg, var(--sage), var(--moss));
    color: #fff; font-family: 'Outfit', sans-serif; font-weight: 600; font-size: .85rem;
    padding: .55rem 1.25rem; border-radius: var(--r2); text-decoration: none;
    border: 1px solid rgba(122,184,147,.2);
    box-shadow: var(--glow-sage), inset 0 1px 0 rgba(255,255,255,.1);
    transition: all .2s; white-space: nowrap;
}
.btn-add:hover { transform: translateY(-1px); box-shadow: 0 0 50px rgba(74,124,89,.4), inset 0 1px 0 rgba(255,255,255,.15); }

/* LAYOUT */
.main {
    position: relative; z-index: 1;
    max-width: 1500px; margin: 0 auto; padding: 2.5rem;
}

/* PAGE HEADER */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 2rem; padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
    animation: fadeUp .6s ease both;
}
.page-eyebrow {
    font-family: 'DM Mono', monospace; font-size: .62rem;
    color: var(--sage); letter-spacing: .2em; text-transform: uppercase; margin-bottom: .4rem;
}
.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem; font-weight: 900;
    color: var(--ivory); line-height: 1.05; letter-spacing: -.02em;
}
.page-title em { font-style: italic; color: var(--mint); }
.page-date {
    font-family: 'DM Mono', monospace; font-size: .68rem;
    color: var(--ink3); letter-spacing: .05em; text-align: right;
}

/* STATS */
.stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 2.5rem; }
.stat-card {
    position: relative; overflow: hidden;
    background: var(--night2); border: 1px solid var(--border);
    border-radius: var(--r); padding: 1.5rem 1.75rem;
    box-shadow: var(--shadow-card);
    transition: border-color .3s, transform .2s;
    animation: fadeUp .5s ease both;
}
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--border2), transparent);
}
.stat-card:nth-child(1) { animation-delay: .05s; }
.stat-card:nth-child(2) { animation-delay: .1s; }
.stat-card:nth-child(3) { animation-delay: .15s; }
.stat-card:hover { border-color: var(--border2); transform: translateY(-2px); }
.stat-number {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem; font-weight: 900; line-height: 1;
    color: var(--ivory); letter-spacing: -.03em; margin-bottom: .4rem;
}
.stat-number.accent { color: var(--mint); }
.stat-number.gold   { color: var(--gold2); }
.stat-label {
    font-family: 'DM Mono', monospace; font-size: .6rem;
    color: var(--ink3); letter-spacing: .15em; text-transform: uppercase;
}
.stat-bg-icon {
    position: absolute; right: 1.25rem; top: 50%; transform: translateY(-50%);
    font-size: 3.5rem; opacity: .055; pointer-events: none; user-select: none;
}

/* TOOLBAR */
.toolbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem; animation: fadeUp .5s .2s ease both;
}
.tbl-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem; font-weight: 700; color: var(--ivory);
}
.count-pill {
    font-family: 'DM Mono', monospace; font-size: .62rem;
    background: var(--moss); color: var(--mint);
    border: 1px solid rgba(74,124,89,.3);
    padding: .2rem .65rem; border-radius: 100px;
    margin-left: .75rem; letter-spacing: .05em;
}

/* TABLE */
.table-wrap {
    background: var(--night2); border: 1px solid var(--border);
    border-radius: var(--r); overflow: hidden;
    box-shadow: var(--shadow-card);
    animation: fadeUp .5s .25s ease both;
}
table { width: 100%; border-collapse: collapse; }
thead tr { background: var(--night3); border-bottom: 1px solid var(--border2); }
thead th {
    padding: .9rem 1.25rem;
    font-family: 'DM Mono', monospace; font-size: .59rem; font-weight: 500;
    letter-spacing: .14em; text-transform: uppercase;
    color: var(--ink3); text-align: left; white-space: nowrap;
}
tbody tr { border-bottom: 1px solid rgba(255,255,255,.04); transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,.022); }
td { padding: .9rem 1.25rem; font-size: .875rem; vertical-align: middle; }

.row-num { font-family: 'DM Mono', monospace; font-size: .68rem; color: var(--ink3); }

/* Medicine cell */
.med-cell { display: flex; align-items: center; gap: .9rem; }
.med-avatar {
    width: 48px; height: 48px; border-radius: var(--r2);
    flex-shrink: 0; border: 1px solid var(--border);
    background: var(--night3); display: grid; place-items: center;
    overflow: hidden; position: relative;
    transition: border-color .2s, transform .2s, box-shadow .2s;
}
.med-avatar.has-img { border-color: rgba(74,124,89,.3); cursor: zoom-in; }
.med-avatar.has-img:hover { border-color: var(--sage); transform: scale(1.08); box-shadow: 0 0 20px rgba(74,124,89,.3); }
.med-avatar.has-img::after {
    content: '⊕'; position: absolute; inset: 0;
    background: rgba(42,61,46,.75); display: grid; place-items: center;
    font-size: 1.1rem; color: var(--mint); opacity: 0; transition: opacity .2s;
}
.med-avatar.has-img:hover::after { opacity: 1; }
.med-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.avatar-emoji { font-size: 1.25rem; user-select: none; }
.med-name { font-weight: 600; color: var(--ivory); font-size: .875rem; line-height: 1.3; }
.med-id {
    font-family: 'DM Mono', monospace; font-size: .6rem;
    color: var(--ink3); margin-top: 2px; letter-spacing: .04em;
}

/* Category */
.cat-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .72rem; font-weight: 500; padding: .28rem .75rem;
    border-radius: 100px; background: var(--night3);
    border: 1px solid var(--border2); color: var(--ink2); white-space: nowrap;
}

/* Qty */
.qty-cell { display: flex; align-items: center; gap: .6rem; }
.qty-num { font-family: 'DM Mono', monospace; font-size: .82rem; color: var(--ivory); min-width: 36px; }
.qty-track { flex: 1; min-width: 44px; max-width: 56px; height: 4px; background: var(--night4); border-radius: 99px; overflow: hidden; }
.qty-fill { height: 100%; border-radius: 99px; background: var(--sage); }
.qty-low .qty-fill { background: var(--rust); }
.qty-low .qty-num  { color: #e08070; }

/* Price */
.price-cell { font-family: 'DM Mono', monospace; font-size: .82rem; color: var(--gold2); }

/* Expiry */
.exp-wrap { display: flex; flex-direction: column; gap: 3px; }
.exp-date { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--ink2); }
.exp-tag {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .62rem; font-weight: 600; padding: .12rem .5rem;
    border-radius: 100px; letter-spacing: .04em; text-transform: uppercase;
}
.exp-tag.expired { background: var(--rust-lt); color: #f08070; border: 1px solid rgba(192,81,58,.3); }
.exp-tag.near    { background: rgba(212,132,58,.12); color: var(--amber); border: 1px solid rgba(212,132,58,.3); }
.exp-tag.caution { background: rgba(201,168,76,.1); color: var(--gold); border: 1px solid rgba(201,168,76,.25); }

/* Supplier */
.supplier-cell { font-size: .82rem; color: var(--ink2); }

/* Actions */
.action-cell { display: flex; gap: .4rem; }
.btn-action {
    display: inline-flex; align-items: center; gap: .3rem;
    font-family: 'Outfit', sans-serif; font-size: .75rem; font-weight: 600;
    padding: .38rem .85rem; border-radius: var(--r2);
    border: 1px solid transparent; cursor: pointer;
    text-decoration: none; transition: all .18s; white-space: nowrap;
}
.btn-edit { background: var(--night3); color: var(--mint); border-color: rgba(74,124,89,.25); }
.btn-edit:hover { background: var(--moss); border-color: var(--sage); box-shadow: 0 0 16px rgba(74,124,89,.2); }
.btn-del  { background: var(--night3); color: #e08070; border-color: rgba(192,81,58,.2); }
.btn-del:hover  { background: var(--rust-lt); border-color: rgba(192,81,58,.5); box-shadow: 0 0 16px rgba(192,81,58,.15); }

/* Empty */
.empty-state { padding: 6rem 2rem; text-align: center; }
.empty-icon { font-size: 3.5rem; display: block; margin-bottom: 1rem; opacity: .35; }
.empty-title { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--ivory); margin-bottom: .5rem; }
.empty-sub { color: var(--ink3); font-size: .9rem; }
.empty-sub a { color: var(--mint); text-decoration: none; }

#noResults { display: none; padding: 4rem 2rem; text-align: center; color: var(--ink3); }
#noResults .ni { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .35; }

/* Lightbox */
#lightbox {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.9); backdrop-filter: blur(16px);
    place-items: center; cursor: zoom-out;
}
#lightbox.open { display: grid; animation: fadeIn .2s ease; }
#lightbox-img {
    max-width: min(86vw,520px); max-height: 80vh;
    object-fit: contain; border-radius: 16px;
    box-shadow: 0 32px 80px rgba(0,0,0,.8), 0 0 0 1px rgba(255,255,255,.07);
    animation: zoomIn .22s cubic-bezier(.34,1.56,.64,1) both;
}
#lightbox-caption {
    position: fixed; bottom: 2.5rem; left: 50%; transform: translateX(-50%);
    font-family: 'DM Mono', monospace; font-size: .78rem;
    color: rgba(255,255,255,.65); letter-spacing: .08em;
    background: rgba(255,255,255,.07); backdrop-filter: blur(12px);
    padding: .5rem 1.4rem; border-radius: 100px;
    border: 1px solid rgba(255,255,255,.1); white-space: nowrap;
}
#lightbox-close {
    position: fixed; top: 1.5rem; right: 2rem;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.7); border-radius: 50%;
    width: 38px; height: 38px; font-size: 1rem;
    cursor: pointer; display: grid; place-items: center;
    transition: background .15s;
}
#lightbox-close:hover { background: rgba(255,255,255,.18); color: #fff; }

/* Toast */
.toast {
    position: fixed; bottom: 2rem; right: 2rem; z-index: 1000;
    display: flex; align-items: center; gap: .65rem;
    background: var(--moss); border: 1px solid rgba(74,124,89,.5);
    color: var(--foam); font-size: .875rem; font-weight: 500;
    padding: .85rem 1.4rem; border-radius: var(--r);
    box-shadow: 0 0 40px rgba(74,124,89,.25), 0 8px 32px rgba(0,0,0,.5);
    animation: slideUp .35s cubic-bezier(.34,1.56,.64,1) both;
    opacity: 1; transition: opacity .4s;
}
.toast-dot { width: 6px; height: 6px; background: var(--mint); border-radius: 50%; flex-shrink: 0; }
.toast.hide { opacity: 0; }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--night2); }
::-webkit-scrollbar-thumb { background: var(--night4); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--ink3); }

/* Animations */
@keyframes fadeUp  { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
@keyframes zoomIn  { from { opacity:0; transform:scale(.85); } to { opacity:1; transform:scale(1); } }
@keyframes slideUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

tbody tr { animation: fadeUp .4s ease both; }

/* Responsive */
@media (max-width: 1100px) { .stats { grid-template-columns: 1fr 1fr; } .stats .stat-card:last-child { grid-column: 1/-1; } }
@media (max-width: 900px) {
    .nav { padding: 0 1.25rem; } .nav-center { display: none; }
    .main { padding: 1.5rem 1.25rem; }
    td:nth-child(6),th:nth-child(6),td:nth-child(7),th:nth-child(7) { display: none; }
    .page-title { font-size: 1.6rem; }
}
@media (max-width: 640px) {
    .stats { grid-template-columns: 1fr; } .stats .stat-card:last-child { grid-column: 1; }
    td:nth-child(4),th:nth-child(4),td:nth-child(5),th:nth-child(5) { display: none; }
    .page-header { flex-direction: column; align-items: flex-start; gap: .5rem; }
}
</style>
</head>
<body>

<div id="lightbox" role="dialog" aria-modal="true">
    <button id="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightbox-img" src="" alt="">
    <div id="lightbox-caption"></div>
</div>

<nav class="nav">
    <a href="index.php" class="brand">
        <div class="brand-emblem">⚕</div>
        <div class="brand-text-wrap">
            <span class="brand-name">MedInventory</span>
            <span class="brand-sub">Pharmacy System</span>
        </div>
    </a>
    <div class="nav-center">
        <div class="search-wrap">
            <span class="search-icon">⌕</span>
            <input type="text" id="searchInput"
                   placeholder="Search medicine, category, supplier…"
                   autocomplete="off" value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="nav-actions">
        <a href="add.php" class="btn-add">＋ Add Medicine</a>
    </div>
</nav>

<div class="main">

    <div class="page-header">
        <div>
            <div class="page-eyebrow">Inventory Management</div>
            <div class="page-title">Medicine <em>Registry</em></div>
        </div>
        <div class="page-date" id="liveDate"></div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number" id="statItems"><?= $total_items ?></div>
            <div class="stat-label">Total Items</div>
            <div class="stat-bg-icon">🧾</div>
        </div>
        <div class="stat-card">
            <div class="stat-number accent" id="statQty"><?= number_format($total_qty) ?></div>
            <div class="stat-label">Total Units</div>
            <div class="stat-bg-icon">📦</div>
        </div>
        <div class="stat-card">
            <div class="stat-number gold" id="statValue">₱<?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Inventory Value</div>
            <div class="stat-bg-icon">💰</div>
        </div>
    </div>

    <div class="toolbar">
        <div style="display:flex;align-items:center">
            <span class="tbl-title">Medicine List</span>
            <span class="count-pill" id="countBadge"><?= $total_items ?> records</span>
        </div>
    </div>

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
                $delay   = min($i * 35, 380);
                $medName = htmlspecialchars($row['mname']);
                $medId   = (int)$row['id'];
                $rawImg  = $row['image'] ?? '';
                $imgPath = ($rawImg !== '' && file_exists($rawImg)) ? htmlspecialchars($rawImg) : '';
            ?>
            <tr data-name="<?= htmlspecialchars(strtolower($row['mname'])) ?>"
                data-cat="<?= htmlspecialchars(strtolower($row['category'])) ?>"
                data-sup="<?= htmlspecialchars(strtolower($row['supplier'])) ?>"
                style="animation-delay:<?= $delay ?>ms">

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
                        <div class="qty-track"><div class="qty-fill" style="width:<?= $qtyPct ?>%"></div></div>
                    </div>
                </td>

                <td class="price-cell">₱<?= number_format($row['price'], 2) ?></td>

                <td>
                    <?php if (!empty($row['expiry_date'])): ?>
                    <div class="exp-wrap">
                        <span class="exp-date"><?= htmlspecialchars($row['expiry_date']) ?></span>
                        <?php if ($expLabel): ?>
                        <span class="exp-tag <?= $expClass ?>"><?= $expLabel ?></span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--ink3);font-family:'DM Mono',monospace;font-size:.72rem">—</span>
                    <?php endif; ?>
                </td>

                <td class="supplier-cell"><?= htmlspecialchars($row['supplier']) ?></td>

                <td>
                    <div class="action-cell">
                        <a class="btn-action btn-edit" href="edit.php?id=<?= $medId ?>">✎ Edit</a>
                        <a class="btn-action btn-del" href="delete.php?id=<?= $medId ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['mname'])) ?>?')">✕ Del</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="noResults">
            <span class="ni">⌕</span>
            No results for "<strong id="noTerm" style="color:var(--mint)"></strong>"
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['updated'])): ?>
<div class="toast" id="toast"><span class="toast-dot"></span> Record updated successfully</div>
<?php endif; ?>
<?php if (isset($_GET['added'])): ?>
<div class="toast" id="toast"><span class="toast-dot"></span> Medicine added successfully</div>
<?php endif; ?>

<script>
// Live date
(function(){
    const el = document.getElementById('liveDate');
    if (!el) return;
    el.textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'}).toUpperCase();
})();

// Lightbox
const lightbox=document.getElementById('lightbox'),lbImg=document.getElementById('lightbox-img'),lbCap=document.getElementById('lightbox-caption');
function openLightbox(src,name){lbImg.src=src;lbImg.alt=name;lbCap.textContent=name;lightbox.classList.add('open');document.body.style.overflow='hidden';}
function closeLightbox(){lightbox.classList.remove('open');document.body.style.overflow='';lbImg.src='';}
lightbox.addEventListener('click',e=>{if(e.target===lightbox)closeLightbox();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeLightbox();});

// Live search
const si=document.getElementById('searchInput'),rows=document.querySelectorAll('#tableBody tr'),
      badge=document.getElementById('countBadge'),noRes=document.getElementById('noResults'),
      noTerm=document.getElementById('noTerm'),sI=document.getElementById('statItems'),
      sQ=document.getElementById('statQty'),sV=document.getElementById('statValue');

function filterTable(q){
    q=q.toLowerCase().trim();
    let vis=0,tQ=0,tV=0;
    rows.forEach(row=>{
        const m=!q||(row.dataset.name||'').includes(q)||(row.dataset.cat||'').includes(q)||(row.dataset.sup||'').includes(q);
        row.style.display=m?'':'none';
        if(m){vis++;
            const qty=parseFloat(row.cells[3]?.querySelector('.qty-num')?.textContent.replace(/,/g,'')||0);
            const price=parseFloat(row.cells[4]?.textContent.replace(/[₱,]/g,'')||0);
            tQ+=qty;tV+=qty*price;
        }
    });
    badge.textContent=vis+' record'+(vis!==1?'s':'');
    if(sI)sI.textContent=vis;
    if(sQ)sQ.textContent=tQ.toLocaleString();
    if(sV)sV.textContent='₱'+Math.round(tV).toLocaleString();
    noRes.style.display=vis===0?'block':'none';
    if(noTerm)noTerm.textContent=q;
    const url=new URL(window.location);
    q?url.searchParams.set('search',q):url.searchParams.delete('search');
    history.replaceState({}, '', url);
}

if(si){
    let t;
    si.addEventListener('input',e=>{clearTimeout(t);t=setTimeout(()=>filterTable(e.target.value),100);});
    if(si.value)filterTable(si.value);
}

// Toast
const toast=document.getElementById('toast');
if(toast){setTimeout(()=>toast.classList.add('hide'),3200);setTimeout(()=>toast.remove(),3700);}
</script>
</body>
</html>