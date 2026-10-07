<?php

declare(strict_types=1);

require_once __DIR__ . '/config/event-helpers.php';

$accountID = requireAccountID();
$errors = [];
$title = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $title = trim((string) ($_POST['title'] ?? ''));
    $type = (string) ($_POST['type'] ?? '');
    $allowedTypes = ['small', 'festival', 'tour'];

    if ($title === '' || mb_strlen($title) > 100) {
        $errors[] = 'Enter an event title between 1 and 100 characters.';
    }

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Select a valid event type.';
    }

    if ($errors === []) {
        $eventID = generateID();
        $participantID = generateID();

        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO Events (ID, Title, Type, State, HostID)
                 VALUES (?, ?, ?, \'draft\', NULL)'
            );
            $statement->execute([$eventID, $title, $type]);

            $statement = $pdo->prepare(
                'INSERT INTO Participants (ID, EventID, AccountID)
                 VALUES (?, ?, ?)'
            );
            $statement->execute([$participantID, $eventID, $accountID]);

            $statement = $pdo->prepare(
                'UPDATE Events SET HostID = ? WHERE ID = ?'
            );
            $statement->execute([$participantID, $eventID]);

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        if ($type === 'small') {
            redirect('edit-small-event.php?event=' . urlencode($eventID));
        }

        redirect('edit-containers.php?event=' . urlencode($eventID));
    }
}

pageStart('Create an event');
?>

<h1>Create an event</h1>
<p class="muted">The event will remain a draft until you open it.</p>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= html($error) ?></p>
<?php endforeach; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= html(csrfToken()) ?>">

    <label>
        Event title
        <input type="text" name="title" maxlength="100" required value="<?= html($title) ?>">
    </label>

    <label>
        Event type
        <select name="type" required>
            <option value="">Select a type</option>
            <option value="small" <?= $type === 'small' ? 'selected' : '' ?>>Small gathering</option>
            <option value="festival" <?= $type === 'festival' ? 'selected' : '' ?>>Beer festival</option>
            <option value="tour" <?= $type === 'tour' ? 'selected' : '' ?>>Beer tour</option>
        </select>
    </label>

    <button type="submit">Create draft event</button>
</form>

<?php pageEnd(); ?>

