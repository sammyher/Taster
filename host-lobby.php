<?php
declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$eventID = (string) ($_GET['event'] ?? $_SESSION['event_id'] ?? '');

if ($eventID === '') {
    http_response_code(400);
    exit('No event was selected.');
}

$event = eventForHost($pdo, $eventID, $accountID);

if ($event['State'] !== 'open' || empty($event['JoinCode'])) {
    http_response_code(409);
    exit('This event is not open.');
}

// Give the host the same active-event context used by reviews.php.
$_SESSION['event_id'] = (string) $event['ID'];
$_SESSION['event_code'] = (string) $event['JoinCode'];
$_SESSION['participant_id'] = (string) $event['HostID'];
unset($_SESSION['anonymous']);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$joinURL = $scheme . '://' . $host . '/index.php?code=' .
    rawurlencode((string) $event['JoinCode']);

pageStart('Host lobby');
?>

<h1><?= html($event['Title']) ?></h1>
<p>Event state: <strong><?= html($event['State']) ?></strong></p>

<h2>Join code</h2>
<p class="join-code"><?= html($event['JoinCode']) ?></p>

<h2>Invite link</h2>
<p><a href="<?= html($joinURL) ?>"><?= html($joinURL) ?></a></p>

<h2>Host actions</h2>
<p><a href="reviews.php">View or submit reviews</a></p>
<p><a href="index.php">Return home</a></p>

<?php pageEnd(); ?>
