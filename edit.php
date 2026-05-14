<?php
include 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header("Location: index.php"); exit(); }

$error   = '';
$success = false;

// GET DATA
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();

if (!$row) { header("Location: index.php"); exit(); }

// UPDATE DATA
if (isset($_POST['update'])) {
    $mname       = trim($_POST['mname']);
    $category    = trim($_POST['category']);
    $quantity    = intval($_POST['quantity']);
    $price       = floatval($_POST['price']);
    $expiry_date = $_POST['expiry_date'];
    $supplier    = trim($_POST['supplier']);
    $image_path  = $row['image'] ?? ''; // keep existing by default

    // ── Handle image upload ──
    if (!empty($_FILES['image']['name'])) {
        $file    = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxSize = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            $error = 'Image must be JPG, PNG, WEBP, or GIF.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Image must be under 5MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed. Please try again.';
        } else {
            $uploadDir = 'uploads/medicines/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Delete old image file if it was a local upload
            if (!empty($row['image']) && file_exists($row['image'])) {
                @unlink($row['image']);
            }

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-z0-9_-]/', '_', strtolower($mname));
            $filename = $safeName . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $image_path = $destPath;
            } else {
                $error = 'Could not save image. Check folder permissions.';
            }
        }
    }

    // ── Handle remove image ──
    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        if (!empty($row['image']) && file_exists($row['image'])) {
            @unlink($row['image']);
        }
        $image_path = '';
    }

    // ── Validate & save ──
    if (empty($error)) {
        if (empty($mname) || empty($category) || empty($supplier)) {
            $error = "Please fill in all required fields.";
        } elseif ($quantity < 0) {
            $error = "Quantity cannot be negative.";
        } elseif ($price < 0) {
            $error = "Price cannot be negative.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE inventory SET mname=?, category=?, quantity=?, price=?, expiry_date=?, supplier=?, image=? WHERE id=?"
            );
            $stmt->bind_param("ssissssi", $mname, $category, $quantity, $price, $expiry_date, $supplier, $image_path, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: index.php?updated=1");
                exit();
            } else {
                $error = "Error updating record: " . $conn->error;
                $stmt->close();
            }
        }
    }
}

// Current image (validate it exists on disk)
$currentImg = '';
if (!empty($row['image']) && file_exists($row['image'])) {
    $currentImg = $row['image'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Medicine — MedInventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
:root {
    --bg:        #0a0a0f;
    --surface:   #111118;
    --card:      #16161f;
    --border:    rgba(255,255,255,0.07);
    --border-hi: rgba(139,92,246,0.5);
    --accent:    #8b5cf6;
    --accent2:   #06b6d4;
    --accent3:   #f59e0b;
    --text:      #f0eeff;
    --muted:     #7b7a99;
    --danger:    #f87171;
    --success:   #34d399;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 10% -10%, rgba(139,92,246,.18) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 110%, rgba(6,182,212,.12) 0%, transparent 55%),
        radial-gradient(ellipse 50% 40% at 50% 50%, rgba(245,158,11,.05) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
}

.orb {
    position: fixed; border-radius: 50%; filter: blur(80px);
    opacity: .18; pointer-events: none; z-index: 0;
    animation: drift 12s ease-in-out infinite alternate;
}
.orb-1 { width:400px;height:400px;background:var(--accent); top:-120px;left:-100px;animation-duration:14s; }
.orb-2 { width:300px;height:300px;background:var(--accent2);bottom:-80px;right:-80px;animation-duration:10s;animation-delay:-4s; }
.orb-3 { width:200px;height:200px;background:var(--accent3);top:50%;right:10%;animation-duration:18s;animation-delay:-7s; }

@keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(30px,20px) scale(1.08); }
}

.wrapper {
    position: relative; z-index: 1;
    min-height: 100vh;
    display: flex; flex-direction: column; align-items: center;
    padding: 2rem 1rem 4rem;
}

header {
    width: 100%; max-width: 780px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.5rem 0 2.5rem;
    animation: fadeDown .6s ease both;
}

.logo-mark { display: flex; align-items: center; gap: .75rem; }
.logo-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 10px; display: grid; place-items: center;
    font-size: 1.1rem; box-shadow: 0 0 20px rgba(139,92,246,.4);
}
.logo-text {
    font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem;
    background: linear-gradient(90deg, var(--text), var(--muted));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

.back-btn {
    display: flex; align-items: center; gap: .5rem;
    font-size: .85rem; font-weight: 500; color: var(--muted);
    text-decoration: none; padding: .5rem 1rem;
    border: 1px solid var(--border); border-radius: 8px;
    background: rgba(255,255,255,.02); transition: all .2s;
}
.back-btn:hover { color: var(--text); border-color: var(--border-hi); background: rgba(139,92,246,.08); }

/* CARD */
.card {
    width: 100%; max-width: 780px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 20px; overflow: hidden;
    box-shadow: 0 0 0 1px rgba(255,255,255,.03), 0 40px 80px rgba(0,0,0,.5), 0 0 60px rgba(139,92,246,.06);
    animation: fadeUp .7s ease .1s both;
}

.card-header {
    padding: 2rem 2.5rem 1.75rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, rgba(139,92,246,.06) 0%, transparent 60%);
    display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
}
.card-title {
    font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; line-height: 1.15;
}
.card-title span {
    display: block; font-size: .85rem; font-weight: 400;
    font-family: 'DM Sans', sans-serif; color: var(--muted); margin-top: .3rem;
}
.item-id-badge {
    font-size: .75rem; font-weight: 600; color: var(--accent);
    background: rgba(139,92,246,.12); border: 1px solid rgba(139,92,246,.25);
    padding: .35rem .75rem; border-radius: 100px; white-space: nowrap;
    letter-spacing: .03em; text-transform: uppercase;
}

.progress-bar { height: 3px; background: var(--border); overflow: hidden; }
.progress-fill {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
    transition: width .3s ease;
}

.card-body { padding: 2.5rem; }

/* ALERT */
.alert {
    display: flex; align-items: center; gap: .75rem;
    padding: .9rem 1.2rem; border-radius: 10px;
    margin-bottom: 2rem; font-size: .9rem; font-weight: 500;
    animation: shake .4s ease;
}
.alert-error { background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); color: var(--danger); }

@keyframes shake {
    0%,100% { transform:translateX(0); }
    20%     { transform:translateX(-6px); }
    40%     { transform:translateX(6px); }
    60%     { transform:translateX(-4px); }
    80%     { transform:translateX(4px); }
}

/* ══════════════════════════════════════
   IMAGE SECTION
══════════════════════════════════════ */
.img-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}
.section-label {
    font-size: .72rem; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--muted);
    margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem;
}

/* Current image display */
.current-img-wrap {
    position: relative;
    display: inline-block;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--border-hi);
    box-shadow: 0 0 30px rgba(139,92,246,.2);
    margin-bottom: 1rem;
    max-width: 100%;
    animation: fadeUp .5s ease both;
}
.current-img-wrap img {
    display: block;
    max-width: 320px;
    max-height: 220px;
    width: 100%;
    object-fit: contain;
    background: rgba(255,255,255,.03);
}
.current-img-label {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,.8), transparent);
    padding: .6rem .85rem .5rem;
    font-size: .72rem; font-weight: 600; color: rgba(255,255,255,.7);
    letter-spacing: .04em;
}
.btn-remove-img {
    position: absolute; top: .5rem; right: .5rem;
    background: rgba(248,113,113,.85); color: #fff;
    border: none; border-radius: 8px;
    padding: .3rem .65rem; font-size: .75rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: .3rem;
    transition: background .2s; backdrop-filter: blur(6px);
}
.btn-remove-img:hover { background: var(--danger); }

/* No image state */
.no-img-placeholder {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: .5rem; padding: 1.5rem 2rem;
    border: 1px dashed rgba(255,255,255,.12); border-radius: 12px;
    color: var(--muted); font-size: .85rem; margin-bottom: 1rem;
    background: rgba(255,255,255,.02);
}
.no-img-placeholder .emoji { font-size: 2rem; }

/* Upload zone */
.upload-zone {
    position: relative;
    border: 2px dashed rgba(255,255,255,.1); border-radius: 12px;
    padding: 1.5rem; text-align: center; cursor: pointer;
    background: rgba(255,255,255,.02);
    transition: border-color .2s, background .2s;
}
.upload-zone:hover,
.upload-zone.dragover {
    border-color: var(--accent);
    background: rgba(139,92,246,.06);
}
.upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}
.upload-zone-icon { font-size: 1.6rem; display: block; margin-bottom: .4rem; }
.upload-zone-text { font-size: .85rem; color: var(--muted); }
.upload-zone-text strong { color: var(--accent); }
.upload-zone-hint { font-size: .72rem; color: rgba(255,255,255,.25); margin-top: .25rem; }

/* New image preview */
.new-preview {
    display: none; margin-top: 1rem;
    border-radius: 12px; overflow: hidden;
    border: 1px solid rgba(52,211,153,.35);
    position: relative;
    box-shadow: 0 0 20px rgba(52,211,153,.1);
}
.new-preview.show { display: block; }
.new-preview img { width: 100%; max-height: 220px; object-fit: contain; display: block; background: rgba(255,255,255,.03); }
.new-preview-bar {
    background: rgba(0,0,0,.7); padding: .45rem .85rem;
    display: flex; align-items: center; justify-content: space-between;
}
.new-preview-name { font-size: .75rem; color: var(--success); font-weight: 600; }
.btn-cancel-new {
    background: none; border: none; color: var(--muted);
    font-size: .8rem; cursor: pointer; padding: .15rem .4rem;
    border-radius: 4px; transition: color .15s;
}
.btn-cancel-new:hover { color: var(--danger); }

/* FORM GRID */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.field-full { grid-column: 1 / -1; }

.field { display: flex; flex-direction: column; gap: .5rem; }
.field-label {
    font-size: .75rem; font-weight: 600; letter-spacing: .08em;
    text-transform: uppercase; color: var(--muted);
    display: flex; align-items: center; gap: .4rem;
}
.field-label .required { color: var(--accent); }

.field-input {
    width: 100%; background: rgba(255,255,255,.04);
    border: 1px solid var(--border); border-radius: 10px;
    padding: .85rem 1rem; color: var(--text);
    font-family: 'DM Sans', sans-serif; font-size: .95rem; outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    -webkit-appearance: none;
}
.field-input::placeholder { color: var(--muted); opacity: .6; }
.field-input:hover { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.06); }
.field-input:focus {
    border-color: var(--accent);
    background: rgba(139,92,246,.06);
    box-shadow: 0 0 0 3px rgba(139,92,246,.15);
}
input[type="date"].field-input::-webkit-calendar-picker-indicator {
    filter: invert(.5) sepia(1) saturate(3) hue-rotate(230deg);
    cursor: pointer; opacity: .6;
}

.input-wrap { position: relative; display: flex; align-items: center; }
.input-prefix {
    position: absolute; left: 1rem;
    color: var(--accent2); font-weight: 600; font-size: .9rem;
    pointer-events: none; z-index: 1;
}
.input-wrap .field-input { padding-left: 2rem; }

/* ACTIONS */
.actions {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 1rem; margin-top: 2.5rem; padding-top: 2rem;
    border-top: 1px solid var(--border);
}
.btn {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .85rem 1.75rem; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
    cursor: pointer; border: none; text-decoration: none;
    transition: all .2s; position: relative; overflow: hidden;
}
.btn-ghost {
    background: transparent; color: var(--muted); border: 1px solid var(--border);
}
.btn-ghost:hover { color: var(--text); border-color: rgba(255,255,255,.15); background: rgba(255,255,255,.04); }
.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    color: #fff;
    box-shadow: 0 4px 20px rgba(139,92,246,.35), inset 0 1px 0 rgba(255,255,255,.15);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(139,92,246,.45), inset 0 1px 0 rgba(255,255,255,.15); }
.btn-primary:active { transform: translateY(0); }
.ripple {
    position: absolute; border-radius: 50%; background: rgba(255,255,255,.25);
    transform: scale(0); animation: ripple .55s linear; pointer-events: none;
}
@keyframes ripple { to { transform: scale(4); opacity: 0; } }

@keyframes fadeDown { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp   { from { opacity:0; transform:translateY(24px);  } to { opacity:1; transform:translateY(0); } }

.field { animation: fadeUp .5s ease both; }
.field:nth-child(1) { animation-delay:.15s; }
.field:nth-child(2) { animation-delay:.20s; }
.field:nth-child(3) { animation-delay:.25s; }
.field:nth-child(4) { animation-delay:.30s; }
.field:nth-child(5) { animation-delay:.35s; }
.field:nth-child(6) { animation-delay:.40s; }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
    .field-full { grid-column: 1; }
    .card-body { padding: 1.5rem; }
    .card-header { padding: 1.5rem; }
    .actions { flex-direction: column-reverse; }
    .btn { width: 100%; justify-content: center; }
    .current-img-wrap img { max-width: 100%; }
}
</style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="wrapper">
    <header>
        <div class="logo-mark">
            <div class="logo-icon">📦</div>
            <span class="logo-text">MedInventory</span>
        </div>
        <a href="index.php" class="back-btn">← Back to List</a>
    </header>

    <div class="card">
        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>

        <div class="card-header">
            <div class="card-title">
                Edit Medicine
                <span>Update the details below and save changes.</span>
            </div>
            <div class="item-id-badge"># ID <?= htmlspecialchars($id) ?></div>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="editForm" novalidate>
                <!-- Hidden flag for image removal -->
                <input type="hidden" name="remove_image" id="removeImageFlag" value="0">

                <!-- ══════════════════════════════
                     IMAGE SECTION
                ══════════════════════════════ -->
                <div class="img-section">
                    <div class="section-label">📷 Medicine Photo</div>

                    <!-- Current image (if exists) -->
                    <div id="currentImgArea">
                        <?php if ($currentImg): ?>
                        <div class="current-img-wrap" id="currentImgWrap">
                            <img src="<?= htmlspecialchars($currentImg) ?>"
                                 alt="<?= htmlspecialchars($row['mname']) ?>">
                            <div class="current-img-label">Current photo</div>
                            <button type="button" class="btn-remove-img" id="btnRemoveImg">
                                ✕ Remove
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="no-img-placeholder" id="noImgPlaceholder">
                            <span class="emoji">🖼️</span>
                            <span>No photo yet — upload one below</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Upload zone -->
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="image" id="imageInput"
                               accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="upload-zone-icon">📤</span>
                        <div class="upload-zone-text">
                            <strong>Click to upload</strong> or drag & drop
                        </div>
                        <div class="upload-zone-hint">JPG · PNG · WEBP · GIF &nbsp;·&nbsp; max 5 MB</div>
                    </div>

                    <!-- New image preview -->
                    <div class="new-preview" id="newPreview">
                        <img id="newPreviewImg" src="" alt="New preview">
                        <div class="new-preview-bar">
                            <span class="new-preview-name" id="newPreviewName"></span>
                            <button type="button" class="btn-cancel-new" id="cancelNew">✕ Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════
                     FORM FIELDS
                ══════════════════════════════ -->
                <div class="form-grid">

                    <div class="field field-full">
                        <label class="field-label" for="mname">
                            💊 Medicine Name <span class="required">*</span>
                        </label>
                        <input class="field-input" type="text" id="mname" name="mname"
                               value="<?= htmlspecialchars($row['mname']) ?>"
                               placeholder="e.g. Paracetamol 500mg" required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="category">
                            🏷️ Category <span class="required">*</span>
                        </label>
                        <input class="field-input" type="text" id="category" name="category"
                               value="<?= htmlspecialchars($row['category']) ?>"
                               placeholder="e.g. Analgesic" required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="supplier">
                            🏭 Supplier <span class="required">*</span>
                        </label>
                        <input class="field-input" type="text" id="supplier" name="supplier"
                               value="<?= htmlspecialchars($row['supplier']) ?>"
                               placeholder="e.g. PharmaCo Inc." required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="quantity">📦 Quantity</label>
                        <input class="field-input" type="number" id="quantity" name="quantity"
                               value="<?= htmlspecialchars($row['quantity']) ?>" min="0" placeholder="0">
                    </div>

                    <div class="field">
                        <label class="field-label" for="price">💰 Price</label>
                        <div class="input-wrap">
                            <span class="input-prefix">₱</span>
                            <input class="field-input" type="number" id="price" name="price"
                                   value="<?= htmlspecialchars($row['price']) ?>"
                                   min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="expiry_date">📅 Expiry Date</label>
                        <input class="field-input" type="date" id="expiry_date" name="expiry_date"
                               value="<?= htmlspecialchars($row['expiry_date']) ?>">
                    </div>

                </div>

                <div class="actions">
                    <a href="index.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" name="update" class="btn btn-primary" id="submitBtn">
                        <span>✦</span> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  IMAGE HANDLING
// ══════════════════════════════════════════════════════════════
const imageInput      = document.getElementById('imageInput');
const newPreview      = document.getElementById('newPreview');
const newPreviewImg   = document.getElementById('newPreviewImg');
const newPreviewName  = document.getElementById('newPreviewName');
const cancelNewBtn    = document.getElementById('cancelNew');
const uploadZone      = document.getElementById('uploadZone');
const removeImageFlag = document.getElementById('removeImageFlag');
const btnRemoveImg    = document.getElementById('btnRemoveImg');
const currentImgWrap  = document.getElementById('currentImgWrap');
const noImgPlaceholder= document.getElementById('noImgPlaceholder');

// Show preview when user picks a new file
function showNewPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        newPreviewImg.src = e.target.result;
        newPreviewName.textContent = '✔ ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
        newPreview.classList.add('show');
        uploadZone.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

imageInput.addEventListener('change', () => {
    if (imageInput.files[0]) showNewPreview(imageInput.files[0]);
});

// Cancel new selection
cancelNewBtn.addEventListener('click', () => {
    imageInput.value = '';
    newPreviewImg.src = '';
    newPreview.classList.remove('show');
    uploadZone.style.display = '';
});

// Remove current image
if (btnRemoveImg) {
    btnRemoveImg.addEventListener('click', () => {
        removeImageFlag.value = '1';
        // Hide the current image card
        if (currentImgWrap) currentImgWrap.style.display = 'none';
        // Show "no image" placeholder if it exists, or create one
        if (noImgPlaceholder) {
            noImgPlaceholder.style.display = 'flex';
        } else {
            const ph = document.createElement('div');
            ph.className = 'no-img-placeholder';
            ph.innerHTML = '<span class="emoji">🖼️</span><span>Photo removed — upload a new one below</span>';
            document.getElementById('currentImgArea').appendChild(ph);
        }
    });
}

// Drag and drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        imageInput.files = dt.files;
        showNewPreview(file);
    }
});

// ══════════════════════════════════════════════════════════════
//  PROGRESS BAR
// ══════════════════════════════════════════════════════════════
const fields = document.querySelectorAll('.field-input');
const fill   = document.getElementById('progressFill');

function updateProgress() {
    const total  = fields.length;
    const filled = [...fields].filter(f => f.value.trim() !== '').length;
    fill.style.width = (filled / total * 100) + '%';
}
fields.forEach(f => f.addEventListener('input', updateProgress));
updateProgress();

// ══════════════════════════════════════════════════════════════
//  RIPPLE ON SAVE BUTTON
// ══════════════════════════════════════════════════════════════
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

// ══════════════════════════════════════════════════════════════
//  CLIENT-SIDE VALIDATION
// ══════════════════════════════════════════════════════════════
document.getElementById('editForm').addEventListener('submit', function(e) {
    const mname    = document.getElementById('mname').value.trim();
    const category = document.getElementById('category').value.trim();
    const supplier = document.getElementById('supplier').value.trim();
    if (!mname || !category || !supplier) {
        e.preventDefault();
        [{ id:'mname',val:mname },{ id:'category',val:category },{ id:'supplier',val:supplier }]
            .forEach(({ id, val }) => {
                const el = document.getElementById(id);
                if (!val) {
                    el.style.borderColor = 'var(--danger)';
                    el.style.boxShadow   = '0 0 0 3px rgba(248,113,113,.15)';
                    el.addEventListener('input', () => { el.style.borderColor=''; el.style.boxShadow=''; }, { once: true });
                }
            });
    }
});
</script>
</body>
</html>