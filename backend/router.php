<?php
/**
 * PHP built-in server router
 * Usage: php -S localhost:8080 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve uploaded files
if (preg_match('#^/uploads/(.+)$#', $uri, $matches)) {
    $file = __DIR__ . '/storage/uploads/' . $matches[1];
    if (file_exists($file)) {
        $mime = mime_content_type($file);
        header("Content-Type: {$mime}");
        header('Access-Control-Allow-Origin: *');
        readfile($file);
        return true;
    }
    http_response_code(404);
    return true;
}

// Route API requests
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api/index.php';
    return true;
}

// Fallback
return false;
