<?php
session_start();
include 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch logged-in user information
$userId = $_SESSION['user_id'];

// Fetch or generate the user's share code
function generateShareCode() {
    return bin2hex(random_bytes(16));
}

$query = $db->prepare("SELECT share_code FROM users WHERE id = ?");
$query->execute([$userId]);
$shareCode = $query->fetchColumn();

if (!$shareCode) {
    $shareCode = generateShareCode();
    $query = $db->prepare("UPDATE users SET share_code = ? WHERE id = ?");
    $query->execute([$shareCode, $userId]);
}

// Handle share code input for viewing another user's savings
if (isset($_GET['view_code'])) {
    $viewCode = $_GET['view_code'];

    // Validate the share code
    $query = $db->prepare("SELECT id, username FROM users WHERE share_code = ?");
    $query->execute([$viewCode]);
    $sharedUser = $query->fetch(PDO::FETCH_ASSOC);

    if ($sharedUser) {
        $sharedUserId = $sharedUser['id'];

        // Fetch savings for the shared user
        $query = $db->prepare("SELECT * FROM savings WHERE user_id = ?");
        $query->execute([$sharedUserId]);
        $savings = $query->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Invalid share code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <ul class="menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="summary.php">Summary</a></li>
                <li><a href="savings.php">Savings</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <div class="navbar-user-info">
                Logged in as: <?= htmlspecialchars($_SESSION['username']) ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1>Settings</h1>

        <!-- Display the user's share code -->
        <h2>Your Share Code</h2>
        <p>Share this code to allow others to view your savings:</p>
        <p><strong><?= htmlspecialchars($shareCode) ?></strong></p>

        <!-- Form to view savings by share code -->
        <h2>View Savings by Code</h2>
        <form method="GET" action="settings.php">
            <label for="view_code">Enter Share Code:</label>
            <input type="text" id="view_code" name="view_code" required>
            <button type="submit">View Savings</button>
        </form>

        <!-- Display savings if a valid share code is provided -->
        <?php if (isset($sharedUser)): ?>
            <h2>Savings for <?= htmlspecialchars($sharedUser['username']) ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>Investment Type</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($savings as $saving): ?>
                        <tr>
                            <td><?= htmlspecialchars($saving['type']) ?></td>
                            <td>$<?= number_format($saving['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($saving['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
