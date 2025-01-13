<?php
session_start();
include 'db.php';

$sharedUser = null;
$savings = [];
$payments = [];
$error = null;

if (isset($_GET['view_code'])) {
    $viewCode = $_GET['view_code'];
    $_SESSION['view_code'] = $viewCode; // Store the share code in the session
} elseif (isset($_SESSION['view_code'])) {
    $viewCode = $_SESSION['view_code'];
}

if (isset($viewCode)) {
    $query = $db->prepare("SELECT * FROM users WHERE share_code = ?");
    $query->execute([$viewCode]);
    $sharedUser = $query->fetch(PDO::FETCH_ASSOC);

    if ($sharedUser) {
        // Fetch savings
        $query = $db->prepare("SELECT * FROM investments WHERE user_id = ?");
        $query->execute([$sharedUser['id']]);
        $savings = $query->fetchAll(PDO::FETCH_ASSOC);

        // Fetch payments
        $query = $db->prepare("SELECT * FROM payments WHERE user_id = ?");
        $query->execute([$sharedUser['id']]);
        $payments = $query->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Savings and Payments</title>
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
            </ul>
            <span class="navbar-text">
                <?= htmlspecialchars($_SESSION['username']) ?> | <a href="logout.php" class="text-light">Logout</a>
            </span>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">View Savings and Payments by Code</h2>
            <form method="GET" action="view_savings.php">
                <div class="mb-3">
                    <label for="view_code" class="form-label">Enter Share Code:</label>
                    <input type="text" class="form-control" id="view_code" name="view_code" required>
                </div>
                <button type="submit" class="btn btn-primary">View Savings and Payments</button>
            </form>
        </div>
    </div>

    <!-- Display savings and payments if a valid share code is provided -->
    <?php if (isset($sharedUser)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Savings for <?= htmlspecialchars($sharedUser['username']) ?></h2>
                <table class="table table-bordered">
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
                                <td><?= isset($saving['date']) ? htmlspecialchars($saving['date']) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="card-title">Payments for <?= htmlspecialchars($sharedUser['username']) ?></h2>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>$<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= isset($payment['date']) ? htmlspecialchars($payment['date']) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($payment['description']) ?></td>
                                <td><?= htmlspecialchars($payment['category']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>