<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$eventID = (string) ($_GET['event'] ?? $_POST['event'] ?? '');
$event = eventForHost($pdo, $eventID, $accountID);
requireDraft($event);

if ($event['Type'] !== 'small') {
    http_response_code(400);
    exit('This page is only for small events.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $submittedBeerIDs = $_POST['beer_ids'] ?? [];

    if (!is_array($submittedBeerIDs)) {
        $submittedBeerIDs = [];
    }

    $submittedBeerIDs = array_values(array_unique(array_filter(
        array_map('strval', $submittedBeerIDs),
        fn(string $id): bool => $id !== ''
    )));

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'DELETE FROM ContainerBeers WHERE EventID = ? AND ContainerID IS NULL'
        );
        $statement->execute([$eventID]);

        $validateBeer = $pdo->prepare('SELECT ID FROM BeerCatalog WHERE ID = ?');
        $insertBeer = $pdo->prepare(
            'INSERT INTO ContainerBeers (ContainerID, EventID, BeerID)
             VALUES (NULL, ?, ?)'
        );

        foreach ($submittedBeerIDs as $beerID) {
            $validateBeer->execute([$beerID]);
            if ($validateBeer->fetchColumn() !== false) {
                $insertBeer->execute([$eventID, $beerID]);
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    redirect('edit-small-event.php?event=' . urlencode($eventID) . '&saved=1');
}

$beers = $pdo->query(
    'SELECT ID, Name, Brewery, Type, ABV FROM BeerCatalog ORDER BY Brewery, Name'
)->fetchAll();

$statement = $pdo->prepare(
    'SELECT BeerID FROM ContainerBeers WHERE EventID = ? AND ContainerID IS NULL'
);
$statement->execute([$eventID]);
$selectedBeerIDs = array_flip($statement->fetchAll(PDO::FETCH_COLUMN));

pageStart('Choose event beers');
?>

<h1><?= html($event['Title']) ?></h1>
<h2>Choose the beer list</h2>

<?php if (isset($_GET['saved'])): ?>
    <p>Beer list saved.</p>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">
    <input type="hidden" name="event" value="<?= html($eventID) ?>">

    <ul class="beer-list">
        <?php foreach ($beers as $beer): ?>
            <li>
                <label>
                    <input
                        type="checkbox"
                        name="beer_ids[]"
                        value="<?= html($beer['ID']) ?>"
                        <?= isset($selectedBeerIDs[$beer['ID']]) ? 'checked' : '' ?>
                    >
                    <?= html($beer['Name']) ?>
                    <span class="muted">
                        — <?= html((string) $beer['Brewery']) ?>,
                        <?= html((string) $beer['Type']) ?>,
                        <?= html((string) $beer['ABV']) ?>% ABV
                    </span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>

    <button type="submit">Save beer list</button>
</form>

<form method="post" action="open-event.php">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">
    <input type="hidden" name="event" value="<?= html($eventID) ?>">
    <button type="submit">Open event</button>
</form>

<?php pageEnd(); ?>

