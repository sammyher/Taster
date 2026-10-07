<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken();
$accountID = requireAccountID();
$eventID = (string) ($_POST['event'] ?? '');
$event = eventForHost($pdo, $eventID, $accountID);
requireDraft($event);

if ($event['Type'] === 'small') {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM ContainerBeers
         WHERE EventID = ? AND ContainerID IS NULL'
    );
    $statement->execute([$eventID]);

    if ((int) $statement->fetchColumn() < 1) {
        exit('Add at least one beer before opening the event.');
    }
} else {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM BeerContainer WHERE EventID = ?'
    );
    $statement->execute([$eventID]);

    if ((int) $statement->fetchColumn() < 1) {
        exit('Add at least one container before opening the event.');
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM BeerContainer bc
         LEFT JOIN ContainerBeers cb ON cb.ContainerID = bc.ID
         WHERE bc.EventID = ?
         GROUP BY bc.ID
         HAVING COUNT(cb.BeerID) = 0'
    );
    $statement->execute([$eventID]);

    if ($statement->fetchColumn() !== false) {
        exit('Every container must have at least one beer before the event can open.');
    }
}

$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$joinCode = '';

for ($attempt = 0; $attempt < 10; $attempt++) {
    $candidate = '';
    for ($index = 0; $index < 6; $index++) {
        $candidate .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    $statement = $pdo->prepare('SELECT COUNT(*) FROM Events WHERE JoinCode = ?');
    $statement->execute([$candidate]);

    if ((int) $statement->fetchColumn() === 0) {
        $joinCode = $candidate;
        break;
    }
}

if ($joinCode === '') {
    throw new RuntimeException('Unable to generate a unique join code.');
}

$statement = $pdo->prepare(
    'UPDATE Events
     SET JoinCode = ?, State = \'open\'
     WHERE ID = ? AND State = \'draft\''
);
$statement->execute([$joinCode, $eventID]);

if ($statement->rowCount() !== 1) {
    http_response_code(409);
    exit('The event could not be opened because its state changed.');
}

redirect('event-lobby.php?event=' . urlencode($eventID));

