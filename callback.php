<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

try {
    //Process Auth0 response
    $auth0->exchange();
    $userData = $auth0->getCredentials()->user;

    $auth0Id     = $userData['sub'];
    $email       = $userData['email'] ?? '';
    $displayName = $userData['name'] ?? $userData['nickname'] ?? 'User';

    //Insert or update the user in the Accounts table
    $stmt = $pdo->prepare("
        INSERT INTO Accounts (ID, DisplayName, Email)
        VALUES (:id, :name, :email)
        ON DUPLICATE KEY UPDATE
            DisplayName = VALUES(DisplayName),
            Email = VALUES(Email)
    ");
    
    $stmt->execute([
        ':id'    => $auth0Id,
        ':name'  => $displayName,
        ':email' => $email,
    ]);

} catch (Exception $e) {
    die('Authentication or Database error: ' . htmlspecialchars($e->getMessage()));
}

//Route user according to selected role
$role = $_SESSION['target_role'] ?? 'attendee';
unset($_SESSION['target_role']);

if ($role === 'host') {
    header('Location: host.php');
} else {
    header('Location: attendee.php');
}
exit;
