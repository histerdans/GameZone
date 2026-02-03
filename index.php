<?php
include 'includes/session.php';

// If user is logged in, redirect based on role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
        header("Location: pages/admin_dashboard.php");
        exit();
        case 'seller':
        header("Location: pages/seller_dashboard.php");
        exit();
        case 'buyer':
        header("Location: pages/buyer_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GameSphere - PC Game Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/stamp.png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css"/>

    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
            height: 100%;
            background: url('assets/img/bg.png') center/cover no-repeat fixed;
            color: #fff;
        }

        .overlay {
            background: rgba(0, 0, 0, 0.7);
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .section {
            padding: 80px 0;
            text-align: center;
        }

        .hero {
            padding: 120px 20px 80px;
        }

        .hero img.logo {
            width: 150px;
            margin-bottom: 20px;
        }

        .hero h1 {
            font-size: 4rem;
            color: #ffc107;
        }

        .hero p {
            font-size: 1.2rem;
            color: #eee;
        }

        .game-card img {
            height: 180px;
            object-fit: cover;
        }

        footer {
            background: rgba(0, 0, 0, 0.6);
            color: #bbb;
        }

        .contact-section {
            background: rgba(255, 255, 255, 0.05);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .contact-section h2,
        .section-title {
            color: #ffc107;
        }

        input, textarea {
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
        }

        input::placeholder, textarea::placeholder {
            color: #ccc;
        }

        .form-control:focus {
            box-shadow: none;
            border: 1px solid #ffc107;
        }
        .hero img.logo {
            width: 150px;
            margin-bottom: 20px;
            transition: transform 0.3s ease-in-out;
        }

        /* Rainbow pulse animation on hover/touch */
        .hero img.logo:hover {
            animation: rainbowPulse 2.5s linear infinite;
            transform: scale(1.1); /* Bigger zoom effect */
            cursor: pointer;
        }

        /* Keyframes for rainbow glowing pulse */
        @keyframes rainbowPulse {
            0% {
                filter: drop-shadow(0 0 5px red)
                drop-shadow(0 0 10px red);
                transform: scale(1.05);
            }
            20% {
                filter: drop-shadow(0 0 10px orange)
                drop-shadow(0 0 20px orange);
                transform: scale(1.08);
            }
            40% {
                filter: drop-shadow(0 0 15px yellow)
                drop-shadow(0 0 30px yellow);
                transform: scale(1.1);
            }
            60% {
                filter: drop-shadow(0 0 20px green)
                drop-shadow(0 0 40px green);
                transform: scale(1.08);
            }
            80% {
                filter: drop-shadow(0 0 25px blue)
                drop-shadow(0 0 50px blue);
                transform: scale(1.05);
            }
            100% {
                filter: drop-shadow(0 0 30px violet)
                drop-shadow(0 0 60px violet);
                transform: scale(1.1);
            }
        }

</style>

</head>
<body>
    <div class="overlay">

        <!-- Home/Welcome Section -->
        <section class="hero section" id="home">
            <!-- Logo -->
            <img src="assets/img/logoh.png" alt="GameSphere Logo" class="logo">
            
            <h1>Welcome to <span class="text-warning">GameSphere</span></h1>
            <p>Your ultimate PC game marketplace. Discover, Sell, and Buy top-rated games!</p>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="pages/login.php" class="btn btn-primary btn-lg">Login</a>
                <a href="pages/register.php" class="btn btn-outline-light btn-lg">Register</a>
            </div>
        </section>
        <!-- Featured Games Section -->
        <section class="container section" id="featured">
            <h2 class="section-title">Featured Games</h2>
            <div class="row mt-4">
                <?php
                include 'includes/config.php';
                include 'functions/game.php';
            $games = array_slice(getGames(), 0, 4); // Show 4 featured games
            foreach ($games as $game):
                ?>
                <div class="col-md-3 mb-4">
                    <div class="card bg-dark text-white game-card h-100">
                        <img src="uploads/<?= htmlspecialchars($game['thumbnail']) ?>" class="card-img-top" alt="<?= htmlspecialchars($game['title']) ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                            <p class="card-text"><?= substr(htmlspecialchars($game['description']), 0, 60) ?>...</p>
                            <p class="mt-auto"><strong>Ksh.<?= htmlspecialchars($game['price']) ?></strong></p>
                            <a href="pages/game_details.php?id=<?= $game['id'] ?>" class="btn btn-warning btn-sm mt-2">View Game</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Contact Section -->
<section class="container contact-section section" id="contact">
    <h2 class="section-title">Contact Us</h2>
    <p class="mb-4">Have questions or feedback? Reach out to the GameSphere team below.</p>

    <?php
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
        require_once 'includes/config.php'; // DB connection
        require_once 'functions/contact.php'; // addContact() function

        $name    = trim($_POST['name']);
        $email   = trim($_POST['email']);
        $phone   = trim($_POST['phone']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        $user_id = $_SESSION['user_id'] ?? null;

        $response = addContact($name, $email, $phone, $subject, $message, $user_id);

        echo '<div class="alert alert-' . ($response['status'] === 'success' ? 'success' : 'danger') . ' text-center">'
            . htmlspecialchars($response['message']) . '</div>';
    }
    ?>

    <form action="" method="post" class="row justify-content-center">
        <div class="col-md-6">
            <div class="mb-3">
                <input type="text" name="name" class="form-control" placeholder="Your Name" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Your Email" required>
            </div>
            <div class="mb-3">
                <input type="text" name="phone" class="form-control" placeholder="Your Phone (optional)">
            </div>
            <div class="mb-3">
                <input type="text" name="subject" class="form-control" placeholder="Subject" required>
            </div>
            <div class="mb-3">
                <textarea name="message" rows="4" class="form-control" placeholder="Your Message" required></textarea>
            </div>
            <button type="submit" name="contact_submit" class="btn btn-warning w-100">Send Message</button>
        </div>
    </form>
</section>
    <!-- Footer -->
    <footer class="text-center py-3 mt-5">
        <p class="mb-0">&copy; <?= date('Y') ?> GameSphere. All rights reserved.</p>
    </footer>

</div>

<!-- Bootstrap JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
