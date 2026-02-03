<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: regenerate session ID to prevent fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
