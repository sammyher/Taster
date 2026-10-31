<?php
require_once __DIR__ . '/config/db.php';

$search = trim($_GET['search'] ?? '');

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

$sql .= " ORDER BY Name";

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
    <title>Beer Catalog</title>

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

        input {
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
    </style>
</head>
<body>

    <h1>Beer Catalog</h1>

    <form class="search-form" method="GET">
        <input
            type="text"
            name="search"
            placeholder="Search by name, brewery, or type"
            value="<?= displayValue($search) ?>"
        >

        <button type="submit">Search</button>

        <a href="beer_catalog.php">Clear</a>
    </form>

    <p>
        Showing <?= count($beers) ?> beer(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Brewery</th>
                <th>Type</th>
                <th>ABV</th>
                <th>IBU</th>
                <th>Description</th>
		<th>Favorite</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($beers as $beer): ?>
                <tr>
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
		    <td>
                        <form method="POST" action="favorite_beer.php">
                            <input
                            type="hidden"
                            name="beer_id"
                            value="<?= displayValue($beer['ID']) ?>"
                            >
                            <button type="submit">Favorite</button>
                            </form>
		    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
