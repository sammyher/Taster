<?php

session_start();

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request.');
}

$accountId = $_SESSION['account_id'] ?? '';
$beerId = trim($_POST['beer_id'] ?? '');
$eventId = trim($_POST['event_id'] ?? '');

if ($accountId === '' || $beerId === '') {
    exit('Missing account or beer.');
}

$statement = $pdo->prepare(
    'INSERT IGNORE INTO FavoriteBeers (BeerID, AccountID)
     VALUES (?, ?)'
);

$statement->execute([$beerId, $accountId]);

if ($eventId !== '') {
    header(
        'Location: beer_selection.php?event_id=' .
        urlencode($eventId)
    );
} else {
    header('Location: beer_catalog.php');
}

exit;
