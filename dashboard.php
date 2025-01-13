<?php
session_start();
include 'db.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user data
$query = $db->prepare("SELECT * FROM users WHERE id = ?");
$query->execute([$userId]);
$user = $query->fetch(PDO::FETCH_ASSOC);

// Fetch current budget
$currentBudget = $user['budget'] ?? 0;

// Fetch total spent for the current month
$query = $db->prepare("SELECT SUM(amount) AS total_spent FROM payments WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$query->execute([$userId]);
$totalSpent = $query->fetch(PDO::FETCH_ASSOC)['total_spent'] ?? 0;

// Calculate remaining budget
$remainingBudget = $currentBudget - $totalSpent;

// Fetch payments for the current month
$query = $db->prepare("SELECT * FROM payments WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$query->execute([$userId]);
$payments = $query->fetchAll(PDO::FETCH_ASSOC);

// Fetch regular payments
$query = $db->prepare("SELECT * FROM regular_payments WHERE user_id = ?");
$query->execute([$userId]);
$regularPayments = $query->fetchAll(PDO::FETCH_ASSOC);

// Handle new payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'], $_POST['description'], $_POST['category'], $_POST['date'])) {
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $category = $_POST['category'];
    $date = $_POST['date'];

    if ($amount > 0 && !empty($description) && !empty($category) && !empty($date)) {
        // Insert the new payment into the database
        $query = $db->prepare("INSERT INTO payments (user_id, amount, description, category, date) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$userId, $amount, $description, $category, $date]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Generate calendar for the current month
$month = date('m');
$year = date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = date('w', strtotime("$year-$month-01"));
$calendar = [];

for ($i = 0; $i < $firstDayOfMonth; $i++) {
    $calendar[] = '';
}

for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendar[] = $day;
}

while (count($calendar) % 7 != 0) {
    $calendar[] = '';
}

function isPaymentDay($day, $payments) {
    foreach ($payments as $payment) {
        if (date('j', strtotime($payment['date'])) == $day) {
            return true;
        }
    }
    return false;
}

function isRegularPaymentDay($day, $regularPayments) {
    foreach ($regularPayments as $payment) {
        if ($payment['day'] == $day) {
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .calendar {
            margin-top: 30px;
        }
        .calendar td, .calendar th {
            text-align: center;
            padding: 10px;
        }
        .calendar .today {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
        }
        .calendar .payment-day {
            background-color: #28a745;
            color: #fff;
        }
        .calendar .regular-payment-day {
            background-color: #ffc107;
            color: #fff;
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
                <li class="nav-item"><a class="nav-link" href="view_savings.php">View savings</a></li>
            </ul>
            <span class="navbar-text">
                <?php if (isset($user['photo']) && $user['photo']): ?>
                    <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Profile Photo">
                <?php endif; ?>
                <a href="profile.php"><?= htmlspecialchars($username) ?></a> | <a href="logout.php" class="text-light">Logout</a>
            </span>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h1 class="text-center mb-4">Welcome, <?= htmlspecialchars($username) ?>!</h1>
    <div class="row">
        <!-- Budget Summary -->
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Total Budget</div>
                <div class="card-body">
                    <h5 class="card-title">$<?= number_format($currentBudget, 2) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Remaining Budget</div>
                <div class="card-body">
                    <h5 class="card-title">$<?= number_format($remainingBudget, 2) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Total Spent</div>
                <div class="card-body">
                    <h5 class="card-title">$<?= number_format($totalSpent, 2) ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="calendar">
        <h2 class="text-center">Payment Calendar</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Sun</th>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($calendar); $i++): ?>
                    <?php if ($i % 7 == 0): ?>
                        <tr>
                    <?php endif; ?>
                    <td class="<?= isPaymentDay($calendar[$i], $payments) ? 'payment-day' : '' ?> <?= isRegularPaymentDay($calendar[$i], $regularPayments) ? 'regular-payment-day' : '' ?>">
                        <?= $calendar[$i] ?>
                    </td>
                    <?php if ($i % 7 == 6): ?>
                        </tr>
                    <?php endif; ?>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Payments Table -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Payments</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($payment['date']))) ?></td>
                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['description']) ?></td>
                            <td><?= htmlspecialchars($payment['category']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Payment Form -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Add Payment</h5>
            <?php if (isset($error)): ?>
                <p class="alert alert-danger"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST" action="dashboard.php">
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
                    <label for="date" class="form-label">Date:</label>
                    <input type="date" class="form-control" id="date" name="date" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Payment</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>