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
$cryptoPrices = json_decode(file_get_contents($cryptoApiUrl), true);

// Fetch live prices for metals (example: GoldAPI)
$metalsApiUrl = "https://www.metals-api.com/api/latest?access_key=YOUR_API_KEY&base=USD&symbols=XAU,XAG";
$metalsPrices = json_decode(file_get_contents($metalsApiUrl), true);

// Fetch live forex rates (example: Exchange Rates API)
$forexApiUrl = "https://api.exchangeratesapi.io/latest?base=USD&symbols=EUR,GBP,JPY,CAD,AUD";
$forexPrices = json_decode(file_get_contents($forexApiUrl), true);

// Combine all prices into a single array
$prices = [
    'cryptos' => $cryptoPrices,
    'metals' => $metalsPrices['rates'] ?? [],
    'currencies' => $forexPrices['rates'] ?? [],
];

// Fetch user's investments
$userId = $_SESSION['user_id'];
$query = $db->prepare("SELECT * FROM investments WHERE user_id = ?");
$query->execute([$userId]);
$investments = $query->fetchAll(PDO::FETCH_ASSOC);

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
            $currentPrice = $forexPrices['rates'][$asset] ?? 0;
        }

        // Validate and save the investment
        if ($currentPrice > 0) {
            $query = $db->prepare("INSERT INTO investments (user_id, type, amount, price_at_investment) VALUES (?, ?, ?, ?)");
            $query->execute([$userId, $type, $amount, $currentPrice]);

            header("Location: savings.php");
            exit;
        } else {
            $error = "Failed to fetch the price for the selected asset.";
        }
    } else {
        $error = "Please enter valid investment details.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings and Investments</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="menu">
        <ul>
            <li><a href="dashboard.php">Lobby</a></li>
            <li><a href="summary.php">Summary</a></li>
            <li><a href="savings.php">Savings</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="container">
        <h1>Savings and Investments</h1>

        <!-- Live Prices -->
        <h2>Live Investment Prices</h2>
        <table>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Price (USD)</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($prices as $asset => $data): ?>
        <tr>
            <td><?= ucfirst($asset) ?></td>
            <td>
                <?php if (isset($data['USD'])): ?>
                    $<?= number_format($data['USD'], 2) ?>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
        </table>

       <!-- Add Investment Form -->
<h2>Invest in an Asset</h2>
<?php if (isset($error)): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form action="savings.php" method="POST">
    <label for="type">Select Asset:</label>
    <select name="type" id="type" required>
        <!-- Cryptocurrencies -->
        <optgroup label="Cryptocurrencies">
            <?php foreach ($cryptoPrices as $asset => $data): ?>
                <option value="Crypto-<?= ucfirst($asset) ?>"><?= ucfirst($asset) ?> (Crypto)</option>
            <?php endforeach; ?>
        </optgroup>
        
        <!-- Metals -->
        <optgroup label="Metals">
            
                <?php foreach ($metalsPrices['rates'] as $metal => $price): ?>
                    <option value="Metal-<?= $metal ?>"><?= ucfirst($metal) ?> (Metal)</option>
                <?php endforeach; ?>
         
        </optgroup>
        
        <!-- Currencies -->
        <optgroup label="Currencies">
            <?php if (isset($forexPrices['rates'])): ?>
                <?php foreach ($forexPrices['rates'] as $currency => $rate): ?>
                    <option value="Currency-<?= $currency ?>"><?= ucfirst($currency) ?> (Currency)</option>
                <?php endforeach; ?>
            <?php endif; ?>
        </optgroup>
    </select>
    <label for="amount">Investment Amount (USD):</label>
    <input type="number" id="amount" name="amount" step="0.01" required>
    <button type="submit">Invest</button>
</form>

        <!-- Display User's Investments -->
        <h2>Your Investments</h2>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Amount (USD)</th>
                    <th>Price at Investment (USD)</th>
                    <th>Current Price (USD)</th>
                    <th>Change (%)</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($investments as $investment): 
    $typeParts = explode('-', $investment['type']);
    $category = strtolower($typeParts[0]);
    $asset = strtolower($typeParts[1] ?? '');

    $currentPrice = 0;

    if ($category === 'crypto') {
        // Check if the asset exists and has a USD price
        if (isset($cryptoPrices[$asset]) && isset($cryptoPrices[$asset]['usd'])) {
            $currentPrice = $cryptoPrices[$asset]['usd'];
        }
    } elseif ($category === 'metal') {
        // Check if the metal exists in the API response
        $currentPrice = $metalsPrices['rates'][$asset] ?? 0;
    } elseif ($category === 'currency') {
        // Check if the currency exists in the API response
        $currentPrice = $forexPrices['rates'][$asset] ?? 0;
    }

    // Calculate percentage change
    $priceAtInvestment = $investment['price_at_investment'];
    $change = $priceAtInvestment > 0 
              ? (($currentPrice - $priceAtInvestment) / $priceAtInvestment) * 100 
              : 0;
?>
    <tr>
        <td><?= htmlspecialchars($investment['type']) ?></td>
        <td>$<?= number_format($investment['amount'], 2) ?></td>
        <td>$<?= number_format($priceAtInvestment, 2) ?></td>
        <td>$<?= number_format($currentPrice, 2) ?></td>
        <td><?= number_format($change, 2) ?>%</td>
    </tr>
<?php endforeach; ?>
</tbody>


        </table>
    </div>
</body>
</html>
