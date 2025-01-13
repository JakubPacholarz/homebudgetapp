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
$query = $db->prepare("SELECT SUM(amount) AS total_spent, COUNT(*) AS total_payments FROM payments WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$query->execute([$userId]);
$summary = $query->fetch(PDO::FETCH_ASSOC);
$totalSpent = $summary['total_spent'] ?? 0;
$totalPayments = $summary['total_payments'] ?? 0;

// Fetch total investments for the logged-in user
$query = $db->prepare("SELECT SUM(amount) AS total_invested FROM investments WHERE user_id = ?");
$query->execute([$userId]);
$investmentSummary = $query->fetch(PDO::FETCH_ASSOC);
$totalInvested = $investmentSummary['total_invested'] ?? 0;

$currentBudget = $_SESSION['budget'];
$remainingBudget = $currentBudget - $totalSpent - $totalInvested;

// Fetch monthly and yearly data
$query = $db->prepare("SELECT SUM(amount) AS total_spent, MONTH(date) AS month FROM payments WHERE user_id = ? AND YEAR(date) = YEAR(CURRENT_DATE()) GROUP BY MONTH(date)");
$query->execute([$userId]);
$monthlyData = $query->fetchAll(PDO::FETCH_ASSOC);

$query = $db->prepare("SELECT SUM(amount) AS total_spent, YEAR(date) AS year FROM payments WHERE user_id = ? GROUP BY YEAR(date)");
$query->execute([$userId]);
$yearlyData = $query->fetchAll(PDO::FETCH_ASSOC);

// Find the biggest spending
$query = $db->prepare("SELECT description, amount FROM payments WHERE user_id = ? ORDER BY amount DESC LIMIT 1");
$query->execute([$userId]);
$biggestSpending = $query->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 50%; /* Adjust the width as needed */
            height: 300px; /* Adjust the height as needed */
            margin: auto;
        }
        .ai-support {
            margin-top: 20px;
            text-align: center;
        }
        .ai-support input, .ai-support button {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <nav class="menu">
        <ul>
            <li><a href="savings.php">Savings</a></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="container">
        <h1>Summary</h1>
        <div class="chart-container">
            <canvas id="summaryChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="yearlyChart"></canvas>
        </div>
        <div class="ai-support">
            <h2>AI Support</h2>
            <p id="aiAnalysis"></p>
            <input type="text" id="userQuestion" placeholder="Ask a question about saving money...">
            <button onclick="getAIResponse()">Ask</button>
            <p id="aiResponse"></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var ctxSummary = document.getElementById('summaryChart').getContext('2d');
            var summaryChart = new Chart(ctxSummary, {
                type: 'bar',
                data: {
                    labels: ['Spent', 'Invested', 'Remaining Budget'],
                    datasets: [{
                        label: 'Amount (USD)',
                        data: [<?= $totalSpent ?>, <?= $totalInvested ?>, <?= $remainingBudget ?>],
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            var ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
            var monthlyChart = new Chart(ctxMonthly, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($monthlyData, 'month')) ?>,
                    datasets: [{
                        label: 'Monthly Spending (USD)',
                        data: <?= json_encode(array_column($monthlyData, 'total_spent')) ?>,
                        backgroundColor: '#36A2EB',
                        borderColor: '#36A2EB',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            var ctxYearly = document.getElementById('yearlyChart').getContext('2d');
            var yearlyChart = new Chart(ctxYearly, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($yearlyData, 'year')) ?>,
                    datasets: [{
                        label: 'Yearly Spending (USD)',
                        data: <?= json_encode(array_column($yearlyData, 'total_spent')) ?>,
                        backgroundColor: '#FF6384',
                        borderColor: '#FF6384',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // AI Analysis
            var aiAnalysis = document.getElementById('aiAnalysis');
            var biggestSpending = <?= json_encode($biggestSpending) ?>;
            var advice = "To save more, consider reducing your biggest spending on " + biggestSpending.description + " which cost you $" + biggestSpending.amount + ".";
            aiAnalysis.textContent = advice;
        });

        function getAIResponse() {
            var userQuestion = document.getElementById('userQuestion').value.toLowerCase();
            var aiResponse = document.getElementById('aiResponse');
            var response = "";

            if (userQuestion.includes("save money")) {
                response = "To save money, try to cut down on unnecessary expenses and create a budget plan.";
            } else if (userQuestion.includes("spend less")) {
                response = "To spend less, consider tracking your expenses and identifying areas where you can reduce spending.";
            } else if (userQuestion.includes("biggest spending")) {
                response = "Your biggest spending is on " + biggestSpending.description + ". Try to find alternatives or reduce the frequency of this expense.";
            } else {
                response = "I'm sorry, I don't have an answer for that. Please try asking something else about saving or spending less money.";
            }

            aiResponse.textContent = response;
        }
    </script>
</body>
</html>