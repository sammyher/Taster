<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$eventID = (string) ($_GET['event'] ?? '');
$event = eventForHost($pdo, $eventID, $accountID);
requireDraft($event);

if (!in_array($event['Type'], ['festival', 'tour'], true)) {
    http_response_code(400);
    exit('This event type does not use containers.');
}

$statement = $pdo->prepare(
    'SELECT bc.ID, bc.Name, bc.Type, COUNT(cb.BeerID) AS BeerCount
     FROM BeerContainer bc
     LEFT JOIN ContainerBeers cb ON cb.ContainerID = bc.ID
     WHERE bc.EventID = ?
     GROUP BY bc.ID, bc.Name, bc.Type
     ORDER BY bc.CreatedAt, bc.Name'
);
$statement->execute([$eventID]);
$containers = $statement->fetchAll();
$label = $event['Type'] === 'festival' ? 'stand' : 'location';

pageStart('Manage ' . $label . 's');
?>

<h1><?= html($event['Title']) ?></h1>
<h2>Manage <?= html($label) ?>s</h2>

<?php if ($containers === []): ?>
    <p>No <?= html($label) ?>s have been created yet.</p>
<?php endif; ?>

<?php foreach ($containers as $container): ?>
    <div class="card">
        <strong><?= html($container['Name']) ?></strong>
        <p><?= (int) $container['BeerCount'] ?> beer(s)</p>
        <a class="button" href="edit-container-beers.php?container=<?= urlencode($container['ID']) ?>">
            Edit beer list
        </a>
    </div>
<?php endforeach; ?>

<p>
    <a class="button" href="create-container.php?event=<?= urlencode($eventID) ?>">
        Add <?= html($label) ?>
    </a>
</p>

<form method="post" action="open-event.php">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">
    <input type="hidden" name="event" value="<?= html($eventID) ?>">
    <button type="submit">Open event</button>
</form>

<?php pageEnd(); ?>

