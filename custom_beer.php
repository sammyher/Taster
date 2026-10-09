<?php
declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountId = requireAccountID();
$eventId = trim((string) ($_GET['event_id'] ?? $_POST['event_id'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $name = trim($_POST['name'] ?? '');
    $brewery = trim($_POST['brewery'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $abv = trim($_POST['abv'] ?? '');
    $ibu = trim($_POST['ibu'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $type === '') {
        $error = 'Name and type are required.';
    } else {
        $beerId = bin2hex(random_bytes(16));

        $statement = $pdo->prepare(
            'INSERT INTO BeerCatalog
            (ID, Name, Brewery, Type, ABV, IBU, Description, AccountID)
            VALUES (?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?)'
        );

        $statement->execute([
            $beerId,
            $name,
            $brewery,
            $type,
            $abv,
            $ibu,
            $description,
            $accountId
        ]);

        if ($eventId !== '') {
            redirect(
                'beer_selection.php?event_id=' .
                urlencode($eventId)
            );
        }

        redirect('beer_catalog.php');
    }
}

pageStart('Create Custom Beer');
?>

<h1>Create Custom Beer</h1>

<?php if ($error !== ''): ?>
    <p><?= html($error) ?></p>
<?php endif; ?>

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
    <?php endif; ?>

    <p>
        <label>
            Name<br>
            <input type="text" name="name" required>
        </label>
    </p>

    <p>
        <label>
            Brewery<br>
            <input type="text" name="brewery">
        </label>
    </p>

    <p>
        <label>
            Type<br>
            <input type="text" name="type" required>
        </label>
    </p>

    <p>
        <label>
            ABV<br>
            <input type="number" name="abv" step="0.01" min="0">
        </label>
    </p>

    <p>
        <label>
            IBU<br>
            <input type="number" name="ibu" step="0.01" min="0">
        </label>
    </p>

    <p>
        <label>
            Description<br>
            <textarea name="description"></textarea>
        </label>
    </p>

    <button type="submit">Create Beer</button>
</form>

<?php pageEnd(); ?>
