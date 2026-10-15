<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['account_id'])) {
    header('Location: login.php?role=attendee');
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountID = $_SESSION['account_id'];
$message = '';
$selectedEventID = (string) ($_GET['event'] ?? $_POST['event'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upvote') {
        $reviewID = (string) ($_POST['review_id'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT 1 FROM ReviewUpvotes
             WHERE ReviewID = ? AND AccountID = ?'
        );
        $stmt->execute([$reviewID, $accountID]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare(
                'DELETE FROM ReviewUpvotes
                 WHERE ReviewID = ? AND AccountID = ?'
            );
            $stmt->execute([$reviewID, $accountID]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO ReviewUpvotes (ReviewID, AccountID)
                 VALUES (?, ?)'
            );
            $stmt->execute([$reviewID, $accountID]);
        }

        header('Location: reviews.php?event=' . urlencode($selectedEventID));
        exit;
    }

    if ($action === 'save_review') {
        $beerID = (string) ($_POST['beer_id'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 0);
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $postedTags = $_POST['tags'] ?? [];

        $tags = array_values(array_unique(array_filter(
            $postedTags,
            static fn ($tag): bool => is_string($tag)
        )));

        if ($selectedEventID === '' || $beerID === '') {
            $message = 'Please choose an event and beer.';
        } elseif ($rating < 1 || $rating > 5) {
            $message = 'Rating must be between 1 and 5.';
        } elseif ($quantity < 0) {
            $message = 'Quantity cannot be negative.';
        } elseif (strlen($comment) > 500) {
            $message = 'Comment must be 500 characters or fewer.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT ID, State
                 FROM Events
                 WHERE ID = ?'
            );
            $stmt->execute([$selectedEventID]);
            $event = $stmt->fetch();

            $stmt = $pdo->prepare(
                'SELECT ID
                 FROM Participants
                 WHERE EventID = ? AND AccountID = ?'
            );
            $stmt->execute([$selectedEventID, $accountID]);
            $participant = $stmt->fetch();

            $stmt = $pdo->prepare(
                'SELECT b.ID
                 FROM ContainerBeers cb
                 JOIN BeerCatalog b ON b.ID = cb.BeerID
                 WHERE cb.EventID = ? AND b.ID = ?
                 LIMIT 1'
            );
            $stmt->execute([$selectedEventID, $beerID]);
            $beerExists = $stmt->fetch();

            if (!$event || $event['State'] !== 'open') {
                $message = 'Reviews are only available while the event is open.';
            } elseif (!$participant) {
                $message = 'You are not registered for this event.';
            } elseif (!$beerExists) {
                $message = 'That beer is not part of this event.';
            } else {
                $validTags = [];

                if ($tags) {
                    $placeholders = implode(',', array_fill(0, count($tags), '?'));

                    $stmt = $pdo->prepare(
                        "SELECT ID FROM Tags WHERE ID IN ($placeholders)"
                    );
                    $stmt->execute($tags);
                    $validTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }

                $stmt = $pdo->prepare(
                    'SELECT ID
                     FROM Reviews
                     WHERE ParticipantID = ? AND BeerID = ?'
                );
                $stmt->execute([$participant['ID'], $beerID]);
                $existingReview = $stmt->fetch();

                try {
                    $pdo->beginTransaction();

                    if ($existingReview) {
                        $reviewID = $existingReview['ID'];

                        $stmt = $pdo->prepare(
                            'UPDATE Reviews
                             SET Rating = ?, Quantity = ?, Comment = ?
                             WHERE ID = ?'
                        );
                        $stmt->execute([
                            $rating,
                            $quantity,
                            $comment,
                            $reviewID,
                        ]);

                        $stmt = $pdo->prepare(
                            'DELETE FROM ReviewTags WHERE ReviewID = ?'
                        );
                        $stmt->execute([$reviewID]);
                    } else {
                        $reviewID = bin2hex(random_bytes(16));

                        $stmt = $pdo->prepare(
                            'INSERT INTO Reviews
                             (ID, ParticipantID, BeerID, Rating, Quantity, Comment)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $reviewID,
                            $participant['ID'],
                            $beerID,
                            $rating,
                            $quantity,
                            $comment,
                        ]);
                    }

                    foreach ($validTags as $tagID) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO ReviewTags (ReviewID, TagID)
                             VALUES (?, ?)'
                        );
                        $stmt->execute([$reviewID, $tagID]);
                    }

                    $pdo->commit();
                    $message = 'Review saved successfully.';
                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $message = 'Could not save the review.';
                }
            }
        }
    }
}

$events = $pdo->query(
    'SELECT ID, Title, State
     FROM Events
     ORDER BY CreatedAt DESC'
)->fetchAll();

$tags = $pdo->query(
    'SELECT ID, Name
     FROM Tags
     ORDER BY Name'
)->fetchAll();

$selectedEvent = null;
$eventBeers = [];

foreach ($events as $event) {
    if ((string) $event['ID'] === $selectedEventID) {
        $selectedEvent = $event;
        break;
    }
}

if ($selectedEvent) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT
            b.ID,
            b.Name,
            b.Brewery,
            b.Type,
            b.ABV,
            b.IBU,
            b.Description
         FROM ContainerBeers cb
         JOIN BeerCatalog b ON b.ID = cb.BeerID
         WHERE cb.EventID = ?
         ORDER BY b.Name'
    );
    $stmt->execute([$selectedEventID]);
    $eventBeers = $stmt->fetchAll();
}

$stmt = $pdo->prepare(
    'SELECT
        r.ID,
        r.Rating,
        r.Quantity,
        r.Comment,
        r.CreatedAt,
        b.Name AS BeerName,
        b.Brewery,
        e.Title AS EventTitle,
        COUNT(DISTINCT up.AccountID) AS Upvotes,
        MAX(
            CASE WHEN up.AccountID = ? THEN 1 ELSE 0 END
        ) AS HasUpvoted,
        GROUP_CONCAT(
            DISTINCT t.Name
            ORDER BY t.Name
            SEPARATOR ", "
        ) AS Tags
     FROM Reviews r
     JOIN Participants p ON p.ID = r.ParticipantID
     JOIN Events e ON e.ID = p.EventID
     JOIN BeerCatalog b ON b.ID = r.BeerID
     LEFT JOIN ReviewUpvotes up ON up.ReviewID = r.ID
     LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID
     LEFT JOIN Tags t ON t.ID = rt.TagID
     GROUP BY
        r.ID,
        r.Rating,
        r.Quantity,
        r.Comment,
        r.CreatedAt,
        b.Name,
        b.Brewery,
        e.Title
     ORDER BY r.CreatedAt DESC'
);
$stmt->execute([$accountID]);
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Taster Reviews</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: 1px solid #bbb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        label {
            display: block;
            margin: 0.7rem 0;
        }

        input, select, textarea {
            padding: 0.45rem;
            max-width: 30rem;
        }

        textarea {
            width: 100%;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
        }

        .tags label {
            margin: 0;
        }

        .message {
            color: #075f20;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Beer Reviews</h1>

    <?php if ($message): ?>
        <p class="message"><?= h($message) ?></p>
    <?php endif; ?>

    <h2>Choose an Event</h2>

    <form method="get">
        <select name="event" required>
            <option value="">Select an event</option>

            <?php foreach ($events as $event): ?>
                <option
                    value="<?= h($event['ID']) ?>"
                    <?= $selectedEventID === (string) $event['ID'] ? 'selected' : '' ?>
                >
                    <?= h($event['Title']) ?>
                    (<?= h($event['State']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">View Event</button>
    </form>

    <?php if ($selectedEvent && $selectedEvent['State'] === 'open'): ?>
        <h2>Submit a Review</h2>

        <form method="post">
            <input type="hidden" name="action" value="save_review">
            <input type="hidden" name="event" value="<?= h($selectedEventID) ?>">

            <label>
                Beer:
                <select name="beer_id" required>
                    <option value="">Select a beer</option>

                    <?php foreach ($eventBeers as $beer): ?>
                        <option value="<?= h($beer['ID']) ?>">
                            <?= h($beer['Name']) ?>
                            — <?= h($beer['Brewery'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Rating:
                <select name="rating" required>
                    <option value="">Choose a rating</option>
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                        <option value="<?= $rating ?>">
                            <?= $rating ?>/5
                        </option>
                    <?php endfor; ?>
                </select>
            </label>

            <label>
                Quantity:
                <input
                    type="number"
                    name="quantity"
                    min="0"
                    step="0.01"
                    value="1"
                    required
                >
            </label>

            <label>
                Comment:
                <textarea name="comment" maxlength="500" rows="4"></textarea>
            </label>

            <p>Tags:</p>

            <div class="tags">
                <?php foreach ($tags as $tag): ?>
                    <label>
                        <input
                            type="checkbox"
                            name="tags[]"
                            value="<?= h($tag['ID']) ?>"
                        >
                        <?= h($tag['Name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit">Save Review</button>
        </form>
    <?php endif; ?>

    <h2>Reviews From All Events</h2>

    <?php if (!$reviews): ?>
        <p>No reviews have been submitted yet.</p>
    <?php endif; ?>

    <?php foreach ($reviews as $review): ?>
        <article class="card">
            <h3><?= h($review['BeerName']) ?></h3>

            <p>
                <?= h($review['Brewery'] ?? '') ?>
                — Event: <?= h($review['EventTitle']) ?>
            </p>

            <p>
                Rating:
                <?= str_repeat('★', (int) $review['Rating']) ?>
                <?= str_repeat('☆', 5 - (int) $review['Rating']) ?>
            </p>

            <p>Quantity: <?= h((string) $review['Quantity']) ?></p>

            <?php if ($review['Tags']): ?>
                <p>Tags: <?= h($review['Tags']) ?></p>
            <?php endif; ?>

            <?php if ($review['Comment']): ?>
                <p><?= nl2br(h($review['Comment'])) ?></p>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="upvote">
                <input type="hidden" name="event" value="<?= h($selectedEventID) ?>">
                <input type="hidden" name="review_id" value="<?= h($review['ID']) ?>">

                <button type="submit">
                    <?= $review['HasUpvoted'] ? 'Remove Upvote' : 'Upvote' ?>
                    (<?= (int) $review['Upvotes'] ?>)
                </button>
            </form>
        </article>
    <?php endforeach; ?>

    <p><a href="index.php">Return Home</a></p>
</body>
</html>