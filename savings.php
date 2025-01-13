<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Fetch live prices for cryptocurrencies
$cryptoApiUrl = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,cardano,polkadot,solana,litecoin,binancecoin,ripple,dogecoin,tron&vs_currencies=usd";
$cryptoPrices = @json_decode(file_get_contents($cryptoApiUrl), true);
if ($cryptoPrices === null) {
    $cryptoPrices = [];
}

// Fetch live prices for metals (example: Metals-API)
$metalsApiUrl = "https://metals-api.com/api/latest?access_key=YOUR_API_KEY&base=USD&symbols=XAU,XAG,XPT,XPD,RH,IR,OS,RU,CU,AL";
$metalsPrices = @json_decode(file_get_contents($metalsApiUrl), true);
if ($metalsPrices === null || !isset($metalsPrices['rates'])) {
    $metalsPrices = ['rates' => []];
}

// Fetch live forex rates (example: ExchangeRate-API)
$forexApiUrl = "https://v6.exchangerate-api.com/v6/YOUR_API_KEY/latest/USD";
$forexPrices = @json_decode(file_get_contents($forexApiUrl), true);
if ($forexPrices === null || !isset($forexPrices['conversion_rates'])) {
    $forexPrices = ['conversion_rates' => []];
}

// Combine all prices into a single array and limit to top 10
$prices = [
    'cryptos' => array_slice($cryptoPrices, 0, 10),
    'metals' => array_slice($metalsPrices['rates'], 0, 10),
    'currencies' => array_slice($forexPrices['conversion_rates'], 0, 10),
];

// Fetch user's investments
$userId = $_SESSION['user_id'];
$query = $db->prepare("SELECT * FROM investments WHERE user_id = ?");
$query->execute([$userId]);
$investments = $query->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's current balance
$currentBalance = $_SESSION['balance'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['type'], $_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
        $type = trim($_POST['type']);
        $amount = trim($_POST['amount']);
        $currentPrice = 0;

        // Determine the type of asset and fetch its live price
        if (strpos($type, 'Crypto-') === 0) {
            $asset = strtolower(str_replace('Crypto-', '', $type));
            $currentPrice = $cryptoPrices[$asset]['usd'] ?? 0; // Fallback to 0 if key doesn't exist
            if ($currentPrice === 0) {
                echo "Price data for $asset is currently unavailable.";
            }
        } elseif (strpos($type, 'Metal-') === 0) {
            $asset = str_replace('Metal-', '', $type);
            $currentPrice = $metalsPrices['rates'][$asset] ?? 0;
        } elseif (strpos($type, 'Currency-') === 0) {
            $asset = str_replace('Currency-', '', $type);
            $currentPrice = $forexPrices['conversion_rates'][$asset] ?? 0;
        }

        // Insert the investment into the database
        $query = $db->prepare("INSERT INTO investments (user_id, type, amount, price_at_investment) VALUES (?, ?, ?, ?)");
        $query->execute([$userId, $type, $amount, $currentPrice]);
    } elseif (isset($_POST['sell_investment_id'])) {
        // Handle selling the investment
        $investmentId = $_POST['sell_investment_id'];
        $query = $db->prepare("SELECT * FROM investments WHERE id = ? AND user_id = ?");
        $query->execute([$investmentId, $userId]);
        $investment = $query->fetch(PDO::FETCH_ASSOC);

        if ($investment) {
            $typeParts = explode('-', $investment['type']);
            $category = strtolower($typeParts[0]);
            $asset = strtolower($typeParts[1] ?? '');
            $currentPrice = 0;

            if ($category === 'crypto') {
                $currentPrice = $cryptoPrices[$asset]['usd'] ?? 0;
            } elseif ($category === 'metal') {
                $currentPrice = $metalsPrices['rates'][$asset] ?? 0;
            } elseif ($category === 'currency') {
                $currentPrice = $forexPrices['conversion_rates'][$asset] ?? 0;
            }

            $priceAtInvestment = $investment['price_at_investment'];
            $profitLoss = ($currentPrice - $priceAtInvestment) * $investment['amount'] / $priceAtInvestment;

            // Update the user's balance
            $currentBalance += $investment['amount'] + $profitLoss;
            $_SESSION['balance'] = $currentBalance;

            // Delete the investment from the database
            $query = $db->prepare("DELETE FROM investments WHERE id = ? AND user_id = ?");
            $query->execute([$investmentId, $userId]);
        }
    }
}

// Separate investments by category and calculate totals
$cryptoInvestments = [];
$metalInvestments = [];
$currencyInvestments = [];
$totalCryptoInvested = 0;
$totalMetalInvested = 0;
$totalCurrencyInvested = 0;

foreach ($investments as $investment) {
    $typeParts = explode('-', $investment['type']);
    $category = strtolower($typeParts[0]);
    if ($category === 'crypto') {
        $cryptoInvestments[] = $investment;
        $totalCryptoInvested += $investment['amount'];
    } elseif ($category === 'metal') {
        $metalInvestments[] = $investment;
        $totalMetalInvested += $investment['amount'];
    } elseif ($category === 'currency') {
        $currencyInvestments[] = $investment;
        $totalCurrencyInvested += $investment['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings and Investments</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .navbar-text {
            margin-left: auto;
        }
        .investment-table th, .investment-table td {
            text-align: center;
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
                <?php if (isset($user['photo']) && $user['photo']): ?>
                    <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Profile Photo">
                <?php endif; ?>
                <a href="profile.php"><?= htmlspecialchars($_SESSION['username']) ?></a> | <a href="logout.php" class="text-light">Logout</a>
            </span>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h1 class="text-center mb-4">Savings and Investments</h1>

    <!-- Live Prices -->
    <h2>Live Investment Prices</h2>
    <table class="table table-bordered investment-table">
        <thead>
            <tr>
                <th>Asset</th>
                <th>Price (USD)</th>
                <th>Total Invested (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prices['cryptos'] as $asset => $data): ?>
                <tr>
                    <td><?= ucfirst($asset) ?></td>
                    <td>$<?= number_format($data['usd'], 2) ?></td>
                    <td>$<?= number_format($totalCryptoInvested, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($prices['metals'] as $metal => $price): ?>
                <tr>
                    <td><?= ucfirst($metal) ?></td>
                    <td>$<?= number_format($price, 2) ?></td>
                    <td>$<?= number_format($totalMetalInvested, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($prices['currencies'] as $currency => $rate): ?>
                <tr>
                    <td><?= ucfirst($currency) ?></td>
                    <td>$<?= number_format($rate, 2) ?></td>
                    <td>$<?= number_format($totalCurrencyInvested, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Add Investment Form -->
    <h2>Invest in an Asset</h2>
    <?php if (isset($error)): ?>
        <p class="alert alert-danger"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form action="savings.php" method="POST" class="mb-4">
        <div class="form-group">
            <label for="type">Select Asset:</label>
            <select name="type" id="type" class="form-control" required>
                <!-- Cryptocurrencies -->
                <optgroup label="Cryptocurrencies">
                    <?php foreach ($prices['cryptos'] as $asset => $data): ?>
                        <option value="Crypto-<?= ucfirst($asset) ?>"><?= ucfirst($asset) ?> (Crypto)</option>
                    <?php endforeach; ?>
                </optgroup>
                
                <!-- Metals -->
                <optgroup label="Metals">
                    <?php foreach ($prices['metals'] as $metal => $price): ?>
                        <option value="Metal-<?= $metal ?>"><?= ucfirst($metal) ?> (Metal)</option>
                    <?php endforeach; ?>
                </optgroup>
                
                <!-- Currencies -->
                <optgroup label="Currencies">
                    <?php foreach ($prices['currencies'] as $currency => $rate): ?>
                        <option value="Currency-<?= $currency ?>"><?= ucfirst($currency) ?> (Currency)</option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Investment Amount (USD):</label>
            <input type="number" id="amount" name="amount" class="form-control" step="0.01" required>
        </div>
        <button type="submit" class="btn btn-primary">Invest</button>
    </form>

    <!-- Display User's Investments -->
    <h2>Your Investments</h2>

    <!-- Cryptocurrencies Table -->
    <h3>Cryptocurrencies</h3>
    <table class="table table-bordered investment-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Amount (USD)</th>
                <th>Price at Investment (USD)</th>
                <th>Current Price (USD)</th>
                <th>Change (%)</th>
                <th>Profit/Loss (USD)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cryptoInvestments as $investment): 
                $typeParts = explode('-', $investment['type']);
                $asset = strtolower($typeParts[1] ?? '');
                $currentPrice = $cryptoPrices[$asset]['usd'] ?? 0;
                $priceAtInvestment = $investment['price_at_investment'];
                $change = $priceAtInvestment > 0 
                          ? (($currentPrice - $priceAtInvestment) / $priceAtInvestment) * 100 
                          : 0;
                $profitLoss = ($currentPrice - $priceAtInvestment) * $investment['amount'] / $priceAtInvestment;
            ?>
            <tr>
                <td>Crypto - <?= ucfirst($asset) ?></td>
                <td><?= number_format($investment['amount'], 2) ?></td>
                <td><?= number_format($priceAtInvestment, 2) ?></td>
                <td><?= number_format($currentPrice, 2) ?></td>
                <td><?= number_format($change, 2) ?>%</td>
                <td><?= number_format($profitLoss, 2) ?></td>
                <td>
                    <form action="savings.php" method="POST" style="display:inline;">
                        <input type="hidden" name="sell_investment_id" value="<?= $investment['id'] ?>">
                        <button type="submit" class="btn btn-danger">Sell</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Metals Table -->
    <h3>Metals</h3>
    <table class="table table-bordered investment-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Amount (USD)</th>
                <th>Price at Investment (USD)</th>
                <th>Current Price (USD)</th>
                <th>Change (%)</th>
                <th>Profit/Loss (USD)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metalInvestments as $investment): 
                $typeParts = explode('-', $investment['type']);
                $asset = strtolower($typeParts[1] ?? '');
                $currentPrice = $metalsPrices['rates'][$asset] ?? 0;
                $priceAtInvestment = $investment['price_at_investment'];
                $change = $priceAtInvestment > 0 
                          ? (($currentPrice - $priceAtInvestment) / $priceAtInvestment) * 100 
                          : 0;
                $profitLoss = ($currentPrice - $priceAtInvestment) * $investment['amount'] / $priceAtInvestment;
            ?>
            <tr>
                <td>Metal - <?= ucfirst($asset) ?></td>
                <td><?= number_format($investment['amount'], 2) ?></td>
                <td><?= number_format($priceAtInvestment, 2) ?></td>
                <td><?= number_format($currentPrice, 2) ?></td>
                <td><?= number_format($change, 2) ?>%</td>
                <td><?= number_format($profitLoss, 2) ?></td>
                <td>
                    <form action="savings.php" method="POST" style="display:inline;">
                        <input type="hidden" name="sell_investment_id" value="<?= $investment['id'] ?>">
                        <button type="submit" class="btn btn-danger">Sell</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Currencies Table -->
    <h3>Currencies</h3>
    <table class="table table-bordered investment-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Amount (USD)</th>
                <th>Price at Investment (USD)</th>
                <th>Current Price (USD)</th>
                <th>Change (%)</th>
                <th>Profit/Loss (USD)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($currencyInvestments as $investment): 
                $typeParts = explode('-', $investment['type']);
                $asset = strtolower($typeParts[1] ?? '');
                $currentPrice = $forexPrices['conversion_rates'][$asset] ?? 0;
                $priceAtInvestment = $investment['price_at_investment'];
                $change = $priceAtInvestment > 0 
                          ? (($currentPrice - $priceAtInvestment) / $priceAtInvestment) * 100 
                          : 0;
                $profitLoss = ($currentPrice - $priceAtInvestment) * $investment['amount'] / $priceAtInvestment;
            ?>
            <tr>
                <td>Currency - <?= ucfirst($asset) ?></td>
                <td><?= number_format($investment['amount'], 2) ?></td>
                <td><?= number_format($priceAtInvestment, 2) ?></td>
                <td><?= number_format($currentPrice, 2) ?></td>
                <td><?= number_format($change, 2) ?>%</td>
                <td><?= number_format($profitLoss, 2) ?></td>
                <td>
                    <form action="savings.php" method="POST" style="display:inline;">
                        <input type="hidden" name="sell_investment_id" value="<?= $investment['id'] ?>">
                        <button type="submit" class="btn btn-danger">Sell</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>