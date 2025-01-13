<?php
// File: db.php (Database Connection)
$host = 'localhost';
$db = 'home_budget';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<?php
// File: register.php (User Registration)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'db.php';
    
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    if ($stmt->execute([$username, $password])) {
        header("Location: login.php");
        exit();
    } else {
        echo "Error: Unable to register user.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
    <h1>Register</h1>
    <form method="POST">
        <label>Username:</label>
        <input type="text" name="username" required>
        <br>
        <label>Password:</label>
        <input type="password" name="password" required>
        <br>
        <button type="submit">Register</button>
    </form>
</body>
</html>

<?php
// File: login.php (User Login)
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'db.php';

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        echo "Invalid credentials.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    <form method="POST">
        <label>Username:</label>
        <input type="text" name="username" required>
        <br>
        <label>Password:</label>
        <input type="password" name="password" required>
        <br>
        <button type="submit">Login</button>
    </form>
</body>
</html>

<?php
// File: dashboard.php (User Dashboard)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, date, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $amount, $date, $description]);
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <h2>Add Payment</h2>
    <form method="POST">
        <label>Amount:</label>
        <input type="number" name="amount" required>
        <br>
        <label>Date:</label>
        <input type="date" name="date" required>
        <br>
        <label>Description:</label>
        <input type="text" name="description" required>
        <br>
        <button type="submit">Add Payment</button>
    </form>

    <h2>Payments</h2>
    <table border="1">
        <tr>
            <th>Date</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?php echo htmlspecialchars($payment['date']); ?></td>
                <td><?php echo htmlspecialchars($payment['amount']); ?></td>
                <td><?php echo htmlspecialchars($payment['description']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Calendar</h2>
    <!-- Basic calendar logic here -->
</body>
</html>
