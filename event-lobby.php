<?php

session_start();

require_once __DIR__ . '/config/event-helpers.php';

$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT * FROM Events WHERE JoinCode = ? LIMIT 1');
$stmt->execute([$_SESSION['event_code']]);
$event = $stmt->fetch();

error_log(print_r($event, true)); //DEBUG

if ($event['State'] !== 'open' || empty($event['JoinCode'])) {
    http_response_code(409);
    exit('This event is not open.');
}

/* ---------- PAGE SETTINGS ---------- */
$page_title   = $event['Title'];
$page_heading = 'Welcome to ' . $event['Title'];
$body_class   = 'centered';

/* ---------- FORM HANDLING ---------- */
$errors     = [];
$event_code = '';

/* ---------- PAGE CONTENT ---------- */
ob_start();
?>
<p>Event state: <strong><?= html($event['State']) ?></strong></p>

<h2>Join code</h2>
<p class="join-code"><?= html($event['JoinCode']) ?></p>

<?php
$page_content = ob_get_clean();

require __DIR__ . '/layout.php';
