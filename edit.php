<?php
include 'db.php';

$id = $_GET['id'];

// GET DATA
$result = $conn->query("SELECT * FROM inventory WHERE id=$id");
$row = $result->fetch_assoc();

// UPDATE DATA
if (isset($_POST['update'])) {

    $mname = $_POST['mname'];
    $category = $_POST['category'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $expiry_date = $_POST['expiry_date'];
    $supplier = $_POST['supplier'];

    $sql = "UPDATE inventory SET 
            mname='$mname',
            category='$category',
            quantity='$quantity',
            price='$price',
            expiry_date='$expiry_date',
            supplier='$supplier'
            WHERE id=$id";

    if ($conn->query($sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>

<form method="POST">
    Name: <input type="text" name="mname" value="<?= $row['mname']; ?>"><br>
    Category: <input type="text" name="category" value="<?= $row['category']; ?>"><br>
    Quantity: <input type="number" name="quantity" value="<?= $row['quantity']; ?>"><br>
    Price: <input type="text" name="price" value="<?= $row['price']; ?>"><br>
    Expiry Date: <input type="date" name="expiry_date" value="<?= $row['expiry_date']; ?>"><br>
    Supplier: <input type="text" name="supplier" value="<?= $row['supplier']; ?>"><br>

    <button type="submit" name="update">Update</button>
</form>