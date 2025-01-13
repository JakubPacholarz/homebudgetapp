<?php
session_start(); // Start the session to access $_SESSION variables

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php'; // Include the database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the logged-in user's ID
    $userId = $_SESSION['user_id'];

    // Retrieve the form data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $date = $_POST['date'];
        $category = $_POST['category'];
    
        $query = $db->prepare("INSERT INTO payments (user_id, amount, description, date, category) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$_SESSION['user_id'], $amount, $description, $date, $category]);
    
        header('Location: dashboard.php');
        exit;
    }
    

    // Validate input
    if (empty($amount) || empty($date) || empty($description)) {
        $error = "All fields are required!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be a valid positive number!";
    } else {
        // Insert the payment record into the database
        $query = $db->prepare("INSERT INTO payments (user_id, amount, date, description) VALUES (?, ?, ?, ?)");
        try {
            $query->execute([$userId, $amount, $date, $description]);
            header("Location: dashboard.php"); // Redirect back to the dashboard after success
            exit;
        } catch (PDOException $e) {
            $error = "Failed to add payment: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">a
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Add Payment</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form action="add_payment.php" method="POST">
            <label for="amount">Amount:</label>
            <input type="number" id="amount" name="amount" step="0.01" required>

            <label for="date">Date:</label>
            <input type="date" id="date" name="date" required>

            <label for="description">Description:</label>
            <input type="text" id="description" name="description" required>

            <button type="submit">Add Payment</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>
