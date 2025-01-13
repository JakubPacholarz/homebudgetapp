<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


include 'db.php';

// Fetch total payments and total spent for the logged-in user
$userId = $_SESSION['user_id'];
$query = $db->prepare("SELECT SUM(amount) AS total_spent FROM payments WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$query->execute([$userId]);
$summary = $query->fetch(PDO::FETCH_ASSOC);
$totalSpent = $summary['total_spent'] ?? 0;

// Fetch payments for the logged-in user
$query = $db->prepare("SELECT amount, description, date, category FROM payments WHERE user_id = ?");
$query->execute([$_SESSION['user_id']]);
$payments = $query->fetchAll();

// Prepare events for the calendar
$events = [];
foreach ($payments as $payment) {
    $events[] = [
        'title' => '$' . number_format($payment['amount'], 2) . ' - ' . $payment['description'],
        'start' => $payment['date'],
        'description' => $payment['description'],
        'amount' => $payment['amount']
    ];
}

// Fetch total investments for the logged-in user
$query = $db->prepare("SELECT SUM(amount) AS total_invested FROM investments WHERE user_id = ?");
$query->execute([$userId]);
$investmentSummary = $query->fetch(PDO::FETCH_ASSOC);
$totalInvested = $investmentSummary['total_invested'] ?? 0;

// Ensure the budget is set in the session
$currentBudget = $_SESSION['budget'] ?? 0;
$remainingBudget = $currentBudget - $totalSpent - $totalInvested;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script>
        // Function to toggle dark mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('mode', mode);
        }

        // Apply mode on page load
        window.onload = function () {
            if (localStorage.getItem('mode') === 'dark') {
                document.body.classList.add('dark-mode');
            }
        };
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: <?= json_encode($events) ?>
            });
            calendar.render();
        });
    </script>
</head>
<body>
   <!-- Navigation Menu -->
   <nav class="menu">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="summary.php">Summary</a></li>
            <li><a href="savings.php">Savings</a></li>
            <li><a href="logout.php">Logout</a></li>
            <li><a href="view_savings.php">View Savings (by Code)</a></li>

        </ul>
        <div class="user-info">
            <?= htmlspecialchars($_SESSION['username']) ?> | <a href="settings.php">Settings</a> | Remaining Budget: $<?= number_format($remainingBudget, 2) ?>
        </div>

        
    </nav>

    <div class="container">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h1>

        <!-- Add Payment Form -->
        <div class="add-payment">
            <h2>Add Payment</h2>
            <form action="add_payment.php" method="POST">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" name="amount" step="0.01" required>

                <label for="date">Date:</label>
                <input type="date" id="date" name="date" required>

                <label for="description">Description:</label>
                <input type="text" id="description" name="description" required>

                <label for="category">Category:</label>
                <select id="category" name="category" required>
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

                <button type="submit">Add Payment</button>
            </form>
        </div>

         <!-- Payments Table -->
        <div class="payments">
            <h2>Your Payments</h2>
            <?php if ($payments): ?>
                <table>
    <thead>
        <tr>
            <th>Amount</th>
            <th>Description</th>
            <th>Date</th>
            <th>Category</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td>$<?= number_format($payment['amount'], 2) ?></td>
                <td><?= htmlspecialchars($payment['description']) ?></td>
                <td><?= htmlspecialchars($payment['date']) ?></td>
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
        <div id="calendar"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($payments as $payment): ?>,
                    {
                        title: '$<?= htmlspecialchars($payment['amount']) ?> - <?= htmlspecialchars($payment['description']) ?>'
                        start: '<?= htmlspecialchars($payment['date']) ?>'
                    }
                    <?php endforeach; ?>
                ]
            });

            calendar.render();
        });
    </script>
</body>
</html>
