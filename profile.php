<?php
session_start();
include 'db.php';

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $photo = $_FILES['photo'];
    $targetDir = "uploads/";
    $targetFile = $targetDir . basename($photo["name"]);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($photo["tmp_name"]);
    if ($check !== false) {
        // Check file size (limit to 5MB)
        if ($photo["size"] > 5000000) {
            $error = "Sorry, your file is too large.";
        } else {
            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                // Save the file
                if (move_uploaded_file($photo["tmp_name"], $targetFile)) {
                    // Update user's profile photo in the database
                    $query = $db->prepare("UPDATE users SET photo = ? WHERE id = ?");
                    $query->execute([$targetFile, $userId]);
                    $message = "The file " . htmlspecialchars(basename($photo["name"])) . " has been uploaded.";
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }
        }
    } else {
        $error = "File is not an image.";
    }
}

// Handle regular payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'], $_POST['description'], $_POST['category'], $_POST['day'])) {
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $category = $_POST['category'];
    $day = intval($_POST['day']);

    if ($amount > 0 && !empty($description) && !empty($category) && $day > 0 && $day <= 31) {
        // Insert the regular payment into the database
        $query = $db->prepare("INSERT INTO regular_payments (user_id, amount, description, category, day) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$userId, $amount, $description, $category, $day]);
        $message = "Regular payment has been set.";
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Fetch user data
$query = $db->prepare("SELECT * FROM users WHERE id = ?");
$query->execute([$userId]);
$user = $query->fetch(PDO::FETCH_ASSOC);

// Fetch regular payments
$query = $db->prepare("SELECT * FROM regular_payments WHERE user_id = ?");
$query->execute([$userId]);
$regularPayments = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .error {
            color: red;
        }
        .navbar-text img {
            border-radius: 50%;
            width: 30px;
            height: 30px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Home Budget App</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="summary.php">Summary</a></li>
                <li class="nav-item"><a class="nav-link" href="savings.php">Savings</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
            </ul>
            <span class="navbar-text">
                <?php if (isset($user['photo']) && $user['photo']): ?>
                    <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Profile Photo">
                <?php endif; ?>
                <a href="profile.php"><?= htmlspecialchars($_SESSION['username']) ?></a> | <a href="logout.php" class="text-light">Logout</a>
            </span>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Profile</h2>
            <?php if ($message): ?>
                <p class="alert alert-success"><?= htmlspecialchars($message) ?></p>
            <?php elseif ($error): ?>
                <p class="alert alert-danger"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <!-- Display user photo -->
            <?php if (isset($user['photo']) && $user['photo']): ?>
                <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Profile Photo" class="img-thumbnail mb-3" style="max-width: 200px;">
            <?php endif; ?>

            <!-- Photo upload form -->
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="photo" class="form-label">Upload Photo:</label>
                    <input type="file" class="form-control" id="photo" name="photo" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload Photo</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Set Regular Payments</h2>
            <form method="POST" action="profile.php">
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount:</label>
                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description:</label>
                    <input type="text" class="form-control" id="description" name="description" required>
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category:</label>
                    <select class="form-control" id="category" name="category" required>
                        <option value="Groceries">Groceries</option>
                        <option value="Rent">Rent</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Dining Out">Dining Out</option>
                        <option value="Healthcare">Healthcare</option>
                        <option value="Education">Education</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Miscellaneous">Miscellaneous</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="day" class="form-label">Day of the Month:</label>
                    <select class="form-control" id="day" name="day" required>
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Set Regular Payment</button>
            </form>
        </div>
    </div>

    <!-- Display regular payments -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Your Regular Payments</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Day of the Month</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regularPayments as $payment): ?>
                        <tr>
                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['description']) ?></td>
                            <td><?= htmlspecialchars($payment['category']) ?></td>
                            <td><?= htmlspecialchars($payment['day']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>