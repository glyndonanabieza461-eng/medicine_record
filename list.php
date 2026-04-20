<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Medicine Inventory System</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #74ebd5, #ACB6E5);
            margin: 0;
            padding: 20px;
        }

        .container {
            width: 95%;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
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
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-size: 14px;
        }

        .add-btn {
            background: #28a745;
        }

        .add-btn:hover {
            background: #218838;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 10px;
        }

        th {
            background: #007bff;
            color: white;
            padding: 12px;
            text-align: center;
        }

        td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        tr:hover {
            background: #e9ecef;
        }

        .action a {
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 13px;
            margin: 2px;
            display: inline-block;
        }

        .edit {
            background: #ffc107;
            color: #000;
        }

        .delete {
            background: #dc3545;
            color: white;
        }

        .edit:hover {
            background: #e0a800;
        }

        .delete:hover {
            background: #c82333;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Medicine Inventory List</h2>

    <div class="top-bar">
        <a href="add.php" class="btn add-btn">+ Add Medicine</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Expiry Date</th>
            <th>Supplier</th>
            <th>Action</th>
        </tr>

        <?php
        $result = $conn->query("SELECT * FROM inventory");

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
        ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['mname']; ?></td>
            <td><?= $row['category']; ?></td>
            <td><?= $row['quantity']; ?></td>
            <td><?= $row['price']; ?></td>
            <td><?= $row['expiry_date']; ?></td>
            <td><?= $row['supplier']; ?></td>
            <td class="action">
                <a class="edit" href="edit.php?id=<?= $row['id']; ?>">Edit</a>
                <a class="delete" href="delete.php?id=<?= $row['id']; ?>" onclick="return confirm('Delete this record?')">Delete</a>
            </td>
        </tr>
        <?php
            }
        } else {
            echo "<tr><td colspan='8' class='empty'>No medicine records found</td></tr>";
        }
        ?>

    </table>

</div>

</body>
</html>