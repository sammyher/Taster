<?php
declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$eventID = (string) ($_SESSION['event_id'] ?? '');
$participantID = (string) ($_SESSION['participant_id'] ?? '');

if ($eventID === '' || $participantID === '') {
    header('Location: index.php');
    exit;
}

// Authorize access through event participation rather than event ownership.
$stmt = $pdo->prepare(
    'SELECT e.ID, e.Title, e.Type, e.State, p.AccountID
     FROM Events e
     JOIN Participants p ON p.EventID = e.ID
     WHERE e.ID = ? AND p.ID = ?
     LIMIT 1'
);
$stmt->execute([$eventID, $participantID]);
$event = $stmt->fetch();

if (!$event) {
    unset($_SESSION['event_id'], $_SESSION['participant_id'], $_SESSION['event_code']);
    http_response_code(403);
    exit('You are not registered for this event.');
}

pageStart('Event lobby');
?>

<h1><?= html($event['Title']) ?></h1>
<p>Event type: <strong><?= html($event['Type']) ?></strong></p>
<p>Event state: <strong><?= html($event['State']) ?></strong></p>

<?php if (!empty($_SESSION['anonymous'])): ?>
    <p>
        Participating as
        <strong><?= html($_SESSION['username'] ?? 'Anonymous attendee') ?></strong>
    </p>
<?php endif; ?>

<?php if ($event['State'] === 'open'): ?>
    <h2>Event actions</h2>
    <p><a href="reviews.php">View or submit beer reviews</a></p>
<?php else: ?>
    <p>This event is no longer open for new reviews.</p>
    <p><a href="reviews.php">View event reviews</a></p>
<?php endif; ?>

<p><a href="index.php">Return home</a></p>

<?php pageEnd(); ?>
