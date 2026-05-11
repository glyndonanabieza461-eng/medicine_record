<?php
include 'db.php';

// Sanitize and validate the ID input
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = false;

// GET DATA using prepared statement
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: index.php");
    exit();
}

// UPDATE DATA using prepared statement
if (isset($_POST['update'])) {
    $mname      = trim($_POST['mname']);
    $category   = trim($_POST['category']);
    $quantity   = intval($_POST['quantity']);
    $price      = floatval($_POST['price']);
    $expiry_date = $_POST['expiry_date'];
    $supplier   = trim($_POST['supplier']);

    if (empty($mname) || empty($category) || empty($supplier)) {
        $error = "Please fill in all required fields.";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative.";
    } elseif ($price < 0) {
        $error = "Price cannot be negative.";
    } else {
        $stmt = $conn->prepare("UPDATE inventory SET mname=?, category=?, quantity=?, price=?, expiry_date=?, supplier=? WHERE id=?");
        $stmt->bind_param("ssisssi", $mname, $category, $quantity, $price, $expiry_date, $supplier, $id);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Inventory Item</title>
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

        /* ── Animated background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% -10%, rgba(139,92,246,.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 110%, rgba(6,182,212,.12) 0%, transparent 55%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(245,158,11,.05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Floating orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .18;
            pointer-events: none;
            z-index: 0;
            animation: drift 12s ease-in-out infinite alternate;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--accent);  top: -120px; left: -100px; animation-duration: 14s; }
        .orb-2 { width: 300px; height: 300px; background: var(--accent2); bottom: -80px; right: -80px;  animation-duration: 10s; animation-delay: -4s; }
        .orb-3 { width: 200px; height: 200px; background: var(--accent3); top: 50%;    right: 10%;      animation-duration: 18s; animation-delay: -7s; }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.08); }
        }

        /* ── Layout ── */
        .wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem 4rem;
        }

        /* ── Header ── */
        header {
            width: 100%;
            max-width: 740px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 0 2.5rem;
            animation: fadeDown .6s ease both;
        }

        .logo-mark {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            box-shadow: 0 0 20px rgba(139,92,246,.4);
        }

        .logo-text {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: -.01em;
            background: linear-gradient(90deg, var(--text), var(--muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
            padding: .5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all .2s;
            background: rgba(255,255,255,.02);
        }
        .back-btn:hover {
            color: var(--text);
            border-color: var(--border-hi);
            background: rgba(139,92,246,.08);
        }

        /* ── Card ── */
        .card {
            width: 100%;
            max-width: 740px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(255,255,255,.03),
                0 40px 80px rgba(0,0,0,.5),
                0 0 60px rgba(139,92,246,.06);
            animation: fadeUp .7s ease .1s both;
        }

        .card-header {
            padding: 2rem 2.5rem 1.75rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(139,92,246,.06) 0%, transparent 60%);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .card-title span {
            display: block;
            font-size: .85rem;
            font-weight: 400;
            font-family: 'DM Sans', sans-serif;
            color: var(--muted);
            margin-top: .3rem;
            letter-spacing: 0;
        }

        .item-id-badge {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--accent);
            background: rgba(139,92,246,.12);
            border: 1px solid rgba(139,92,246,.25);
            padding: .35rem .75rem;
            border-radius: 100px;
            white-space: nowrap;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .card-body {
            padding: 2.5rem;
        }

        /* ── Alert ── */
        .alert {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .9rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-size: .9rem;
            font-weight: 500;
            animation: shake .4s ease;
        }
        .alert-error {
            background: rgba(248,113,113,.1);
            border: 1px solid rgba(248,113,113,.3);
            color: var(--danger);
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-6px); }
            40%      { transform: translateX(6px); }
            60%      { transform: translateX(-4px); }
            80%      { transform: translateX(4px); }
        }

        /* ── Grid ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .field-full { grid-column: 1 / -1; }

        /* ── Field ── */
        .field {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .field-label {
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .field-label .icon { font-size: .9rem; }
        .field-label .required { color: var(--accent); }

        .field-input {
            width: 100%;
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: .85rem 1rem;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            font-weight: 400;
            outline: none;
            transition: border-color .2s, background .2s, box-shadow .2s;
            -webkit-appearance: none;
        }
        .field-input::placeholder { color: var(--muted); opacity: .6; }
        .field-input:hover {
            border-color: rgba(255,255,255,.12);
            background: rgba(255,255,255,.06);
        }
        .field-input:focus {
            border-color: var(--accent);
            background: rgba(139,92,246,.06);
            box-shadow: 0 0 0 3px rgba(139,92,246,.15);
        }

        input[type="date"].field-input::-webkit-calendar-picker-indicator {
            filter: invert(.5) sepia(1) saturate(3) hue-rotate(230deg);
            cursor: pointer;
            opacity: .6;
        }
        input[type="date"].field-input::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        /* Currency prefix */
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-prefix {
            position: absolute;
            left: 1rem;
            color: var(--accent2);
            font-weight: 600;
            font-size: .9rem;
            pointer-events: none;
            z-index: 1;
        }
        .input-wrap .field-input { padding-left: 2rem; }

        /* ── Divider ── */
        .divider {
            margin: 2rem 0;
            border: none;
            border-top: 1px solid var(--border);
            position: relative;
        }
        .divider::after {
            content: 'ITEM DETAILS';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card);
            padding: 0 1rem;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .12em;
            color: var(--muted);
        }

        /* ── Actions ── */
        .actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .85rem 1.75rem;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all .2s;
            position: relative;
            overflow: hidden;
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover {
            color: var(--text);
            border-color: rgba(255,255,255,.15);
            background: rgba(255,255,255,.04);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            color: #fff;
            box-shadow:
                0 4px 20px rgba(139,92,246,.35),
                inset 0 1px 0 rgba(255,255,255,.15);
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.1), transparent);
            opacity: 0;
            transition: opacity .2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(139,92,246,.45), inset 0 1px 0 rgba(255,255,255,.15);
        }
        .btn-primary:hover::before { opacity: 1; }
        .btn-primary:active { transform: translateY(0); }

        /* Ripple */
        .btn-primary .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.25);
            transform: scale(0);
            animation: ripple .55s linear;
            pointer-events: none;
        }
        @keyframes ripple {
            to { transform: scale(4); opacity: 0; }
        }

        /* ── Progress indicator ── */
        .progress-bar {
            height: 3px;
            background: var(--border);
            border-radius: 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 0;
            transition: width .3s ease;
        }

        /* ── Animations ── */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .field { animation: fadeUp .5s ease both; }
        .field:nth-child(1) { animation-delay: .15s; }
        .field:nth-child(2) { animation-delay: .20s; }
        .field:nth-child(3) { animation-delay: .25s; }
        .field:nth-child(4) { animation-delay: .30s; }
        .field:nth-child(5) { animation-delay: .35s; }
        .field:nth-child(6) { animation-delay: .40s; }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .field-full { grid-column: 1; }
            .card-body { padding: 1.5rem; }
            .card-header { padding: 1.5rem; }
            .actions { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
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
        <a href="index.php" class="back-btn">
            ← Back to List
        </a>
    </header>

    <div class="card">
        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>

        <div class="card-header">
            <div class="card-title">
                Edit Inventory Item
                <span>Update the details below and save your changes.</span>
            </div>
            <div class="item-id-badge">
                # ID <?= htmlspecialchars($id) ?>
            </div>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="editForm" novalidate>

                <div class="form-grid">

                    <div class="field field-full">
                        <label class="field-label" for="mname">
                            <span class="icon">💊</span> Medicine Name <span class="required">*</span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="mname"
                            name="mname"
                            value="<?= htmlspecialchars($row['mname']) ?>"
                            placeholder="e.g. Paracetamol 500mg"
                            required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="category">
                            <span class="icon">🏷️</span> Category <span class="required">*</span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="category"
                            name="category"
                            value="<?= htmlspecialchars($row['category']) ?>"
                            placeholder="e.g. Analgesic"
                            required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="quantity">
                            <span class="icon">📦</span> Quantity
                        </label>
                        <input
                            class="field-input"
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="<?= htmlspecialchars($row['quantity']) ?>"
                            min="0"
                            placeholder="0">
                    </div>

                    <div class="field">
                        <label class="field-label" for="price">
                            <span class="icon">💰</span> Price
                        </label>
                        <div class="input-wrap">
                            <span class="input-prefix">₱</span>
                            <input
                                class="field-input"
                                type="number"
                                id="price"
                                name="price"
                                value="<?= htmlspecialchars($row['price']) ?>"
                                min="0"
                                step="0.01"
                                placeholder="0.00">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="expiry_date">
                            <span class="icon">📅</span> Expiry Date
                        </label>
                        <input
                            class="field-input"
                            type="date"
                            id="expiry_date"
                            name="expiry_date"
                            value="<?= htmlspecialchars($row['expiry_date']) ?>">
                    </div>

                    <div class="field">
                        <label class="field-label" for="supplier">
                            <span class="icon">🏭</span> Supplier <span class="required">*</span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="supplier"
                            name="supplier"
                            value="<?= htmlspecialchars($row['supplier']) ?>"
                            placeholder="e.g. PharmaCo Inc."
                            required>
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
    // Live progress bar based on filled fields
    const fields = document.querySelectorAll('.field-input');
    const fill   = document.getElementById('progressFill');

    function updateProgress() {
        const total   = fields.length;
        const filled  = [...fields].filter(f => f.value.trim() !== '').length;
        fill.style.width = (filled / total * 100) + '%';
    }
    fields.forEach(f => f.addEventListener('input', updateProgress));
    updateProgress();

    // Ripple on submit button
    document.getElementById('submitBtn').addEventListener('click', function(e) {
        const btn    = this;
        const circle = document.createElement('span');
        const rect   = btn.getBoundingClientRect();
        const size   = Math.max(rect.width, rect.height);
        circle.style.cssText = `
            width:${size}px; height:${size}px;
            left:${e.clientX - rect.left - size/2}px;
            top:${e.clientY - rect.top  - size/2}px;
        `;
        circle.classList.add('ripple');
        btn.appendChild(circle);
        setTimeout(() => circle.remove(), 600);
    });

    // Client-side validation
    document.getElementById('editForm').addEventListener('submit', function(e) {
        const mname    = document.getElementById('mname').value.trim();
        const category = document.getElementById('category').value.trim();
        const supplier = document.getElementById('supplier').value.trim();
        if (!mname || !category || !supplier) {
            e.preventDefault();
            // Highlight empty required fields
            [
                { id: 'mname',    val: mname    },
                { id: 'category', val: category },
                { id: 'supplier', val: supplier },
            ].forEach(({ id, val }) => {
                const el = document.getElementById(id);
                if (!val) {
                    el.style.borderColor = 'var(--danger)';
                    el.style.boxShadow   = '0 0 0 3px rgba(248,113,113,.15)';
                    el.addEventListener('input', () => {
                        el.style.borderColor = '';
                        el.style.boxShadow   = '';
                    }, { once: true });
                }
            });
        }
    });
</script>
</body>
</html>