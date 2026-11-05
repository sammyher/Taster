<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// echo '<pre>';
// echo "CALLBACK SESSION ID: " . session_id() . "\n";
// var_dump($_SESSION);
// echo '</pre>';
// exit;

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

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

// Make the Auth0 account available to the rest of Taster
session_regenerate_id(true);

$_SESSION['account_id'] = $auth0Id;
$_SESSION['display_name'] = $displayName;
$_SESSION['email'] = $email;

} catch (Exception $e) {
    die('Authentication or Database error: ' . htmlspecialchars($e->getMessage()));
}

//Route user according to selected role
$role = $_SESSION['target_role'] ?? 'attendee';
unset($_SESSION['target_role']);

if ($role === 'host') {
    header('Location: host.php');
} elseif ($role === 'account') {
    header('Location: account.php');
} elseif ($role === 'home') {
    header('Location: index.php');
} elseif (!empty($_SESSION['pending_event_code'])) {
    header('Location: join.php?resume=1');
} else {
    header('Location: index.php');
}

exit;
