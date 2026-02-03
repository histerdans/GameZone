<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../index.php');
    exit;
}

$message = '';

if (isset($_POST['add_game'])) {

    /* =========================
       1. Sanitize & Validate
    ========================== */
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $seller_id   = intval($_SESSION['user_id']);

    // Stock: default to 5 if not provided
    $stock = isset($_POST['stock']) && $_POST['stock'] !== ''
        ? intval($_POST['stock'])
        : 5;

    if ($stock < 0) {
        $stock = 0;
    }

    if (empty($title) || empty($description) || $price <= 0) {
        $message = '<div class="alert alert-danger">❌ Please fill all required fields correctly.</div>';
    }

    /* =========================
       2. Upload Directories
    ========================== */
    $thumbDir = __DIR__ . '/../uploads/';
    $gameDir  = __DIR__ . '/../uploads/games/';

    if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
    if (!is_dir($gameDir))  mkdir($gameDir, 0777, true);

    /* =========================
       3. Thumbnail Upload
    ========================== */
    if (empty($_FILES['thumbnail']['name'])) {
        $message = '<div class="alert alert-warning">⚠ Thumbnail is required.</div>';
    } else {

        $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        $allowedImg = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($thumbExt, $allowedImg)) {
            $message = '<div class="alert alert-danger">❌ Invalid thumbnail format.</div>';
        } else {

            $thumbnailName = uniqid('thumb_') . '.' . $thumbExt;
            $thumbPath = $thumbDir . $thumbnailName;

            if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath)) {
                $message = '<div class="alert alert-warning">⚠ Failed to upload thumbnail.</div>';
            }
        }
    }

    /* =========================
       4. Game File Upload
    ========================== */
    if (empty($message)) {

        if (empty($_FILES['file']['name'])) {
            $message = '<div class="alert alert-warning">⚠ Game file is required.</div>';
        } else {

            $fileExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowedFiles = ['zip', 'exe', 'rar', '7z'];

            if (!in_array($fileExt, $allowedFiles)) {
                $message = '<div class="alert alert-danger">❌ Invalid game file format.</div>';
            } else {

                $fileSize = $_FILES['file']['size'];

                if ($fileSize > 8 * 1024 * 1024 * 1024) {
                    $message = '<div class="alert alert-danger">❌ Game file exceeds 8GB limit.</div>';
                } else {

                    $fileName = uniqid('game_') . '.' . $fileExt;
                    $filePath = $gameDir . $fileName;

                    if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
                        $message = '<div class="alert alert-warning">⚠ Failed to upload game file.</div>';
                    } else {

                        // Convert size to readable
                        $readableSize = ($fileSize >= 1073741824)
                            ? round($fileSize / 1073741824, 2) . ' GB'
                            : round($fileSize / 1048576, 2) . ' MB';

                        /* =========================
                           5. Insert Into Database
                        ========================== */
                        $stmt = $conn->prepare("
                            INSERT INTO games 
                            (title, description, price, stock, file_size, thumbnail, file_path, seller_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        if (!$stmt) {
                            $message = '<div class="alert alert-danger">SQL Error: ' . $conn->error . '</div>';
                        } else {

                            $stmt->bind_param(
                                "ssdisssi",
                                $title,
                                $description,
                                $price,
                                $stock,
                                $readableSize,
                                $thumbnailName,
                                $fileName,
                                $seller_id
                            );

                            if ($stmt->execute()) {
                                $message = '<div class="alert alert-success">✅ Game added successfully!</div>';
                            } else {
                                $message = '<div class="alert alert-danger">❌ Database error: ' . $stmt->error . '</div>';
                            }

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}
?>

<!-- =========================
     HTML FORM
========================= -->
<div class="container mt-5">
    <h2 class="mb-4 text-center">📥 Add New Game</h2>
    <?= $message ?>

    <form method="POST" enctype="multipart/form-data"
          class="card p-4 shadow-sm mx-auto text-dark"
          style="max-width: 600px;">

        <div class="mb-3">
            <label class="form-label">Game Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" required></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Price (KES)</label>
            <input type="number" step="0.01" name="price" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Units Available (Stock)</label>
            <input type="number" name="stock" class="form-control" min="0" value="5">
            <small class="text-muted">Downloads allowed before the game becomes unavailable</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Thumbnail</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Game File</label>
            <input type="file" name="file" class="form-control" accept=".zip,.exe,.rar,.7z" required>
            <small class="text-muted">Max size: 8GB</small>
        </div>

        <button type="submit" name="add_game" class="btn btn-primary w-100">
            Add Game
        </button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
