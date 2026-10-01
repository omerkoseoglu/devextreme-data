<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in web server:
 *   php -S localhost:8000 -t demo demo/router.php
 * Static files are served as-is, every other path below /api/ is dispatched to demo/api/*.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/api/')) {
    $script = __DIR__ . '/api/' . basename($path, '.php') . '.php';

    if (is_file($script)) {
        require $script;

        return true;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"error":"Unknown endpoint"}';

    return true;
}

if ($path !== '/' && is_dir(__DIR__ . $path) && is_file(__DIR__ . rtrim($path, '/') . '/index.html')) {
    header('Location: ' . rtrim($path, '/') . '/index.html');

    return true;
}

return false; // let the built-in server serve static files
