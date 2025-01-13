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

// Fetch spending by category and month
$query = $db->prepare("SELECT category, MONTH(date) AS month, SUM(amount) AS total_spent FROM payments WHERE user_id = ? GROUP BY category, MONTH(date) ORDER BY MONTH(date), category");
$query->execute([$userId]);
$categoryData = $query->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$categories = [];
$monthlySpending = [];

foreach ($categoryData as $data) {
    $month = date('F', mktime(0, 0, 0, $data['month'], 10));
    $category = $data['category'];
    $totalSpent = $data['total_spent'];

    if (!isset($monthlySpending[$month])) {
        $monthlySpending[$month] = [];
    }
    $monthlySpending[$month][$category] = $totalSpent;

    if (!in_array($category, $categories)) {
        $categories[] = $category;
    }
}

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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 100%;
            height: 400px;
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
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Home Budget App</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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

<div class="container my-5">
    <h1 class="text-center mb-4">Summary</h1>
    <div class="row">
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

    <div class="chart-container">
        <canvas id="summaryChart"></canvas>
    </div>
    <div class="chart-container">
        <canvas id="monthlyChart"></canvas>
    </div>
    <div class="chart-container">
        <canvas id="yearlyChart"></canvas>
    </div>
    <div class="chart-container">
        <canvas id="categoryChart"></canvas>
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

        // Prepare data for category chart
        var categoryLabels = <?= json_encode($categories) ?>;
        var categoryDatasets = [];
        var months = Object.keys(<?= json_encode($monthlySpending) ?>);

        months.forEach(function(month) {
            var data = [];
            categoryLabels.forEach(function(category) {
                data.push(<?= json_encode($monthlySpending) ?>[month][category] || 0);
            });
            categoryDatasets.push({
                label: month,
                data: data,
                backgroundColor: '#' + Math.floor(Math.random()*16777215).toString(16)
            });
        });

        var ctxCategory = document.getElementById('categoryChart').getContext('2d');
        var categoryChart = new Chart(ctxCategory, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: categoryDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true
                    }
                }
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
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>