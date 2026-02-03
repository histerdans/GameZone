<?php
// header.php - Navbar with Role-Based Links + Avatar + Balance + Date
ob_start(); // ✅ VERY IMPORTANT
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/config.php";

// Default values
$userFullName = "";
$balance = 0;
$role = "";
$avatar = "default.png"; // fallback avatar
$gamesOwned = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT first_name, last_name, username, balance, role, avatar FROM users WHERE id = $user_id");
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $userFullName = trim($user['first_name'] . " " . $user['last_name']);
        $role = $user['role'];
        $avatar = !empty($user['avatar']) ? $user['avatar'] : "assets/img/avatar/user.png";

        if ($role === 'seller') {
            // Total earnings from sold games
            $earningsQuery = "
                SELECT IFNULL(SUM(g.price),0) AS total_earnings
                FROM purchases p
                JOIN games g ON p.game_id = g.id
                WHERE g.seller_id = $user_id
            ";
            $earningsResult = mysqli_query($conn, $earningsQuery);
            $earningsRow = mysqli_fetch_assoc($earningsResult);
            $balance = $earningsRow['total_earnings'];
        } elseif ($role === 'buyer') {
            // Count of games owned
            $ownedQuery = "SELECT COUNT(*) AS total_games FROM purchases WHERE user_id = $user_id";
            $ownedResult = mysqli_query($conn, $ownedQuery);
            $ownedRow = mysqli_fetch_assoc($ownedResult);
            $balance = $ownedRow['total_games'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Game Store</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/stamp.png">
  <style>
    .avatar-img {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 5px;
      border: 2px solid #fff;
    }

    /* Logo rainbow glow */
    .logo-img {
      height: 45px;
      transition: 0.3s;
    }
    .logo-img:hover {
      animation: rainbow-glow 1.5s infinite alternate;
      transform: scale(1.05);
      cursor: pointer;
    }
    @keyframes rainbow-glow {
      0% { filter: drop-shadow(0 0 5px red); }
      20% { filter: drop-shadow(0 0 5px orange); }
      40% { filter: drop-shadow(0 0 5px yellow); }
      60% { filter: drop-shadow(0 0 5px green); }
      80% { filter: drop-shadow(0 0 5px blue); }
      100% { filter: drop-shadow(0 0 5px violet); }
    }

    body {
      position: relative;
      background: url('../assets/img/bg-store.png') no-repeat center center fixed;
      background-size: cover;
      background-color: black;
      color: white;
    }

    /* Overlay layer */
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: -1;
    }

    @media (max-width: 992px), (orientation: portrait) {
      body { background-size: contain; }
    }

    .card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.6);
    }
    .card img {
      height: 180px;
      object-fit: cover;
      width: 100%;
    }
    .card-body {
      background: rgba(0, 0, 0, 0.75);
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center" href="../index.php">
      <img src="../assets/img/logoh.png" alt="GameSphere Logo" class="logo-img me-2">
      <span>GameSphere</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
      aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

        <!-- Home link always available -->
        <li class="nav-item"><a class="nav-link" href="../index.php">🏠 Home</a></li>

        <?php if (isset($_SESSION['user_id'])): ?>

          <!-- Role-based Quick Links -->
          <?php if ($role === 'buyer'): ?>
            <li class="nav-item"><a class="nav-link" href="../pages/buyer_dashboard.php">📋 My Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/cart.php">🛒 My Cart</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/store.php">🛍️ Store</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/purchases.php">🎮 My Games</a></li>

          <?php elseif ($role === 'seller'): ?>
            <li class="nav-item"><a class="nav-link" href="../pages/seller_dashboard.php">📊 Seller Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/add_game.php">➕ Add New Game</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/store.php">👀 View Games (Buyer Store)</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/checkout_list.php">✅ Checkout List</a></li>

          <?php elseif ($role === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="../pages/admin_dashboard.php">⚙️ Admin Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/manage_users.php">👥 Manage Users</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/manage_games.php">🎮 Manage Games</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/report.php">📑 Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/view_contacts.php">💬 Contacts</a></li>
          <?php endif; ?>

          <!-- Balance / Total Earnings / Games Owned -->
          <li class="nav-item">
            <?php if ($role === 'seller'): ?>
              <span class="nav-link disabled">💰 Earnings: $<?= number_format($balance, 2) ?></span>
            <?php elseif ($role === 'buyer'): ?>
              <span class="nav-link disabled">🎮 Games Owned: <?= $balance ?></span>
            <?php endif; ?>
          </li>

          <li class="nav-item d-flex align-items-center">
            <img src="../<?= $avatar === 'default.png' ? '../assets/img/avatar.jpg' : 'uploads/avatars/' . htmlspecialchars($avatar) ?>" 
            alt="Avatar" class="avatar-img">
            <span class="nav-link">👤 <?= htmlspecialchars($userFullName ?: 'Guest') ?></span>
          </li>

          <!-- Date & Time -->
          <li class="nav-item">
            <span class="nav-link">📅 <?= date("l, d M Y H:i") ?></span>
          </li>

          <!-- Profile -->
          <li class="nav-item">
            <a class="nav-link" href="../pages/profile.php">⚙️ Profile</a>
          </li>

          <!-- Logout -->
          <li class="nav-item">
            <a class="nav-link" href="../pages/logout.php">🚪 Logout</a>
          </li>

        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="../pages/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="../pages/register.php">Register</a></li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<?php
ob_end_flush(); // ✅ VERY IMPORTANT
?>
