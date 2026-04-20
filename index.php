<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Medicine Inventory System</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f4f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            width: 95%;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .btn {
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
        }

        .add-btn {
            background: #28a745;
        }

        .search-box {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #4a90e2;
            color: white;
            padding: 12px;
        }

        td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        img {
            border-radius: 8px;
        }

        .edit {
            background: #ffc107;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: black;
        }

        .delete {
            background: #e74c3c;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>💊 Medicine Inventory System</h2>

    <div class="top-bar">
        <a href="add.php" class="btn add-btn">+ Add Medicine</a>

        <form method="GET">
            <input type="text" name="search" class="search-box"
            value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>"
            placeholder="Search medicine...">
            <button type="submit" class="btn add-btn">Search</button>
        </form>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Expiry Date</th>
            <th>Supplier</th>
            <th>Action</th>
        </tr>

<?php
// SEARCH FUNCTION
if (isset($_GET['search']) && $_GET['search'] != '') {
    $search = $_GET['search'];
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE mname LIKE ? OR category LIKE ? OR supplier LIKE ?");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM inventory");
}

// DISPLAY DATA
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
?>

<tr>

    <td><?= $row['id']; ?></td>

    <!-- IMAGE FIXED -->
    <td>
        <?php if (!empty($row['image'])) { ?>
            <img src="img/<?= $row['image']; ?>" width="60" height="60">
        <?php } else { ?>
            <img src="img/default.jpg" width="60" height="60">
        <?php } ?>
    </td>

    <td><?= $row['mname']; ?></td>
    <td><?= $row['category']; ?></td>
    <td><?= $row['quantity']; ?></td>
    <td>₱<?= number_format($row['price'], 2); ?></td>
    <td><?= $row['expiry_date']; ?></td>
    <td><?= $row['supplier']; ?></td>

    <td>
        <a class="edit" href="edit.php?id=<?= $row['id']; ?>">Edit</a>
        <a class="delete" href="delete.php?id=<?= $row['id']; ?>" onclick="return confirm('Delete this record?')">Delete</a>
    </td>

</tr>

<?php
    }
} else {
    echo "<tr><td colspan='9'>No medicine found</td></tr>";
}
?>

    </table>

</div>

</body>
</html>