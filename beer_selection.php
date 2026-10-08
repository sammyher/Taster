<?php
require_once __DIR__ . '/config/db.php';

$eventId = trim($_POST['event_id'] ?? $_GET['event_id'] ?? '');
$containerId = trim($_POST['container_id'] ?? $_GET['container_id'] ?? '');
$search = trim($_GET['search'] ?? '');
$message = '';
$error = '';

$hasEvent = $eventId !== '';
$hasContainer = $containerId !== '';

if ($hasEvent === $hasContainer) {
    $error = 'Provide either an event ID or a container ID.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beerIds = $_POST['beer_ids'] ?? [];

    if (!is_array($beerIds) || count($beerIds) === 0) {
        $error = 'Select at least one beer.';
    } else {
        try {
            if ($hasEvent) {
                $stmt = $pdo->prepare("
                    INSERT INTO ContainerBeers (ContainerID, EventID, BeerID)
                    SELECT NULL, :event_id, :beer_id
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM ContainerBeers
                        WHERE EventID = :existing_event_id
                          AND BeerID = :existing_beer_id
                          AND ContainerID IS NULL
                    )
                ");

                foreach ($beerIds as $beerId) {
                    $stmt->execute([
                        ':event_id' => $eventId,
                        ':beer_id' => $beerId,
                        ':existing_event_id' => $eventId,
                        ':existing_beer_id' => $beerId,
                    ]);
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ContainerBeers (ContainerID, EventID, BeerID)
                    SELECT :container_id, NULL, :beer_id
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM ContainerBeers
                        WHERE ContainerID = :existing_container_id
                          AND BeerID = :existing_beer_id
                          AND EventID IS NULL
                    )
                ");

                foreach ($beerIds as $beerId) {
                    $stmt->execute([
                        ':container_id' => $containerId,
                        ':beer_id' => $beerId,
                        ':existing_container_id' => $containerId,
                        ':existing_beer_id' => $beerId,
                    ]);
                }
            }

            $message = 'Selected beers were added successfully.';
        } catch (PDOException $e) {
            $error = 'The beers could not be added. Make sure the event or container ID exists.';
        }
    }
}

$sql = "
    SELECT ID, Name, Brewery, Type, ABV, IBU, Description
    FROM BeerCatalog
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE Name LIKE :search
           OR Brewery LIKE :search
           OR Type LIKE :search
    ";

    $params['search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY Name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beers = $stmt->fetchAll();

function displayValue($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Beers</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f7f7f7;
        }

        h1 {
            text-align: center;
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

        .message {
            padding: 10px;
            margin-bottom: 20px;
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
        }

        .error {
            padding: 10px;
            margin-bottom: 20px;
            background: #ffebee;
            border: 1px solid #ef9a9a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
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
            width: 70px;
            text-align: center;
        }

        .submit-bar {
            position: sticky;
            top: 0;
            padding: 10px 0;
            background: #f7f7f7;
            z-index: 10;
        }
    </style>
</head>
<body>
    <h1>Select Beers</h1>

    <p>
        <a href="beer_catalog.php">View Full Beer Catalog</a>
        |
        <a href="index.php">Return to Home</a>
    </p>

    <?php if ($message !== ''): ?>
        <p class="message"><?= displayValue($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= displayValue($error) ?></p>
    <?php endif; ?>

    <form class="search-form" method="GET">
        <?php if ($hasEvent): ?>
            <input type="hidden" name="event_id" value="<?= displayValue($eventId) ?>">
        <?php elseif ($hasContainer): ?>
            <input type="hidden" name="container_id" value="<?= displayValue($containerId) ?>">
        <?php endif; ?>

        <input
            type="text"
            name="search"
            placeholder="Search by name, brewery, or type"
            value="<?= displayValue($search) ?>"
        >

        <button type="submit">Search</button>

        <?php if ($hasEvent): ?>
            <a href="beer_selection.php?event_id=<?= urlencode($eventId) ?>">Clear</a>
        <?php elseif ($hasContainer): ?>
            <a href="beer_selection.php?container_id=<?= urlencode($containerId) ?>">Clear</a>
        <?php else: ?>
            <a href="beer_selection.php">Clear</a>
        <?php endif; ?>
    </form>

    <p>
        Showing <?= count($beers) ?> beer(s)
    </p>

    <form method="POST">
        <?php if ($hasEvent): ?>
            <input type="hidden" name="event_id" value="<?= displayValue($eventId) ?>">
        <?php elseif ($hasContainer): ?>
            <input type="hidden" name="container_id" value="<?= displayValue($containerId) ?>">
        <?php endif; ?>

        <div class="submit-bar">
            <button type="submit">Add Selected Beers</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="select-column">Select</th>
                    <th>Name</th>
                    <th>Brewery</th>
                    <th>Type</th>
                    <th>ABV</th>
                    <th>IBU</th>
                    <th>Description</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($beers as $beer): ?>
                    <tr>
                        <td class="select-column">
                            <input
                                type="checkbox"
                                name="beer_ids[]"
                                value="<?= displayValue($beer['ID']) ?>"
                            >
                        </td>
                        <td><?= displayValue($beer['Name']) ?></td>
                        <td><?= displayValue($beer['Brewery']) ?></td>
                        <td><?= displayValue($beer['Type']) ?></td>
                        <td>
                            <?= $beer['ABV'] !== null
                                ? displayValue($beer['ABV']) . '%'
                                : 'N/A' ?>
                        </td>
                        <td>
                            <?= $beer['IBU'] !== null
                                ? displayValue($beer['IBU'])
                                : 'N/A' ?>
                        </td>
                        <td>
                            <?= $beer['Description'] !== ''
                                ? displayValue($beer['Description'])
                                : 'No description available' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</body>
</html>
