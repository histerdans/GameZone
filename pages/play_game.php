<?php
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../pages/login.php");
    exit;
}

$userId = intval($_SESSION['user_id']);
$gameId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- Verify this game exists ---
$stmt = $conn->prepare("SELECT id, title, file_path FROM games WHERE id = ?");
if (!$stmt) die("DB prepare error (games): " . $conn->error);
$stmt->bind_param("i", $gameId);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Game not found.</div></div>");
}

// --- Check if buyer purchased this game ---
$stmt2 = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND game_id = ?");
if (!$stmt2) die("DB prepare error (purchases): " . $conn->error);
$stmt2->bind_param("ii", $userId, $gameId);
$stmt2->execute();
$result = $stmt2->get_result();
$stmt2->close();

if ($result->num_rows === 0) {
    die("<div class='container mt-5'><div class='alert alert-warning'>You have not purchased this game yet.</div></div>");
}

// --- Server file path for download ---
$serverFile = !empty($game['file_path']) && file_exists("../uploads/" . $game['file_path'])
    ? "../uploads/" . $game['file_path']
    : null;
?>

<div class="container mt-5">
    <h2 class="mb-4">Playing: <?= htmlspecialchars($game['title']) ?></h2>

    <div class="card p-4 bg-dark text-white">
        <?php if ($serverFile): ?>
            <p>Your game is ready to launch if installed or download if not.</p>

            <!-- Play Button -->
            <button id="playGameBtn" class="btn btn-success mb-2">
                Play Game
            </button>

            <!-- Download Link -->
            <a href="<?= $serverFile ?>" class="btn btn-primary" download>
                Download Game
            </a>

            <p class="mt-2 text-warning" id="launchMessage"></p>

            <script>
            document.getElementById('playGameBtn').addEventListener('click', function() {
                const username = "<?= addslashes($_SESSION['username']) ?>";
                const gameTitle = "<?= addslashes($game['title']) ?>";

                // Assume installed games are in: C:\Users\USERNAME\Games\GameTitle\GameTitle.exe
                const localPath = `file:///C:/Users/${username}/Games/${gameTitle}/${gameTitle}.exe`;

                const launchMessage = document.getElementById('launchMessage');

                // Try opening local exe
                try {
                    window.location.href = localPath;
                    launchMessage.textContent = "Attempting to launch your installed game...";
                } catch (err) {
                    launchMessage.textContent = "Cannot launch the game directly. Please use the download link.";
                }
            });
            </script>

        <?php else: ?>
            <p class="text-danger">No game file available for download.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
