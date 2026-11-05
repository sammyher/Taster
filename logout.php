<?php

declare(strict_types=1);

session_start();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parameters['path'],
        'domain' => $parameters['domain'],
        'secure' => $parameters['secure'],
        'httponly' => $parameters['httponly'],
        'samesite' => $parameters['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

if (file_exists(__DIR__ . '/config/auth.php')) {
    try {
        require_once __DIR__ . '/config/auth.php';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $returnTo = $scheme . '://' . $host . '/index.php';
        
        $logoutUrl = $auth0->logout($returnTo);
        header('Location: ' . $logoutUrl);
        exit;
    } catch (Throwable $e) {
        // Fallback to local redirect if Auth0 logout fails
    }
}

header('Location: index.php');
exit;
