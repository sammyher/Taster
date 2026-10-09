<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$containerID = (string) ($_GET['container'] ?? $_POST['container'] ?? '');
$container = containerForHost($pdo, $containerID, $accountID);

if ($container['EventState'] !== 'draft') {
    http_response_code(409);
    exit('This event is no longer a draft.');
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
        $statement = $pdo->prepare('DELETE FROM ContainerBeers WHERE ContainerID = ?');
        $statement->execute([$containerID]);

        $validateBeer = $pdo->prepare('SELECT ID FROM BeerCatalog WHERE ID = ?');
        $insertBeer = $pdo->prepare(
            'INSERT INTO ContainerBeers (ContainerID, EventID, BeerID)
             VALUES (?, NULL, ?)'
        );

        foreach ($submittedBeerIDs as $beerID) {
            $validateBeer->execute([$beerID]);
            if ($validateBeer->fetchColumn() !== false) {
                $insertBeer->execute([$containerID, $beerID]);
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    redirect('edit-containers.php?event=' . urlencode($container['EventID']));
}

$beers = $pdo->query(
    'SELECT ID, Name, Brewery, Type, ABV FROM BeerCatalog ORDER BY Brewery, Name'
)->fetchAll();

$statement = $pdo->prepare('SELECT BeerID FROM ContainerBeers WHERE ContainerID = ?');
$statement->execute([$containerID]);
$selectedBeerIDs = array_flip($statement->fetchAll(PDO::FETCH_COLUMN));

pageStart('Choose container beers');
?>

<h1><?= html($container['Name']) ?></h1>
<h2>Choose beers</h2>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">
    <input type="hidden" name="container" value="<?= html($containerID) ?>">

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

<p><a href="edit-containers.php?event=<?= urlencode($container['EventID']) ?>">Cancel</a></p>

<?php pageEnd(); ?>

