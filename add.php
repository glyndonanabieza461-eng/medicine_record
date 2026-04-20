<?php 
include 'db.php';

// Handle form submission
if (isset($_POST['submit'])) {

    $mname = $_POST['mname'];
    $category = $_POST['category'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $expiry_date = $_POST['expiry_date'];
    $supplier = $_POST['supplier'];

    // IMAGE UPLOAD
    $image = $_FILES['image']['name'];
    $temp = $_FILES['image']['tmp_name'];

    $folder = "uploads/" . $image;

    // allowed file types
    $allowed = ['jpg','jpeg','png'];
    $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {

        move_uploaded_file($temp, $folder);

        // INSERT WITH IMAGE
        $stmt = $conn->prepare("INSERT INTO inventory (mname, category, quantity, price, expiry_date, supplier, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $mname, $category, $quantity, $price, $expiry_date, $supplier, $image);

        if ($stmt->execute()) {
            $success = "Medicine added successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }

        $stmt->close();

    } else {
        $error = "Only JPG, JPEG, PNG files are allowed!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Medicine Inventory</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #74ebd5, #ACB6E5);
            margin: 0;
            padding: 0;
        }

        .container {
            width: 400px;
            margin: 60px auto;
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            font-size: 14px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        button {
            width: 100%;
            background: #28a745;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
        }

        .msg {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .link {
            text-align: center;
            margin-top: 15px;
        }

        .link a {
            text-decoration: none;
            color: #007bff;
        }

        /* image preview */
        .preview {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            display: none;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Add Medicine</h2>

    <?php if (isset($success)) echo "<div class='msg success'>$success</div>"; ?>
    <?php if (isset($error)) echo "<div class='msg error'>$error</div>"; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Name</label>
        <input type="text" name="mname" required>

        <label>Category</label>
        <input type="text" name="category" required>

        <label>Quantity</label>
        <input type="number" name="quantity" required>

        <label>Price</label>
        <input type="text" name="price" required>

        <label>Expiry Date</label>
        <input type="date" name="expiry_date" required>

        <label>Supplier</label>
        <input type="text" name="supplier" required>
        
        <label>image</label>
        <input type="file" name="image" required>

        <!-- IMAGE INPUT -->
        <label>Medicine Image</label>
        <img id="preview" class="preview">
        <input type="file" name="image" accept="image/*" onchange="previewImage(event)" required>

        <button type="submit" name="submit">+ Add Medicine</button>
    </form>

    <div class="link">
        <a href="list.php">View Medicine List</a>
    </div>

</div>

<script>
function previewImage(event) {
    const img = document.getElementById('preview');
    img.src = URL.createObjectURL(event.target.files[0]);
    img.style.display = "block";
}
</script>

</body>
</html>