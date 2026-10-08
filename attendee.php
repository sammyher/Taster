<?php
session_start();

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: login.php?role=attendee');
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$accountId = $_SESSION['user']['id'];
$message = '';
$selectedEventId = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beerId = (int) ($_POST['beer_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($beerId <= 0) {
        $message = 'Please enter a valid beer ID.';
    } elseif ($rating < 1 || $rating > 5) {
        $message = 'Rating must be between 1 and 5.';
    } elseif ($quantity < 0) {
        $message = 'Quantity cannot be negative.';
    } else {
        $stmt = $pdo->prepare("
            SELECT ID
            FROM Events
            WHERE ID = :event_id AND State = 'open'
        ");

        $stmt->execute([
            ':event_id' => $selectedEventId,
        ]);

        if (!$stmt->fetch()) {
            $message = 'This event is not open for reviews.';
        } else {
            $stmt = $pdo->prepare("
                SELECT ID
                FROM Participants
                WHERE EventID = :event_id AND AccountID = :account_id
            ");

            $stmt->execute([
                ':event_id' => $selectedEventId,
                ':account_id' => $accountId,
            ]);

            $participant = $stmt->fetch();

            if (!$participant) {
                $stmt = $pdo->prepare("
                    INSERT INTO Participants (EventID, AccountID)
                    VALUES (:event_id, :account_id)
                ");

                $stmt->execute([
                    ':event_id' => $selectedEventId,
                    ':account_id' => $accountId,
                ]);

                $participantId = (int) $pdo->lastInsertId();
            } else {
                $participantId = (int) $participant['ID'];
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Reviews
                        (ParticipantID, BeerID, Quantity, Rating, Comment)
                    VALUES
                        (:participant_id, :beer_id, :quantity, :rating, :comment)
                ");

                $stmt->execute([
                    ':participant_id' => $participantId,
                    ':beer_id' => $beerId,
                    ':quantity' => $quantity,
                    ':rating' => $rating,
                    ':comment' => $comment,
                ]);

                $message = 'Review submitted successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] ?? 0 == 1062) {
                    $message = 'You already reviewed this beer.';
                } else {
                    $message = 'Could not save the review.';
                }
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT ID, Type
    FROM Events
    WHERE State = 'open'
    ORDER BY ID DESC
");

$openEvents = $stmt->fetchAll();

$selectedEvent = null;
$participantId = null;
$reviews = [];

if ($selectedEventId > 0) {
    $stmt = $pdo->prepare("
        SELECT ID, Type
        FROM Events
        WHERE ID = :event_id AND State = 'open'
    ");

    $stmt->execute([
        ':event_id' => $selectedEventId,
    ]);

    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        $stmt = $pdo->prepare("
            SELECT ID
            FROM Participants
            WHERE EventID = :event_id AND AccountID = :account_id
        ");

        $stmt->execute([
            ':event_id' => $selectedEventId,
            ':account_id' => $accountId,
        ]);

        $participant = $stmt->fetch();

        if ($participant) {
            $participantId = (int) $participant['ID'];

            $stmt = $pdo->prepare("
                SELECT BeerID, Quantity, Rating, Comment
                FROM Reviews
                WHERE ParticipantID = :participant_id
                ORDER BY ID DESC
            ");

            $stmt->execute([
                ':participant_id' => $participantId,
            ]);

            $reviews = $stmt->fetchAll();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendee Reviews</title>
</head>
<body>
    <h1>Attendee Reviews</h1>

    <p>
        Welcome,
        <?= e($_SESSION['user']['displayName']) ?>
    </p>

    <?php if ($message): ?>
        <p><?= e($message) ?></p>
    <?php endif; ?>

    <h2>Choose an Open Event</h2>

    <?php if (empty($openEvents)): ?>
        <p>There are no open events right now.</p>
    <?php else: ?>
        <form method="get">
            <select name="event_id" required>
                <option value="">Select an event</option>

                <?php foreach ($openEvents as $event): ?>
                    <option
                        value="<?= (int) $event['ID'] ?>"
                        <?= $selectedEventId === (int) $event['ID'] ? 'selected' : '' ?>
                    >
                        <?= e($event['Type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Enter Event</button>
        </form>
    <?php endif; ?>

    <?php if ($selectedEvent): ?>
        <h2>
            Review:
            <?= e($selectedEvent['Type']) ?>
        </h2>

        <form method="post">
            <input
                type="hidden"
                name="event_id"
                value="<?= (int) $selectedEvent['ID'] ?>"
            >

            <p>
                <label>
                    Beer ID:
                    <input type="number" name="beer_id" min="1" required>
                </label>
            </p>

            <p>
                <label>
                    Rating:
                    <select name="rating" required>
                        <option value="">Choose rating</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </label>
            </p>

            <p>
                <label>
                    Quantity:
                    <input
                        type="number"
                        name="quantity"
                        min="0"
                        value="1"
                        required
                    >
                </label>
            </p>

            <p>
                <label>
                    Comment:<br>
                    <textarea name="comment" rows="4" cols="40"></textarea>
                </label>
            </p>

            <button type="submit">Submit Review</button>
        </form>

        <h2>Your Submitted Reviews</h2>

        <?php if (empty($reviews)): ?>
            <p>You have not submitted any reviews yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <article>
                    <p>Beer ID: <?= (int) $review['BeerID'] ?></p>
                    <p>Rating: <?= (int) $review['Rating'] ?>/5</p>
                    <p>Quantity: <?= (int) $review['Quantity'] ?></p>
                    <p><?= e($review['Comment'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="index.php">Return to Home</a></p>
</body>
</html>