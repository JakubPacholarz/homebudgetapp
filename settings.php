<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

$userId = $_SESSION['user_id'];

// Fetch user data
$query = $db->prepare("SELECT * FROM users WHERE id = ?");
$query->execute([$userId]);
$user = $query->fetch(PDO::FETCH_ASSOC);

$currentBudget = $user['budget'] ?? 0;
$shareCode = $user['share_code'] ?? '';

// Generate share code if not set
if (!$shareCode) {
    $shareCode = bin2hex(random_bytes(16));
    $query = $db->prepare("UPDATE users SET share_code = ? WHERE id = ?");
    $query->execute([$shareCode, $userId]);
}

// Generate a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle adding funds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['funds'], $_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $fundsToAdd = floatval($_POST['funds']);
    if ($fundsToAdd > 0) {
        $currentBudget += $fundsToAdd;
        $query = $db->prepare("UPDATE users SET budget = ? WHERE id = ?");
        $query->execute([$currentBudget, $userId]);
        $_SESSION['budget'] = $currentBudget;
        $message = "Funds added successfully!";
        // Regenerate CSRF token after successful form submission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = "Please enter a valid amount.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Home Budget App</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="summary.php">Summary</a></li>
                    <li class="nav-item"><a class="nav-link" href="savings.php">Savings</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
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

    <!-- Main Content -->
    <div class="container my-5">
        <h1 class="mb-4 text-center">Settings</h1>

        <!-- Share Code Section -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Your Share Code</h5>
                <p class="card-text">Share this code to let others view your savings:</p>
                <div class="alert alert-info" role="alert">
                    <?= htmlspecialchars($shareCode) ?>
                </div>
            </div>
        </div>

        <!-- Add Funds Section -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add Funds</h5>
                <?php if (isset($message)): ?>
                    <p class="alert alert-success"><?= htmlspecialchars($message) ?></p>
                <?php elseif (isset($error)): ?>
                    <p class="alert alert-danger"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="funds" class="form-label">Amount to Add:</label>
                        <input type="number" class="form-control" id="funds" name="funds" placeholder="Enter amount" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Funds</button>
                </form>
            </div>
        </div>

        <!-- View Savings Section -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">View Savings by Code</h5>
                <form method="GET" action="view_savings.php">
                    <div class="mb-3">
                        <label for="view_code" class="form-label">Enter Share Code:</label>
                        <input type="text" class="form-control" id="view_code" name="code" placeholder="Enter share code" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">View Savings</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>