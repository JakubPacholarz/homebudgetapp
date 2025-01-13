<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Fetch user details
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch total payments
$query = $db->prepare("SELECT SUM(amount) AS total_spent FROM payments WHERE user_id = ?");
$query->execute([$userId]);
$totalSpent = $query->fetch(PDO::FETCH_ASSOC)['total_spent'] ?? 0;

// Fetch total investments
$query = $db->prepare("SELECT SUM(amount) AS total_invested FROM investments WHERE user_id = ?");
$query->execute([$userId]);
$totalInvested = $query->fetch(PDO::FETCH_ASSOC)['total_invested'] ?? 0;

// Current budget
$currentBudget = $_SESSION['budget'] ?? 0;
$remainingBudget = $currentBudget - $totalSpent - $totalInvested;

// Handle adding payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'], $_POST['date'], $_POST['description'], $_POST['category'])) {
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];
    $description = $_POST['description'];
    $category = $_POST['category'];

    if ($amount > 0 && !empty($date) && !empty($description) && !empty($category)) {
        $query = $db->prepare("INSERT INTO payments (user_id, amount, date, description, category) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$userId, $amount, $date, $description, $category]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Fetch payments for the logged-in user
$query = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY date DESC");
$query->execute([$userId]);
$payments = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
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
                <a href="profile.php"><?= htmlspecialchars($_SESSION['username']) ?></a> | <a href="logout.php" class="text-light">Logout</a>
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

    <!-- Add Payment Form -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Add Payment</h5>
            <?php if (isset($error)): ?>
                <p class="alert alert-danger"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="dashboard.php" method="POST">
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount:</label>
                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="date" class="form-label">Date:</label>
                    <input type="date" class="form-control" id="date" name="date" required>
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
                <button type="submit" class="btn btn-primary">Add Payment</button>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="payments">
        <h2>Your Payments</h2>
        <?php if ($payments): ?>
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
                            <td><?= htmlspecialchars($payment['date']) ?></td>
                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['description']) ?></td>
                            <td><?= htmlspecialchars($payment['category']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No payments found!</p>
        <?php endif; ?>
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
            <tbody id="calendar-body">
                <!-- Calendar will be dynamically generated here -->
            </tbody>
        </table>
    </div>
</div>

<script>
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    function generateCalendar(month, year) {
        const firstDay = new Date(year, month).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let calendarBody = document.getElementById("calendar-body");
        calendarBody.innerHTML = "";

        let date = 1;
        for (let i = 0; i < 6; i++) {
            let row = document.createElement("tr");
            for (let j = 0; j < 7; j++) {
                let cell = document.createElement("td");
                if (i === 0 && j < firstDay) {
                    cell.textContent = "";
                } else if (date > daysInMonth) {
                    break;
                } else {
                    cell.textContent = date;
                    const payment = payments.find(p => new Date(p.date).getDate() === date && new Date(p.date).getMonth() === month && new Date(p.date).getFullYear() === year);
                    if (payment) {
                        cell.innerHTML += `<br>$${payment.amount} - ${payment.description} (${payment.category})`;
                    }
                    if (
                        date === today.getDate() &&
                        year === today.getFullYear() &&
                        month === today.getMonth()
                    ) {
                        cell.classList.add("today");
                    }
                    date++;
                }
                row.appendChild(cell);
            }
            calendarBody.appendChild(row);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        generateCalendar(currentMonth, currentYear);
    });
</script>
</body>
</html>