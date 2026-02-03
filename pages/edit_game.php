<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the game info
$query = $conn->prepare("SELECT * FROM games WHERE id = ? AND seller_id = ?");
$query->bind_param("ii", $game_id, $seller_id);
$query->execute();
$game = $query->get_result()->fetch_assoc();

if (!$game) {
    die("Game not found or access denied.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = (int)$_POST['stock'];

    // Preserve existing thumbnail unless a new one is uploaded
    $thumbnail = $game['thumbnail'];
    if (!empty($_FILES['thumbnail']['name'])) {
        $new_thumb_file = time() . "_" . basename($_FILES['thumbnail']['name']);
        $tmp_thumb = $_FILES['thumbnail']['tmp_name'];
        $thumb_path = "../uploads/" . $new_thumb_file;
        if (move_uploaded_file($tmp_thumb, $thumb_path)) {
            $thumbnail = $new_thumb_file;
        }
    }

    // Preserve existing game file size and path unless a new file is uploaded
    $file_size = $game['file_size'];
    if (!empty($_FILES['file']['name'])) {
        $new_game_file = time() . "_" . basename($_FILES['file']['name']);
        $tmp_file = $_FILES['file']['tmp_name'];
        $raw_size = $_FILES['file']['size'];

        if ($raw_size > 8 * 1024 * 1024 * 1024) {
            $message = "<div class='alert alert-danger'>❌ File too large! Max 8GB allowed.</div>";
        } else {
            $upload_path_game = "../uploads/games/" . $new_game_file;
            if (move_uploaded_file($tmp_file, $upload_path_game)) {
                // Convert size to human-readable
                if ($raw_size >= 1073741824) {
                    $file_size = round($raw_size / 1073741824, 2) . " GB";
                } else {
                    $file_size = round($raw_size / 1048576, 2) . " MB";
                }
                // Update file_path in DB
                $conn->query("UPDATE games SET file_path='" . $conn->real_escape_string($new_game_file) . "' WHERE id=$game_id AND seller_id=$seller_id");
            }
        }
    }

    if (empty($message)) {
        // Correct bind_param order: title, description, price, file_size, thumbnail, stock, game_id, seller_id
        $update = $conn->prepare("
            UPDATE games 
            SET title=?, description=?, price=?, file_size=?, thumbnail=?, stock=? 
            WHERE id=? AND seller_id=?
        ");
        if (!$update) die("SQL Error: " . $conn->error);

        $update->bind_param(
            "ssdssiii",
            $title,
            $description,
            $price,
            $file_size,
            $thumbnail,
            $stock,
            $game_id,
            $seller_id
        );

        if ($update->execute()) {
            $message = "<div class='alert alert-success'>✅ Game updated successfully!</div>";
            // Refresh game data
            $query->execute();
            $game = $query->get_result()->fetch_assoc();
        } else {
            $message = "<div class='alert alert-danger'>❌ Failed to update game: " . $conn->error . "</div>";
        }
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4 text-center">✏️ Edit Game</h2>
    <?= $message ?>

    <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm mx-auto" style="max-width: 600px;">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($game['title']) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($game['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Price (KSh)</label>
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($game['price']) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Replace Game File (optional, max 8GB)</label>
            <input type="file" name="file" class="form-control" accept=".zip,.exe,.rar,.7z">
        </div>

        <div class="mb-3">
            <label class="form-label">Units Available (Stock)</label>
            <input type="number" name="stock" value="<?= (int)$game['stock'] ?>" min="5" class="form-control" required>
            <small class="text-muted">Downloads allowed before the game becomes unavailable</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Current Thumbnail:</label><br>
            <?php if (!empty($game['thumbnail']) && file_exists("../uploads/" . $game['thumbnail'])): ?>
                <img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>" alt="Thumbnail" width="100" style="object-fit: cover;">
            <?php else: ?>
                <span class="text-muted">No thumbnail uploaded</span>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Change Thumbnail (optional)</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-success">Update Game</button>
        </div>
    </form>
</div>

<style>
label {
    color: #000;
}
</style>

<?php require_once '../includes/footer.php'; ?>
