<?php
include 'db.php';

$success = $error = '';

if (isset($_POST['submit'])) {

    // Sanitize inputs
    $mname       = trim($_POST['mname']);
    $category    = trim($_POST['category']);
    $quantity    = intval($_POST['quantity']);
    $price       = floatval($_POST['price']);
    $expiry_date = $_POST['expiry_date'];
    $supplier    = trim($_POST['supplier']);

    // Basic server-side validation
    if (empty($mname) || empty($category) || empty($supplier)) {
        $error = "Please fill in all required fields.";
    } elseif ($quantity < 0 || $price < 0) {
        $error = "Quantity and price must not be negative.";
    } else {

        // IMAGE UPLOAD (optional — image column accepts NULL)
        $image    = '';
        $hasFile  = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

        if ($hasFile) {
            $origName = $_FILES['image']['name'];
            $tmpPath  = $_FILES['image']['tmp_name'];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, JPEG, PNG, or WEBP files are allowed.";
            } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
                $error = "Image must be under 3 MB.";
            } else {
                // Use a unique filename to prevent collisions / overwrites
                $image  = uniqid('med_', true) . '.' . $ext;
                $folder = "img/" . $image;

                if (!is_dir('img')) mkdir('img', 0755, true);

                if (!move_uploaded_file($tmpPath, $folder)) {
                    $error = "Failed to upload image. Check folder permissions.";
                    $image = '';
                }
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare(
                "INSERT INTO inventory (mname, category, quantity, price, expiry_date, supplier, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssissss", $mname, $category, $quantity, $price, $expiry_date, $supplier, $image);

            if ($stmt->execute()) {
                $success = "Medicine <strong>" . htmlspecialchars($mname) . "</strong> added successfully!";
                // Clear fields after success
                $mname = $category = $supplier = $expiry_date = '';
                $quantity = $price = 0;
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Medicine · MedInventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
:root {
    --bg:        #f7f5f0;
    --surface:   #ffffff;
    --ink:       #1a1814;
    --ink2:      #6b6560;
    --ink3:      #b0aaa3;
    --accent:    #2d6a4f;
    --accent2:   #52b788;
    --accent-lt: #d8f3dc;
    --warn:      #e07a5f;
    --warn-lt:   #fde8e3;
    --border:    #e8e4dc;
    --shadow:    0 1px 3px rgba(26,24,20,.06), 0 4px 16px rgba(26,24,20,.08);
    --shadow-lg: 0 4px 8px rgba(26,24,20,.07), 0 16px 40px rgba(26,24,20,.12);
    --radius:    14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Instrument Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Subtle grid texture */
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
        linear-gradient(rgba(45,106,79,.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(45,106,79,.035) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none; z-index: 0;
}

/* ── NAV ── */
.nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(247,245,240,.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex; align-items: center; justify-content: space-between;
    height: 64px;
}
.brand { display: flex; align-items: center; gap: .7rem; text-decoration: none; }
.brand-pill {
    background: var(--accent); color: #fff; font-size: 1.1rem;
    width: 36px; height: 36px; border-radius: 10px;
    display: grid; place-items: center;
    box-shadow: 0 2px 8px rgba(45,106,79,.35);
}
.brand-name {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800; font-size: 1.1rem; letter-spacing: -.02em; color: var(--ink);
}
.back-link {
    display: inline-flex; align-items: center; gap: .45rem;
    font-size: .85rem; font-weight: 500; color: var(--ink2);
    text-decoration: none; padding: .45rem 1rem;
    border: 1px solid var(--border); border-radius: 9px;
    background: transparent; transition: all .18s;
}
.back-link:hover { color: var(--ink); border-color: rgba(45,106,79,.4); background: var(--accent-lt); }

/* ── PAGE LAYOUT ── */
.page {
    position: relative; z-index: 1;
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center;
    padding: 3rem 1.5rem 5rem;
}

/* ── PAGE HEADER ── */
.page-header {
    text-align: center;
    margin-bottom: 2.5rem;
    animation: fadeDown .55s ease both;
}
.page-header h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 2rem; font-weight: 800; letter-spacing: -.03em;
    color: var(--ink); line-height: 1.15;
}
.page-header p { font-size: .9rem; color: var(--ink2); margin-top: .4rem; }

/* ── CARD ── */
.card {
    width: 100%; max-width: 640px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    animation: fadeUp .6s .08s ease both;
}

/* Accent stripe */
.card::before {
    content: '';
    display: block; height: 4px;
    background: linear-gradient(90deg, var(--accent), var(--accent2), #a7c4b5);
}

.card-body { padding: 2.25rem 2.5rem 2.5rem; }

/* ── ALERTS ── */
.alert {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .9rem 1.1rem; border-radius: 10px;
    margin-bottom: 1.75rem; font-size: .875rem; font-weight: 500;
    line-height: 1.5;
}
.alert-success { background: var(--accent-lt); border: 1px solid rgba(45,106,79,.25); color: var(--accent); }
.alert-error   { background: var(--warn-lt);   border: 1px solid rgba(224,122,95,.3);  color: var(--warn);   animation: shake .4s ease; }
.alert-icon    { font-size: 1.1rem; flex-shrink: 0; margin-top: .05rem; }

/* ── FORM GRID ── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}
.field-full { grid-column: 1 / -1; }

/* ── FIELD ── */
.field { display: flex; flex-direction: column; gap: .45rem; }
.field-label {
    font-size: .72rem; font-weight: 700;
    letter-spacing: .07em; text-transform: uppercase;
    color: var(--ink2);
    display: flex; align-items: center; gap: .4rem;
}
.req { color: var(--accent); }

.field-input {
    width: 100%;
    background: #faf9f7;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: .78rem 1rem;
    color: var(--ink);
    font-family: 'Instrument Sans', sans-serif;
    font-size: .9rem;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    -webkit-appearance: none;
}
.field-input::placeholder { color: var(--ink3); }
.field-input:hover { background: #f5f4f1; border-color: #ccc8be; }
.field-input:focus {
    border-color: var(--accent);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(45,106,79,.12);
}

/* currency wrapper */
.input-wrap { position: relative; }
.input-prefix {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    color: var(--accent2); font-weight: 700; font-size: .9rem; pointer-events: none;
}
.input-wrap .field-input { padding-left: 1.85rem; }

/* date picker */
input[type="date"].field-input::-webkit-calendar-picker-indicator {
    opacity: .45; cursor: pointer;
    filter: sepia(.5) saturate(4) hue-rotate(110deg);
}

/* ── IMAGE UPLOAD ZONE ── */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    background: #faf9f7;
    padding: 1.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    overflow: hidden;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: var(--accent2);
    background: var(--accent-lt);
}
.upload-zone input[type="file"] {
    position: absolute; inset: 0;
    opacity: 0; cursor: pointer; font-size: 0;
}
.upload-icon { font-size: 2rem; display: block; margin-bottom: .5rem; }
.upload-label {
    font-size: .875rem; font-weight: 600; color: var(--ink2);
    display: block; margin-bottom: .2rem;
}
.upload-sub { font-size: .75rem; color: var(--ink3); }

/* Preview */
.preview-wrap {
    margin-top: 1rem;
    display: none;
    position: relative;
}
.preview-wrap.show { display: block; }
#imgPreview {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 10px;
    display: block;
    border: 1.5px solid var(--border);
}
.preview-remove {
    position: absolute; top: .5rem; right: .5rem;
    background: rgba(26,24,20,.65);
    color: #fff; border: none; border-radius: 50%;
    width: 28px; height: 28px;
    display: grid; place-items: center;
    font-size: .85rem; cursor: pointer;
    transition: background .15s;
}
.preview-remove:hover { background: var(--warn); }

/* ── DIVIDER ── */
.divider {
    border: none; border-top: 1px solid var(--border);
    position: relative; margin: 1.75rem 0 1.5rem;
}
.divider::after {
    content: attr(data-label);
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: var(--surface);
    padding: 0 .9rem;
    font-size: .65rem; font-weight: 700;
    letter-spacing: .1em; color: var(--ink3);
    text-transform: uppercase;
}

/* ── SUBMIT ── */
.btn-submit {
    width: 100%;
    display: flex; align-items: center; justify-content: center; gap: .55rem;
    background: linear-gradient(135deg, var(--accent) 0%, #1f5c3e 100%);
    color: #fff;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 1rem; font-weight: 700;
    padding: .95rem 1.5rem;
    border: none; border-radius: 11px;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(45,106,79,.35), inset 0 1px 0 rgba(255,255,255,.12);
    transition: transform .15s, box-shadow .15s;
    position: relative; overflow: hidden;
    margin-top: 1.75rem;
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(45,106,79,.45), inset 0 1px 0 rgba(255,255,255,.12);
}
.btn-submit:active { transform: translateY(0); }
.btn-submit .ripple {
    position: absolute; border-radius: 50%;
    background: rgba(255,255,255,.25);
    transform: scale(0);
    animation: ripple .55s linear;
    pointer-events: none;
}

/* ── PROGRESS BAR ── */
.progress-bar { height: 3px; background: var(--border); }
.progress-fill {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
    transition: width .3s ease;
}

/* ── FOOTER LINK ── */
.form-footer {
    text-align: center; margin-top: 1.5rem;
    font-size: .875rem; color: var(--ink2);
    animation: fadeUp .6s .2s ease both;
}
.form-footer a { color: var(--accent); text-decoration: none; font-weight: 600; }
.form-footer a:hover { text-decoration: underline; }

/* ── FIELD ANIMATIONS ── */
.field { animation: fadeUp .45s ease both; }
.field:nth-child(1) { animation-delay: .1s; }
.field:nth-child(2) { animation-delay: .14s; }
.field:nth-child(3) { animation-delay: .18s; }
.field:nth-child(4) { animation-delay: .22s; }
.field:nth-child(5) { animation-delay: .26s; }
.field:nth-child(6) { animation-delay: .30s; }
.field:nth-child(7) { animation-delay: .34s; }

/* ── KEYFRAMES ── */
@keyframes fadeDown { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp   { from { opacity:0; transform:translateY(18px);  } to { opacity:1; transform:translateY(0); } }
@keyframes shake    {
    0%,100% { transform:translateX(0); }
    20%     { transform:translateX(-6px); }
    40%     { transform:translateX(6px); }
    60%     { transform:translateX(-4px); }
    80%     { transform:translateX(4px); }
}
@keyframes ripple   { to { transform:scale(4); opacity:0; } }

/* ── RESPONSIVE ── */
@media (max-width: 560px) {
    .form-grid { grid-template-columns: 1fr; }
    .field-full { grid-column: 1; }
    .card-body { padding: 1.75rem 1.25rem; }
    .page-header h1 { font-size: 1.6rem; }
    .nav { padding: 0 1rem; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a href="index.php" class="brand">
        <div class="brand-pill">💊</div>
        <span class="brand-name">MedInventory</span>
    </a>
    <a href="index.php" class="back-link">← Back to List</a>
</nav>

<!-- PAGE -->
<div class="page">

    <div class="page-header">
        <h1>➕ Add New Medicine</h1>
        <p>Fill in the details below to register a medicine to the inventory.</p>
    </div>

    <div class="card">
        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>

        <div class="card-body">

            <!-- ALERTS -->
            <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✔</span>
                <span><?= $success ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST" enctype="multipart/form-data" id="addForm" novalidate>

                <div class="form-grid">

                    <!-- NAME -->
                    <div class="field field-full">
                        <label class="field-label">
                            💊 Medicine Name <span class="req">*</span>
                        </label>
                        <input class="field-input" type="text" name="mname"
                               placeholder="e.g. Paracetamol 500mg"
                               value="<?= htmlspecialchars($mname ?? '') ?>" required>
                    </div>

                    <!-- CATEGORY -->
                    <div class="field">
                        <label class="field-label">
                            🏷 Category <span class="req">*</span>
                        </label>
                        <input class="field-input" type="text" name="category"
                               placeholder="e.g. Analgesic"
                               value="<?= htmlspecialchars($category ?? '') ?>" required>
                    </div>

                    <!-- SUPPLIER -->
                    <div class="field">
                        <label class="field-label">
                            🏭 Supplier <span class="req">*</span>
                        </label>
                        <input class="field-input" type="text" name="supplier"
                               placeholder="e.g. PharmaCo Inc."
                               value="<?= htmlspecialchars($supplier ?? '') ?>" required>
                    </div>

                    <!-- QUANTITY -->
                    <div class="field">
                        <label class="field-label">📦 Quantity</label>
                        <input class="field-input" type="number" name="quantity"
                               min="0" placeholder="0"
                               value="<?= isset($quantity) && $quantity > 0 ? $quantity : '' ?>">
                    </div>

                    <!-- PRICE -->
                    <div class="field">
                        <label class="field-label">💰 Price</label>
                        <div class="input-wrap">
                            <span class="input-prefix">₱</span>
                            <input class="field-input" type="number" name="price"
                                   min="0" step="0.01" placeholder="0.00"
                                   value="<?= isset($price) && $price > 0 ? $price : '' ?>">
                        </div>
                    </div>

                    <!-- EXPIRY DATE -->
                    <div class="field field-full">
                        <label class="field-label">📅 Expiry Date</label>
                        <input class="field-input" type="date" name="expiry_date"
                               value="<?= htmlspecialchars($expiry_date ?? '') ?>">
                    </div>

                </div>

                <hr class="divider" data-label="Medicine Photo (Optional)">

                <!-- IMAGE UPLOAD -->
                <div class="field">
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="image" id="imageInput"
                               accept=".jpg,.jpeg,.png,.webp"
                               onchange="handleImageSelect(event)">
                        <span class="upload-icon">📷</span>
                        <span class="upload-label">Click or drag &amp; drop an image</span>
                        <span class="upload-sub">JPG, PNG, WEBP · Max 3 MB · Optional</span>
                    </div>

                    <!-- PREVIEW -->
                    <div class="preview-wrap" id="previewWrap">
                        <img id="imgPreview" src="" alt="Preview">
                        <button type="button" class="preview-remove" onclick="removeImage()" title="Remove image">✕</button>
                    </div>
                </div>

                <!-- SUBMIT -->
                <button type="submit" name="submit" class="btn-submit" id="submitBtn">
                    ＋ Add to Inventory
                </button>

            </form>
        </div>
    </div>

    <div class="form-footer">
        Want to see all medicines?
        <a href="index.php">View Medicine List →</a>
    </div>

</div>

<script>
// ── LIVE PROGRESS BAR ──
const inputs     = document.querySelectorAll('.field-input');
const fill       = document.getElementById('progressFill');
const required   = ['mname','category','supplier']; // required field names

function updateProgress() {
    const total  = inputs.length;
    const filled = [...inputs].filter(f => f.value.trim() !== '').length;
    fill.style.width = (filled / total * 100) + '%';
}
inputs.forEach(f => f.addEventListener('input', updateProgress));

// ── IMAGE PREVIEW ──
function handleImageSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 3 * 1024 * 1024) {
        alert('Image is too large. Please choose a file under 3 MB.');
        e.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = ev => {
        document.getElementById('imgPreview').src = ev.target.result;
        document.getElementById('previewWrap').classList.add('show');
        document.getElementById('uploadZone').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    document.getElementById('imageInput').value = '';
    document.getElementById('imgPreview').src   = '';
    document.getElementById('previewWrap').classList.remove('show');
    document.getElementById('uploadZone').style.display = '';
}

// ── DRAG & DROP ──
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        // Inject into input
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('imageInput').files = dt.files;
        handleImageSelect({ target: { files: [file], value: '' } });
    }
});

// ── RIPPLE ──
document.getElementById('submitBtn').addEventListener('click', function(e) {
    const btn    = this;
    const circle = document.createElement('span');
    const rect   = btn.getBoundingClientRect();
    const size   = Math.max(rect.width, rect.height);
    circle.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;
    circle.classList.add('ripple');
    btn.appendChild(circle);
    setTimeout(() => circle.remove(), 600);
});

// ── CLIENT-SIDE VALIDATION ──
document.getElementById('addForm').addEventListener('submit', function(e) {
    let valid = true;
    required.forEach(name => {
        const el = this.querySelector(`[name="${name}"]`);
        if (!el || !el.value.trim()) {
            valid = false;
            el.style.borderColor = '#e07a5f';
            el.style.boxShadow   = '0 0 0 3px rgba(224,122,95,.15)';
            el.addEventListener('input', () => {
                el.style.borderColor = '';
                el.style.boxShadow   = '';
            }, { once: true });
        }
    });
    if (!valid) e.preventDefault();
});
</script>
</body>
</html>