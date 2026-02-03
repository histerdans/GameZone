<?php
/**
 * Lightweight .env loader (no composer, no external libs)
 */

function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception(".env file not found at $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // skip comments
        }

        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'"); // remove quotes if any

        // Set for getenv() and $_ENV
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// ✅ Auto-load your .env when included
loadEnv(__DIR__ . '/../.env');
