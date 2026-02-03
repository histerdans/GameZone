<?php
require_once(__DIR__ . '/../includes/config.php');

if (!function_exists('getGames')) {
    function getGames() {
        global $conn;
        $sql = "SELECT * FROM games ORDER BY created_at DESC";
        $result = mysqli_query($conn, $sql);

        $games = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $games[] = $row;
            }
        }

        return $games;
    }
}

if (!function_exists('getGameById')) {
    function getGameById($id) {
        global $conn;
        $sql = "SELECT * FROM games WHERE id = ?";

        // handle both object-oriented and procedural mysqli
        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare($sql);
        } else {
            $stmt = mysqli_prepare($conn, $sql);
        }

        if (!$stmt) {
            die("❌ SQL Prepare Error: " . (is_object($conn) ? $conn->error : mysqli_error($conn)));
        }

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}
