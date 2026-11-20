<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$eventID = (string) ($_GET['event'] ?? $_POST['event'] ?? '');
$event = eventForHost($pdo, $eventID, $accountID);
requireDraft($event);

if (!in_array($event['Type'], ['festival', 'tour'], true)) {
    http_response_code(400);
    exit('This event type does not use containers.');
}

$label = $event['Type'] === 'festival' ? 'stand' : 'location';
$name = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $name = trim((string) ($_POST['name'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) {
        $errorMessage = 'Enter a name between 1 and 100 characters.';
    } else {
        $containerID = generateID();

        $statement = $pdo->prepare(
            'INSERT INTO BeerContainer (ID, EventID, Name, Type)
             VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$containerID, $eventID, $name, $label]);

        redirect('edit-container-beers.php?container=' . urlencode($containerID));
    }
}

pageStart('Create ' . $label);
?>

<h1>Create <?= html($label) ?></h1>
<p>Event: <?= html($event['Title']) ?></p>

<?php if ($errorMessage !== ''): ?>
    <p class="error"><?= html($errorMessage) ?></p>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">
    <input type="hidden" name="event" value="<?= html($eventID) ?>">

    <label>
        <?= ucfirst(html($label)) ?> name
        <input type="text" name="name" maxlength="100" required value="<?= html($name) ?>">
    </label>

    <button type="submit">Create and choose beers</button>
</form>

<p><a href="edit-containers.php?event=<?= urlencode($eventID) ?>">Return to containers</a></p>

<?php pageEnd(); ?>

