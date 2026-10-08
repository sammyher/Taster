<?php
declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();

$eventId = trim((string) ($_POST['event_id'] ?? $_GET['event_id'] ?? ''));
$containerId = trim((string) ($_POST['container_id'] ?? $_GET['container_id'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$favoritesOnly = ($_GET['favorites'] ?? '') === '1';

if ($eventId === '' && $containerId === '') {
    exit('Missing event ID.');
}

if ($eventId !== '') {
    $event = eventForHost($pdo, $eventId, $accountID);
    requireDraft($event);

    $pageTitle = $event['Title'];

    $selectedQuery = $pdo->prepare(
        'SELECT BeerID
         FROM ContainerBeers
         WHERE EventID = ? AND ContainerID IS NULL'
    );
    $selectedQuery->execute([$eventId]);

    $selectedBeerIds = array_map(
        'strval',
        $selectedQuery->fetchAll(PDO::FETCH_COLUMN)
    );
} else {
    $container = containerForHost($pdo, $containerId, $accountID);

    if (($container['EventState'] ?? '') !== 'draft') {
        exit('This event is no longer a draft.');
    }

    $pageTitle = $container['Name'];

    $selectedQuery = $pdo->prepare(
        'SELECT BeerID
         FROM ContainerBeers
         WHERE ContainerID = ?'
    );
    $selectedQuery->execute([$containerId]);

    $selectedBeerIds = array_map(
        'strval',
        $selectedQuery->fetchAll(PDO::FETCH_COLUMN)
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $beerIds = $_POST['beer_ids'] ?? [];

    if (!is_array($beerIds)) {
        $beerIds = [];
    }

    $beerIds = array_values(array_unique(array_filter(
        array_map('strval', $beerIds),
        static fn(string $id): bool => $id !== ''
    )));

    $pdo->beginTransaction();

    try {
        if ($eventId !== '') {
            $delete = $pdo->prepare(
                'DELETE FROM ContainerBeers
                 WHERE EventID = ? AND ContainerID IS NULL'
            );
            $delete->execute([$eventId]);

            $insert = $pdo->prepare(
                'INSERT INTO ContainerBeers
                 (ContainerID, EventID, BeerID)
                 VALUES (NULL, ?, ?)'
            );
        } else {
            $delete = $pdo->prepare(
                'DELETE FROM ContainerBeers
                 WHERE ContainerID = ?'
            );
            $delete->execute([$containerId]);

            $insert = $pdo->prepare(
                'INSERT INTO ContainerBeers
                 (ContainerID, EventID, BeerID)
                 VALUES (?, NULL, ?)'
            );
        }

        $validBeer = $pdo->prepare(
            'SELECT ID FROM BeerCatalog WHERE ID = ?'
        );

        foreach ($beerIds as $beerId) {
            $validBeer->execute([$beerId]);

            if ($validBeer->fetchColumn() !== false) {
                if ($eventId !== '') {
                    $insert->execute([$eventId, $beerId]);
                } else {
                    $insert->execute([$containerId, $beerId]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    if ($eventId !== '') {
        redirect(
            'beer_selection.php?event_id=' .
            urlencode($eventId) .
            '&saved=1'
        );
    }

    redirect(
        'beer_selection.php?container_id=' .
        urlencode($containerId) .
        '&saved=1'
    );
}

$sql = '
    SELECT DISTINCT
        BeerCatalog.ID,
        BeerCatalog.Name,
        BeerCatalog.Brewery,
        BeerCatalog.Type,
        BeerCatalog.ABV,
        BeerCatalog.IBU,
        BeerCatalog.Description
    FROM BeerCatalog
';

$params = [];

if ($favoritesOnly) {
    $sql .= '
        INNER JOIN FavoriteBeers
            ON FavoriteBeers.BeerID = BeerCatalog.ID
           AND FavoriteBeers.AccountID = :account_id
    ';

    $params['account_id'] = $accountID;
}

if ($search !== '') {
    $sql .= '
        WHERE BeerCatalog.Name LIKE :search
           OR BeerCatalog.Brewery LIKE :search
           OR BeerCatalog.Type LIKE :search
    ';

    $params['search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY BeerCatalog.Name';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$beers = $statement->fetchAll();

pageStart('Choose Event Beers');
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 40px;
        background: #f7f7f7;
    }

    h1 {
        text-align: center;
    }

    .top-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .search-form {
        text-align: center;
        margin-bottom: 25px;
    }

    input[type="text"] {
        padding: 10px;
        width: 300px;
    }

    button {
        padding: 10px 16px;
        cursor: pointer;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    th,
    td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background: #333;
        color: white;
    }

    tr:nth-child(even) {
        background: #f2f2f2;
    }

    .select-column {
        width: 80px;
        text-align: center;
    }

    .favorite-column {
        width: 110px;
        text-align: center;
    }

    .message {
        padding: 10px;
        margin-bottom: 20px;
        background: #e8f5e9;
        border: 1px solid #a5d6a7;
    }
</style>

<h1><?= html((string) $pageTitle) ?></h1>

<p>
    <a href="custom_beer.php?event_id=<?= html($eventId) ?>">
        Create Custom Beer
    </a>
</p>

<div class="top-actions">
    <a href="create-event.php">Back</a>

    <?php if ($eventId !== ''): ?>
        <form method="POST" action="open-event.php">
            <input
                type="hidden"
                name="event"
                value="<?= html($eventId) ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= html(csrfToken()) ?>"
            >

            <button type="submit">Start/Open Event</button>
        </form>
    <?php endif; ?>
</div>

<?php if (isset($_GET['saved'])): ?>
    <p class="message">Beer list saved.</p>
<?php endif; ?>

<form class="search-form" method="GET">
    <?php if ($eventId !== ''): ?>
        <input
            type="hidden"
            name="event_id"
            value="<?= html($eventId) ?>"
        >
    <?php else: ?>
        <input
            type="hidden"
            name="container_id"
            value="<?= html($containerId) ?>"
        >
    <?php endif; ?>

    <input
        type="text"
        name="search"
        placeholder="Search by name, brewery, or type"
        value="<?= html($search) ?>"
    >

    <label>
        <input
            type="checkbox"
            name="favorites"
            value="1"
            <?= $favoritesOnly ? 'checked' : '' ?>
        >
        Favorites only
    </label>

    <button type="submit">Search</button>
</form>

<p>Showing <?= count($beers) ?> beer(s)</p>

<form method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= html(csrfToken()) ?>"
    >

    <?php if ($eventId !== ''): ?>
        <input
            type="hidden"
            name="event_id"
            value="<?= html($eventId) ?>"
        >
    <?php else: ?>
        <input
            type="hidden"
            name="container_id"
            value="<?= html($containerId) ?>"
        >
    <?php endif; ?>

    <button type="submit">Save Beer List</button>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Brewery</th>
                <th>Type</th>
                <th>ABV</th>
                <th>IBU</th>
                <th>Description</th>
                <th class="favorite-column">Favorite</th>
                <th class="select-column">Select</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($beers as $beer): ?>
                <tr>
                    <td><?= html((string) $beer['Name']) ?></td>
                    <td><?= html((string) ($beer['Brewery'] ?? '')) ?></td>
                    <td><?= html((string) ($beer['Type'] ?? '')) ?></td>
                    <td>
                        <?= $beer['ABV'] !== null
                            ? html((string) $beer['ABV']) . '%'
                            : 'N/A' ?>
                    </td>
                    <td>
                        <?= $beer['IBU'] !== null
                            ? html((string) $beer['IBU'])
                            : 'N/A' ?>
                    </td>
                    <td>
                        <?= ($beer['Description'] ?? '') !== ''
                            ? html((string) $beer['Description'])
                            : 'No description available' ?>
                    </td>

                    <td class="favorite-column">
                        <button
                            type="submit"
                            formaction="favorite_beer.php"
                            name="beer_id"
                            value="<?= html((string) $beer['ID']) ?>"
                        >
                            Favorite
                        </button>
                    </td>

                    <td class="select-column">
                        <input
                            type="checkbox"
                            name="beer_ids[]"
                            value="<?= html((string) $beer['ID']) ?>"
                            <?= in_array(
                                (string) $beer['ID'],
                                $selectedBeerIds,
                                true
                            ) ? 'checked' : '' ?>
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<?php pageEnd(); ?>
