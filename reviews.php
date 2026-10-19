<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['account_id'])) {
    header('Location: login.php?role=attendee');
    exit;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stars(int $rating): string
{
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

$accountID = (string) $_SESSION['account_id'];
$selectedEventID = (string) ($_GET['event'] ?? $_POST['event'] ?? '');
$message = '';
$duplicateReviewID = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upvote') {
        $reviewID = (string) ($_POST['review_id'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT r.ID
             FROM Reviews r
             JOIN Participants p ON p.ID = r.ParticipantID
             WHERE r.ID = ? AND p.EventID = ?'
        );
        $stmt->execute([$reviewID, $selectedEventID]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?'
            );
            $stmt->execute([$reviewID, $accountID]);

            if ($stmt->fetch()) {
                $stmt = $pdo->prepare(
                    'DELETE FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO ReviewUpvotes (ReviewID, AccountID) VALUES (?, ?)'
                );
            }
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
        $tagIDs = array_values(array_unique(array_filter(
            $postedTags,
            static fn ($tag): bool => is_string($tag)
        )));

        if ($selectedEventID === '' || $beerID === '') {
            $message = 'Please choose an event and beer.';
            $messageIsError = true;
        } elseif ($rating < 1 || $rating > 5) {
            $message = 'Rating must be between 1 and 5.';
            $messageIsError = true;
        } elseif ($quantity < 0) {
            $message = 'Quantity cannot be negative.';
            $messageIsError = true;
        } elseif (strlen($comment) > 500) {
            $message = 'Comment must be 500 characters or fewer.';
            $messageIsError = true;
        } else {
            $stmt = $pdo->prepare('SELECT ID, State FROM Events WHERE ID = ?');
            $stmt->execute([$selectedEventID]);
            $event = $stmt->fetch();

            $stmt = $pdo->prepare(
                'SELECT ID FROM Participants WHERE EventID = ? AND AccountID = ?'
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
                $messageIsError = true;
            } elseif (!$participant) {
                $message = 'You are not registered for this event.';
                $messageIsError = true;
            } elseif (!$beerExists) {
                $message = 'That beer is not part of this event.';
                $messageIsError = true;
            } else {
                $stmt = $pdo->prepare(
                    'SELECT ID FROM Reviews WHERE ParticipantID = ? AND BeerID = ?'
                );
                $stmt->execute([$participant['ID'], $beerID]);
                $existingReview = $stmt->fetch();

                if ($existingReview) {
                    $message = 'You have already submitted a review for this beer.';
                    $duplicateReviewID = (string) $existingReview['ID'];
                    $messageIsError = true;
                } else {
                    $validTags = [];
                    if ($tagIDs) {
                        $placeholders = implode(',', array_fill(0, count($tagIDs), '?'));
                        $stmt = $pdo->prepare("SELECT ID FROM Tags WHERE ID IN ($placeholders)");
                        $stmt->execute($tagIDs);
                        $validTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }

                    try {
                        $pdo->beginTransaction();
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

                        foreach ($validTags as $tagID) {
                            $stmt = $pdo->prepare(
                                'INSERT INTO ReviewTags (ReviewID, TagID) VALUES (?, ?)'
                            );
                            $stmt->execute([$reviewID, $tagID]);
                        }

                        $pdo->commit();
                        header('Location: reviews.php?event=' . urlencode($selectedEventID));
                        exit;
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $message = 'Could not save the review.';
                        $messageIsError = true;
                    }
                }
            }
        }
    }
}

$events = $pdo->query(
    'SELECT ID, Title, State FROM Events ORDER BY CreatedAt DESC'
)->fetchAll();

$tags = $pdo->query(
    'SELECT ID, Name FROM Tags ORDER BY Name'
)->fetchAll();

$selectedEvent = null;
foreach ($events as $event) {
    if ((string) $event['ID'] === $selectedEventID) {
        $selectedEvent = $event;
        break;
    }
}

$eventBeers = [];
if ($selectedEvent) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT b.ID, b.Name, b.Brewery, b.Type, b.ABV, b.IBU, b.Description
         FROM ContainerBeers cb
         JOIN BeerCatalog b ON b.ID = cb.BeerID
         WHERE cb.EventID = ?
         ORDER BY b.Name'
    );
    $stmt->execute([$selectedEventID]);
    $eventBeers = $stmt->fetchAll();
}

$reviews = [];
if ($selectedEvent) {
    $stmt = $pdo->prepare(
        'SELECT r.ID, r.Rating, r.Quantity, r.Comment, r.CreatedAt,
                p.AccountID AS ReviewAccountID, b.Name AS BeerName, b.Brewery,
                e.Title AS EventTitle,
                COUNT(DISTINCT up.AccountID) AS Upvotes,
                MAX(CASE WHEN up.AccountID = ? THEN 1 ELSE 0 END) AS HasUpvoted,
                GROUP_CONCAT(DISTINCT t.Name ORDER BY t.Name SEPARATOR ", ") AS TagNames
         FROM Reviews r
         JOIN Participants p ON p.ID = r.ParticipantID
         JOIN Events e ON e.ID = p.EventID
         JOIN BeerCatalog b ON b.ID = r.BeerID
         LEFT JOIN ReviewUpvotes up ON up.ReviewID = r.ID
         LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID
         LEFT JOIN Tags t ON t.ID = rt.TagID
         WHERE p.EventID = ?
         GROUP BY r.ID, r.Rating, r.Quantity, r.Comment, r.CreatedAt,
                  p.AccountID, b.Name, b.Brewery, e.Title
         ORDER BY r.CreatedAt DESC'
    );
    $stmt->execute([$accountID, $selectedEventID]);
    $reviews = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beer Reviews | Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .top-nav { display: flex; justify-content: flex-end; gap: 1rem; }
        .card { border: 1px solid #bbb; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .message { color: #075f20; font-weight: bold; }
        .error { color: #a40000; font-weight: bold; }
        .review-form { max-width: 575px; }
        .review-form > label { display: grid; grid-template-columns: 80px 1fr; gap: .5rem; align-items: center; margin: .75rem 0; }
        .review-form > label > select, .review-form > label > input, .review-form > label > textarea { width: 100%; box-sizing: border-box; padding: .45rem; }
        .review-form > label:nth-of-type(4) { align-items: start; }
        .review-tags { display: flex; align-items: flex-start; gap: 1rem; margin-top: 1rem; }
        .review-tags strong { min-width: 80px; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .7rem; }
        .tag-options label { margin: 0; white-space: nowrap; }
        .save-review { margin-top: 1.25rem; }
        .rating-stars { white-space: nowrap; letter-spacing: 0; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="account.php">My Reviews</a>
        <a href="index.php">Home</a>
    </nav>

    <h1>Beer Reviews</h1>

    <?php if ($message): ?>
        <p class="<?= $messageIsError ? 'error' : 'message' ?>">
            <?= h($message) ?>
            <?php if ($duplicateReviewID): ?>
                You can edit your review <a href="account.php?edit=<?= h($duplicateReviewID) ?>">here</a>.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <h2>Choose an Event</h2>
    <form method="get">
        <select name="event" required>
            <option value="">Select an event</option>
            <?php foreach ($events as $event): ?>
                <option value="<?= h($event['ID']) ?>" <?= $selectedEventID === (string) $event['ID'] ? 'selected' : '' ?>>
                    <?= h($event['Title']) ?> (<?= h($event['State']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">View Event</button>
    </form>

    <?php if ($selectedEvent && $selectedEvent['State'] === 'open'): ?>
        <h2>Submit a Review</h2>
        <form method="post" class="review-form">
            <input type="hidden" name="action" value="save_review">
            <input type="hidden" name="event" value="<?= h($selectedEventID) ?>">

            <label>
                Beer:
                <select name="beer_id" required>
                    <option value="">Select a beer</option>
                    <?php foreach ($eventBeers as $beer): ?>
                        <option value="<?= h($beer['ID']) ?>">
                            <?= h($beer['Name']) ?> — <?= h($beer['Brewery'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Rating:
                <select name="rating" required>
                    <option value="">Choose a rating</option>
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                        <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                    <?php endfor; ?>
                </select>
            </label>

            <label>
                Quantity:
                <input type="number" name="quantity" min="0" step="0.01" value="1" required>
            </label>

            <label>
                Comment:
                <textarea name="comment" maxlength="500" rows="4"></textarea>
            </label>

            <div class="review-tags">
                <strong>Tags:</strong>
                <div class="tag-options">
                    <?php foreach ($tags as $tag): ?>
                        <label>
                            <input type="checkbox" name="tags[]" value="<?= h($tag['ID']) ?>">
                            <?= h($tag['Name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="save-review">Save Review</button>
        </form>
    <?php elseif ($selectedEvent): ?>
        <p>This event is closed, so new reviews cannot be submitted.</p>
    <?php endif; ?>

    <?php if ($selectedEvent): ?>
        <h2>Reviews for <?= h($selectedEvent['Title']) ?></h2>
        <?php if (!$reviews): ?>
            <p>No reviews have been submitted for this event yet.</p>
        <?php endif; ?>

        <?php foreach ($reviews as $review): ?>
            <article class="card">
                <h3><?= h($review['BeerName']) ?></h3>
                <p>Rating: <span class="rating-stars"><?= h(stars((int) $review['Rating'])) ?></span></p>
                <p>Quantity: <?= h($review['Quantity']) ?></p>
                <?php if ($review['TagNames']): ?>
                    <p>Tags: <?= h($review['TagNames']) ?></p>
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
    <?php else: ?>
        <p>Select an event to see its reviews.</p>
    <?php endif; ?>
</body>
</html>
