<?php
include 'db.php';

// XSS-safe search term
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch data
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
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Stats
$total_items = count($rows);
$total_qty   = array_sum(array_column($rows, 'quantity'));
$total_value = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));

// Category icon map
function categoryIcon(string $cat): string {
    $cat = strtolower($cat);
    if (str_contains($cat, 'analg') || str_contains($cat, 'pain'))    return '🩹';
    if (str_contains($cat, 'antibi'))                                   return '🦠';
    if (str_contains($cat, 'vitamin') || str_contains($cat, 'suppl'))  return '💊';
    if (str_contains($cat, 'antih') || str_contains($cat, 'allerg'))   return '🤧';
    if (str_contains($cat, 'cardio') || str_contains($cat, 'heart'))   return '❤️';
    if (str_contains($cat, 'diab'))                                     return '🩸';
    if (str_contains($cat, 'antac') || str_contains($cat, 'gastro'))   return '🫃';
    return '💉';
}

// Expiry status
function expiryStatus(string $date): array {
    if (empty($date)) return ['', ''];
    $diff = (strtotime($date) - time()) / 86400;
    if ($diff < 0)   return ['expired',  '⚠ Expired'];
    if ($diff < 30)  return ['near',     '⚠ Expiring soon'];
    if ($diff < 90)  return ['caution',  '· 3 months'];
    return ['ok', ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine Inventory System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
:root {
    --bg:        #f7f5f0;
    --surface:   #ffffff;
    --card:      #ffffff;
    --ink:       #1a1814;
    --ink2:      #6b6560;
    --ink3:      #b0aaa3;
    --accent:    #2d6a4f;
    --accent2:   #52b788;
    --accent-lt: #d8f3dc;
    --warn:      #e07a5f;
    --warn-lt:   #fde8e3;
    --caution:   #f2a65a;
    --caution-lt:#fef3e7;
    --border:    #e8e4dc;
    --shadow:    0 1px 3px rgba(26,24,20,.06), 0 4px 16px rgba(26,24,20,.08);
    --shadow-lg: 0 2px 6px rgba(26,24,20,.06), 0 12px 32px rgba(26,24,20,.12);
    --radius:    14px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Instrument Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}

/* ── TOP NAV ── */
.nav {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(247,245,240,.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
}

.brand {
    display: flex;
    align-items: center;
    gap: .7rem;
}
.brand-pill {
    background: var(--accent);
    color: #fff;
    font-size: 1.15rem;
    width: 36px; height: 36px;
    border-radius: 10px;
    display: grid; place-items: center;
    box-shadow: 0 2px 8px rgba(45,106,79,.35);
}
.brand-name {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800;
    font-size: 1.15rem;
    letter-spacing: -.02em;
    color: var(--ink);
}
.brand-tag {
    font-size: .7rem;
    font-weight: 500;
    color: var(--ink3);
    background: var(--border);
    padding: .2rem .55rem;
    border-radius: 100px;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.nav-right { display: flex; align-items: center; gap: .75rem; }

/* ── SEARCH ── */
.search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: .85rem;
    color: var(--ink3);
    font-size: .95rem;
    pointer-events: none;
}
#searchInput {
    padding: .55rem 1rem .55rem 2.4rem;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    font-family: inherit;
    font-size: .875rem;
    color: var(--ink);
    width: 240px;
    transition: border-color .2s, box-shadow .2s, width .3s;
    outline: none;
}
#searchInput:focus {
    border-color: var(--accent2);
    box-shadow: 0 0 0 3px rgba(82,183,136,.15);
    width: 300px;
}
#searchInput::placeholder { color: var(--ink3); }

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    background: var(--accent);
    color: #fff;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 600;
    font-size: .875rem;
    padding: .55rem 1.2rem;
    border-radius: 10px;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(45,106,79,.3);
    transition: transform .15s, box-shadow .15s;
}
.btn-add:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(45,106,79,.4);
}

/* ── MAIN ── */
.main { max-width: 1400px; margin: 0 auto; padding: 2rem; }

/* ── STAT CARDS ── */
.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
    animation: fadeUp .5s ease both;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.2rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow);
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: grid; place-items: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.stat-icon.green  { background: var(--accent-lt); }
.stat-icon.teal   { background: #d4f0ea; }
.stat-icon.amber  { background: #fef3e7; }
.stat-label { font-size: .75rem; color: var(--ink2); font-weight: 500; letter-spacing: .03em; text-transform: uppercase; }
.stat-value {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--ink);
}

/* ── SECTION HEADER ── */
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    animation: fadeUp .5s .08s ease both;
}
.section-title {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
}
.count-badge {
    background: var(--accent-lt);
    color: var(--accent);
    font-size: .75rem;
    font-weight: 700;
    padding: .25rem .7rem;
    border-radius: 100px;
}
#noResults {
    display: none;
    text-align: center;
    padding: 4rem 2rem;
    color: var(--ink2);
    font-size: .95rem;
}
#noResults span { display: block; font-size: 2.5rem; margin-bottom: .75rem; }

/* ── TABLE WRAPPER ── */
.table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: fadeUp .5s .12s ease both;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: #f2efe8;
    padding: .85rem 1.1rem;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--ink2);
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
thead th:first-child { border-radius: 0; }

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f9f8f5; }

td {
    padding: .95rem 1.1rem;
    font-size: .9rem;
    vertical-align: middle;
}

/* ── MEDICINE AVATAR ── */
.med-cell {
    display: flex;
    align-items: center;
    gap: .85rem;
}
.med-avatar {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: var(--accent-lt);
    display: grid; place-items: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    border: 1px solid rgba(45,106,79,.12);
}
.med-name {
    font-weight: 600;
    color: var(--ink);
    line-height: 1.3;
}
.med-id {
    font-size: .72rem;
    color: var(--ink3);
}

/* ── CATEGORY BADGE ── */
.cat-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .75rem;
    font-weight: 500;
    padding: .3rem .7rem;
    border-radius: 100px;
    background: #eeecea;
    color: var(--ink2);
    white-space: nowrap;
}

/* ── QTY ── */
.qty-wrap {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.qty-bar-bg {
    width: 48px; height: 5px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
}
.qty-bar {
    height: 100%;
    border-radius: 99px;
    background: var(--accent2);
    transition: width .4s ease;
}
.qty-low .qty-bar { background: var(--warn); }
.qty-num { font-weight: 600; min-width: 28px; }

/* ── PRICE ── */
.price-cell {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 700;
    color: var(--ink);
    font-size: .95rem;
}

/* ── EXPIRY STATUS ── */
.exp-ok      { color: var(--ink2); font-size: .85rem; }
.exp-near    { color: var(--warn);    font-size: .8rem; font-weight: 600; }
.exp-expired { color: var(--warn);    font-size: .8rem; font-weight: 700; }
.exp-caution { color: var(--caution); font-size: .8rem; font-weight: 600; }

/* ── ACTIONS ── */
.action-cell { display: flex; gap: .5rem; }
.btn-edit, .btn-del {
    display: inline-flex; align-items: center; gap: .35rem;
    font-family: inherit;
    font-size: .8rem;
    font-weight: 600;
    padding: .42rem .85rem;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: transform .12s, box-shadow .12s;
}
.btn-edit {
    background: #e8f4ea;
    color: var(--accent);
    border: 1px solid rgba(45,106,79,.2);
}
.btn-edit:hover { background: var(--accent-lt); transform: translateY(-1px); }

.btn-del {
    background: var(--warn-lt);
    color: var(--warn);
    border: 1px solid rgba(224,122,95,.2);
}
.btn-del:hover { background: #fbd5cc; transform: translateY(-1px); }

/* ── EMPTY STATE ── */
.empty {
    padding: 5rem 2rem;
    text-align: center;
    color: var(--ink2);
}
.empty-icon { font-size: 3rem; display: block; margin-bottom: 1rem; }
.empty h3 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.2rem; font-weight: 700; margin-bottom: .5rem; }
.empty p  { font-size: .9rem; }

/* ── SUCCESS TOAST ── */
.toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: var(--accent);
    color: #fff;
    padding: .85rem 1.4rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: .9rem;
    box-shadow: 0 8px 24px rgba(45,106,79,.4);
    display: flex;
    align-items: center;
    gap: .6rem;
    z-index: 999;
    animation: slideUp .35s ease both;
    opacity: 1;
    transition: opacity .4s;
}
.toast.hide { opacity: 0; }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── ANIMATIONS ── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

tbody tr {
    animation: fadeUp .4s ease both;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .stats { grid-template-columns: 1fr 1fr; }
    .stats .stat-card:last-child { grid-column: 1/-1; }
    .nav { padding: 0 1rem; }
    .main { padding: 1.25rem 1rem; }
    #searchInput { width: 160px; }
    #searchInput:focus { width: 200px; }
    td:nth-child(5), th:nth-child(5),
    td:nth-child(7), th:nth-child(7) { display: none; }
}
@media (max-width: 600px) {
    .stats { grid-template-columns: 1fr; }
    .stats .stat-card:last-child { grid-column: 1; }
    td:nth-child(4), th:nth-child(4) { display: none; }
    .brand-tag { display: none; }
}
</style>
</head>
<body>

<!-- ── NAV ── -->
<nav class="nav">
    <div class="brand">
        <div class="brand-pill">💊</div>
        <span class="brand-name">MedInventory</span>
        <span class="brand-tag">System</span>
    </div>
    <div class="nav-right">
        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Search medicine, category…" autocomplete="off"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <a href="add.php" class="btn-add">＋ Add Medicine</a>
    </div>
</nav>

<!-- ── MAIN ── -->
<div class="main">

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon green">🧾</div>
            <div>
                <div class="stat-label">Total Items</div>
                <div class="stat-value" id="statItems"><?= $total_items ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">📦</div>
            <div>
                <div class="stat-label">Total Units</div>
                <div class="stat-value" id="statQty"><?= number_format($total_qty) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">💵</div>
            <div>
                <div class="stat-label">Inventory Value</div>
                <div class="stat-value" id="statValue">₱<?= number_format($total_value, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- SECTION HEADER -->
    <div class="section-head">
        <span class="section-title">Medicine List</span>
        <span class="count-badge" id="countBadge"><?= $total_items ?> records</span>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
        <div class="empty">
            <span class="empty-icon">🔍</span>
            <h3>No medicines found</h3>
            <p>Try a different search or <a href="add.php" style="color:var(--accent)">add a new item</a>.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Expiry</th>
                    <th>Supplier</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php
            $maxQty = max(array_column($rows, 'quantity'), 1);
            foreach ($rows as $i => $row):
                [$expClass, $expLabel] = expiryStatus($row['expiry_date'] ?? '');
                $icon    = categoryIcon($row['category'] ?? '');
                $quantities = array_column($rows, 'quantity');
                $maxQty = !empty($quantities) ? max(max($quantities), 1) : 1;
                $qtyLow  = $row['quantity'] < 10 ? 'qty-low' : '';
                $delay   = min($i * 40, 400);
            ?>
            <tr data-name="<?= htmlspecialchars(strtolower($row['mname'])) ?>"
                data-cat="<?= htmlspecialchars(strtolower($row['category'])) ?>"
                data-sup="<?= htmlspecialchars(strtolower($row['supplier'])) ?>"
                style="animation-delay:<?= $delay ?>ms">
                <td style="color:var(--ink3);font-size:.8rem;font-weight:600"><?= $row['id'] ?></td>

                <td>
                    <div class="med-cell">
                        <div class="med-avatar"><?= $icon ?></div>
                        <div>
                            <div class="med-name"><?= htmlspecialchars($row['mname']) ?></div>
                            <div class="med-id">ID #<?= $row['id'] ?></div>
                        </div>
                    </div>
                </td>

                <td><span class="cat-badge"><?= $icon ?> <?= htmlspecialchars($row['category']) ?></span></td>

                <td>
                    <div class="qty-wrap <?= $qtyLow ?>">
                        <span class="qty-num"><?= number_format($row['quantity']) ?></span>
                        <div class="qty-bar-bg"><div class="qty-bar" style="width:<?= $qtyPct ?>%"></div></div>
                    </div>
                </td>

                <td class="price-cell">₱<?= number_format($row['price'], 2) ?></td>

                <td>
                    <?php if (!empty($row['expiry_date'])): ?>
                        <div class="exp-<?= $expClass ?>"><?= htmlspecialchars($row['expiry_date']) ?></div>
                        <?php if ($expLabel): ?><div class="exp-<?= $expClass ?>"><?= $expLabel ?></div><?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--ink3)">—</span>
                    <?php endif; ?>
                </td>

                <td style="color:var(--ink2)"><?= htmlspecialchars($row['supplier']) ?></td>

                <td>
                    <div class="action-cell">
                        <a class="btn-edit" href="edit.php?id=<?= $row['id'] ?>">✏ Edit</a>
                        <a class="btn-del" href="delete.php?id=<?= $row['id'] ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['mname'])) ?>?')">✕ Del</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="noResults" style="display:none;padding:4rem 2rem;text-align:center;color:var(--ink2)">
            <span style="font-size:2rem;display:block;margin-bottom:.75rem">🔍</span>
            No medicines match "<span id="noTerm"></span>"
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if (isset($_GET['updated'])): ?>
<div class="toast" id="toast">✔ Record updated successfully</div>
<?php endif; ?>

<script>
// ── LIVE CLIENT-SIDE SEARCH ──
const input   = document.getElementById('searchInput');
const rows    = document.querySelectorAll('#tableBody tr');
const badge   = document.getElementById('countBadge');
const noRes   = document.getElementById('noResults');
const noTerm  = document.getElementById('noTerm');

// Stats elements
const statItems = document.getElementById('statItems');
const statQty   = document.getElementById('statQty');
const statValue = document.getElementById('statValue');

function filterTable(q) {
    q = q.toLowerCase().trim();
    let visible = 0, totalQty = 0, totalVal = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const cat  = row.dataset.cat  || '';
        const sup  = row.dataset.sup  || '';
        const hit  = !q || name.includes(q) || cat.includes(q) || sup.includes(q);
        row.style.display = hit ? '' : 'none';

        if (hit) {
            visible++;
            // read qty and price from cells
            const qty   = parseFloat(row.cells[3].querySelector('.qty-num')?.textContent.replace(/,/g,'') || 0);
            const price = parseFloat(row.cells[4].textContent.replace(/[₱,]/g, '') || 0);
            totalQty += qty;
            totalVal += qty * price;
        }
    });

    badge.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
    statItems.textContent = visible;
    statQty.textContent   = totalQty.toLocaleString();
    statValue.textContent = '₱' + Math.round(totalVal).toLocaleString();

    if (noRes) {
        noRes.style.display = visible === 0 ? 'block' : 'none';
        if (noTerm) noTerm.textContent = q;
    }

    // update URL without reload
    const url = new URL(window.location);
    q ? url.searchParams.set('search', q) : url.searchParams.delete('search');
    history.replaceState({}, '', url);
}

if (input) {
    let debounce;
    input.addEventListener('input', e => {
        clearTimeout(debounce);
        debounce = setTimeout(() => filterTable(e.target.value), 120);
    });
    // run on load if pre-filled
    if (input.value) filterTable(input.value);
}

// ── AUTO-HIDE TOAST ──
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => { toast.classList.add('hide'); }, 3000);
    setTimeout(() => toast.remove(), 3500);
}
</script>
</body>
</html>