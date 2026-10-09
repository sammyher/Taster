<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$eventID = (string) ($_GET['event'] ?? '');
$event = eventForHost($pdo, $eventID, $accountID);

if ($event['State'] !== 'open' || empty($event['JoinCode'])) {
    http_response_code(409);
    exit('This event is not open.');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$joinURL = $scheme . '://' . $host . '/join.php?code=' . rawurlencode($event['JoinCode']);

pageStart('Event lobby');
?>

<h1><?= html($event['Title']) ?></h1>
<p>Event state: <strong><?= html($event['State']) ?></strong></p>

<h2>Join code</h2>
<p class="join-code"><?= html($event['JoinCode']) ?></p>

<h2>Invite link</h2>
<p><a href="<?= html($joinURL) ?>"><?= html($joinURL) ?></a></p>

<p class="muted">
    The join page itself is to be implemented by the teammate responsible for participant joining (Paul).
</p>

<?php pageEnd(); ?>

