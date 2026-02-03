<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $update = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $update->bind_param("di", $amount, $user_id);
        if ($update->execute()) {
            $message = "<div class='alert alert-success'>💳 Balance topped up successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ Failed to top up balance.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>⚠️ Enter a valid amount.</div>";
    }
}

// Fetch updated balance
$bal_res = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
$balance = mysqli_fetch_assoc($bal_res)['balance'];
?>

<div class="container mt-5">
    <h2 class="mb-4 text-center">💳 Top Up Balance</h2>
    <?= $message ?>

    <div class="text-center mb-4">
        <h4>Current Balance: $<?= number_format($balance, 2) ?></h4>
    </div>

    <form method="POST" class="card p-4 shadow-sm mx-auto" style="max-width: 400px;">
        <div class="mb-3">
            <label for="amount" class="form-label">Amount to Add ($)</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Top Up</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
