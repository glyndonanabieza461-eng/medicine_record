<?php
include 'db.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mname       = trim($_POST['mname'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $quantity    = (int)($_POST['quantity'] ?? 0);
    $price       = (float)($_POST['price'] ?? 0);
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $supplier    = trim($_POST['supplier'] ?? '');
    $image_path  = '';

    // Validate
    if (!$mname)    $errors[] = 'Medicine name is required.';
    if (!$category) $errors[] = 'Category is required.';
    if ($quantity < 0) $errors[] = 'Quantity cannot be negative.';
    if ($price < 0)    $errors[] = 'Price cannot be negative.';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $file     = $_FILES['image'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize  = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Image must be JPG, PNG, WEBP, or GIF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Image must be under 5MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed. Please try again.';
        } else {
            // Create uploads folder if it doesn't exist
            $uploadDir = 'uploads/medicines/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Unique filename: sanitized medicine name + timestamp
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName  = preg_replace('/[^a-z0-9_-]/', '_', strtolower($mname));
            $filename  = $safeName . '_' . time() . '.' . $ext;
            $destPath  = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Save as web-accessible path (relative to project root)
                $image_path = $destPath; // e.g. "uploads/medicines/paracetamol_1234.jpg"
            } else {
                $errors[] = 'Could not save the image. Check server permissions.';
            }
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO inventory (mname, category, quantity, price, expiry_date, supplier, image)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssissss', $mname, $category, $quantity, $price, $expiry_date, $supplier, $image_path);

        if ($stmt->execute()) {
            header('Location: index.php?added=1');
            exit();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Medicine — MedInventory</title>
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
    --radius:    14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Instrument Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}

/* NAV */
.nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(247,245,240,.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex; align-items: center; justify-content: space-between;
    height: 64px;
}
.brand { display: flex; align-items: center; gap: .7rem; text-decoration: none; color: inherit; }
.brand-pill {
    background: var(--accent); color: #fff; font-size: 1.15rem;
    width: 36px; height: 36px; border-radius: 10px;
    display: grid; place-items: center;
    box-shadow: 0 2px 8px rgba(45,106,79,.35);
}
.brand-name {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800; font-size: 1.15rem; letter-spacing: -.02em;
}
.btn-back {
    display: inline-flex; align-items: center; gap: .45rem;
    font-family: inherit; font-size: .875rem; font-weight: 600;
    padding: .5rem 1.1rem; border-radius: 10px;
    border: 1.5px solid var(--border); background: var(--surface);
    color: var(--ink2); text-decoration: none;
    transition: border-color .2s, color .2s;
}
.btn-back:hover { border-color: var(--accent2); color: var(--accent); }

/* LAYOUT */
.main {
    max-width: 860px; margin: 0 auto; padding: 2.5rem 2rem;
    animation: fadeUp .5s ease both;
}
.page-title {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800; font-size: 1.8rem; letter-spacing: -.03em;
    margin-bottom: .35rem;
}
.page-sub { color: var(--ink2); font-size: .9rem; margin-bottom: 2rem; }

/* CARD */
.card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow);
    padding: 2rem;
}

/* ERRORS */
.error-box {
    background: var(--warn-lt); border: 1px solid rgba(224,122,95,.3);
    border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;
    color: var(--warn); font-size: .875rem;
}
.error-box ul { padding-left: 1.2rem; }
.error-box li { margin-top: .25rem; }

/* FORM GRID */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}
.form-grid .full { grid-column: 1 / -1; }

.field { display: flex; flex-direction: column; gap: .45rem; }
.field label {
    font-size: .78rem; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: var(--ink2);
}
.field input,
.field select {
    padding: .65rem .9rem;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: inherit; font-size: .9rem; color: var(--ink);
    background: var(--bg); outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.field input:focus,
.field select:focus {
    border-color: var(--accent2);
    box-shadow: 0 0 0 3px rgba(82,183,136,.15);
    background: var(--surface);
}
.field input::placeholder { color: var(--ink3); }

/* ── IMAGE UPLOAD ── */
.upload-zone {
    border: 2px dashed var(--border); border-radius: 12px;
    padding: 1.75rem; text-align: center;
    cursor: pointer; background: var(--bg);
    transition: border-color .2s, background .2s;
    position: relative;
}
.upload-zone:hover,
.upload-zone.dragover {
    border-color: var(--accent2);
    background: var(--accent-lt);
}
.upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}
.upload-icon { font-size: 2rem; display: block; margin-bottom: .5rem; }
.upload-label {
    font-size: .875rem; color: var(--ink2); line-height: 1.5;
}
.upload-label strong { color: var(--accent); font-weight: 600; }
.upload-hint { font-size: .75rem; color: var(--ink3); margin-top: .3rem; }

/* Preview area */
.preview-wrap {
    display: none; margin-top: 1rem;
    border-radius: 12px; overflow: hidden;
    border: 1.5px solid var(--accent2);
    position: relative; background: #000;
    max-height: 240px;
}
.preview-wrap.show { display: block; }
.preview-wrap img {
    width: 100%; max-height: 240px;
    object-fit: contain; display: block;
}
.preview-remove {
    position: absolute; top: .5rem; right: .5rem;
    background: rgba(0,0,0,.6); color: #fff;
    border: none; border-radius: 50%;
    width: 28px; height: 28px; font-size: .85rem;
    cursor: pointer; display: grid; place-items: center;
    transition: background .15s;
}
.preview-remove:hover { background: var(--warn); }
.preview-name {
    background: rgba(0,0,0,.55); color: #fff;
    font-size: .75rem; padding: .35rem .75rem;
    font-weight: 600;
}

/* SUBMIT */
.form-actions {
    display: flex; align-items: center; justify-content: flex-end;
    gap: .75rem; margin-top: 1.75rem; padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}
.btn-cancel {
    display: inline-flex; align-items: center;
    font-family: inherit; font-size: .9rem; font-weight: 600;
    padding: .65rem 1.3rem; border-radius: 10px;
    border: 1.5px solid var(--border); background: transparent;
    color: var(--ink2); cursor: pointer; text-decoration: none;
    transition: border-color .15s;
}
.btn-cancel:hover { border-color: var(--ink3); }
.btn-submit {
    display: inline-flex; align-items: center; gap: .5rem;
    font-family: 'Bricolage Grotesque', sans-serif; font-size: .9rem; font-weight: 700;
    padding: .7rem 1.8rem; border-radius: 10px;
    border: none; background: var(--accent); color: #fff;
    cursor: pointer; box-shadow: 0 2px 8px rgba(45,106,79,.3);
    transition: transform .15s, box-shadow .15s;
}
.btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,106,79,.4); }
.btn-submit:active { transform: translateY(0); }

/* DIVIDER */
.section-divider {
    font-size: .72rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: var(--ink3);
    grid-column: 1 / -1; padding-top: .5rem;
    border-top: 1px solid var(--border); margin-top: .25rem;
}

@keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }
    .main { padding: 1.5rem 1rem; }
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
    <a href="index.php" class="btn-back">← Back to List</a>
</nav>

<!-- MAIN -->
<div class="main">
    <div class="page-title">Add New Medicine</div>
    <p class="page-sub">Fill in the details below. Fields marked * are required.</p>

    <?php if (!empty($errors)): ?>
    <div class="error-box">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data" id="addForm">

            <div class="form-grid">

                <!-- Medicine Info -->
                <div class="section-divider">Medicine Information</div>

                <div class="field full">
                    <label for="mname">Medicine Name *</label>
                    <input type="text" id="mname" name="mname"
                           placeholder="e.g. Paracetamol 500mg"
                           value="<?= htmlspecialchars($_POST['mname'] ?? '') ?>"
                           required autocomplete="off">
                </div>

                <div class="field">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">— Select category —</option>
                        <?php
                        $cats = ['Analgesic / Pain Relief','Antibiotic','Vitamin / Supplement',
                                 'Antihistamine / Allergy','Cardiovascular / Heart','Diabetes',
                                 'Antacid / Gastrointestinal','Antiviral','Antifungal','Other'];
                        $selected = $_POST['category'] ?? '';
                        foreach ($cats as $c):
                        ?>
                        <option value="<?= $c ?>" <?= $selected === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="supplier">Supplier</label>
                    <input type="text" id="supplier" name="supplier"
                           placeholder="e.g. PharmaCorp"
                           value="<?= htmlspecialchars($_POST['supplier'] ?? '') ?>">
                </div>

                <!-- Stock & Pricing -->
                <div class="section-divider">Stock & Pricing</div>

                <div class="field">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" min="0"
                           placeholder="0"
                           value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"
                           required>
                </div>

                <div class="field">
                    <label for="price">Price per Unit (₱) *</label>
                    <input type="number" id="price" name="price" min="0" step="0.01"
                           placeholder="0.00"
                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                           required>
                </div>

                <div class="field">
                    <label for="expiry_date">Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date"
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>

                <!-- Image Upload -->
                <div class="section-divider">Medicine Photo</div>

                <div class="field full">
                    <label>Upload Image <span style="color:var(--ink3);font-weight:400;text-transform:none;letter-spacing:0">(optional · JPG, PNG, WEBP, GIF · max 5MB)</span></label>

                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="image" id="imageInput"
                               accept="image/jpeg,image/png,image/webp,image/gif">
                        <span class="upload-icon">📷</span>
                        <div class="upload-label">
                            <strong>Click to upload</strong> or drag & drop a photo here
                        </div>
                        <div class="upload-hint">Recommended: clear photo of the medicine box or tablet</div>
                    </div>

                    <!-- Live preview shown as soon as user picks a file -->
                    <div class="preview-wrap" id="previewWrap">
                        <button type="button" class="preview-remove" id="removeImg" title="Remove image">✕</button>
                        <img id="previewImg" src="" alt="Preview">
                        <div class="preview-name" id="previewName"></div>
                    </div>
                </div>

            </div><!-- /form-grid -->

            <div class="form-actions">
                <a href="index.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">＋ Add Medicine</button>
            </div>

        </form>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  IMAGE PREVIEW — shows thumbnail as soon as file is chosen
// ══════════════════════════════════════════════════════════════
const imageInput  = document.getElementById('imageInput');
const previewWrap = document.getElementById('previewWrap');
const previewImg  = document.getElementById('previewImg');
const previewName = document.getElementById('previewName');
const uploadZone  = document.getElementById('uploadZone');
const removeBtn   = document.getElementById('removeImg');

function showPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result;
        previewName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        previewWrap.classList.add('show');
        uploadZone.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

imageInput.addEventListener('change', () => {
    if (imageInput.files[0]) showPreview(imageInput.files[0]);
});

// Remove / reset
removeBtn.addEventListener('click', () => {
    imageInput.value = '';
    previewImg.src   = '';
    previewWrap.classList.remove('show');
    uploadZone.style.display = '';
});

// Drag-and-drop
uploadZone.addEventListener('dragover', e => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        // Assign to the input so it's included in the form POST
        const dt = new DataTransfer();
        dt.items.add(file);
        imageInput.files = dt.files;
        showPreview(file);
    }
});
</script>
</body>
</html>