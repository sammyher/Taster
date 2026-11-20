<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';

// A review belongs to a participant, so registered and anonymous attendees
// can both use this page after joining an event.
if (empty($_SESSION['event_id']) || empty($_SESSION['participant_id'])) {
    header('Location: index.php');
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

$eventID = (string) $_SESSION['event_id'];
$participantID = (string) $_SESSION['participant_id'];
$accountID = isset($_SESSION['account_id']) ? (string) $_SESSION['account_id'] : null;
$message = '';
$duplicateReviewID = '';
$messageIsError = false;

// Confirm that the active participant actually belongs to the active event.
$stmt = $pdo->prepare(
    'SELECT e.ID, e.Title, e.Type, e.State
     FROM Events e
     JOIN Participants p ON p.EventID = e.ID
     WHERE e.ID = ? AND p.ID = ?
     LIMIT 1'
);
$stmt->execute([$eventID, $participantID]);
$event = $stmt->fetch();

if (!$event) {
    unset($_SESSION['event_id'], $_SESSION['participant_id'], $_SESSION['event_code']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    /*
     * FUTURE FEATURE: review upvotes.
     * Keep disabled until ReviewUpvotes is in the shared schema. With the
     * current AccountID design, anonymous participants cannot upvote.
     *
     * if ($action === 'upvote' && $accountID !== null) {
     *     $reviewID = (string) ($_POST['review_id'] ?? '');
     *     $stmt = $pdo->prepare(
     *         'SELECT r.ID FROM Reviews r
     *          JOIN Participants p ON p.ID = r.ParticipantID
     *          WHERE r.ID = ? AND p.EventID = ?'
     *     );
     *     $stmt->execute([$reviewID, $eventID]);
     *
     *     if ($stmt->fetch()) {
     *         $stmt = $pdo->prepare(
     *             'SELECT 1 FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?'
     *         );
     *         $stmt->execute([$reviewID, $accountID]);
     *         $stmt = $stmt->fetch()
     *             ? $pdo->prepare('DELETE FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?')
     *             : $pdo->prepare('INSERT INTO ReviewUpvotes (ReviewID, AccountID) VALUES (?, ?)');
     *         $stmt->execute([$reviewID, $accountID]);
     *     }
     *     header('Location: reviews.php');
     *     exit;
     * }
     */

    if ($action === 'save_review') {
        $beerID = (string) ($_POST['beer_id'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 0);
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));

        /*
         * FUTURE FEATURE: review tags.
         * $postedTags = $_POST['tags'] ?? [];
         * $tagIDs = array_values(array_unique(array_filter(
         *     $postedTags,
         *     static fn ($tag): bool => is_string($tag)
         * )));
         */

        if ($beerID === '') {
            $message = 'Please choose a beer.';
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
        } elseif ($event['State'] !== 'open') {
            $message = 'Reviews are only available while the event is open.';
            $messageIsError = true;
        } else {
            // Small-event beers use cb.EventID. Festival/tour beers use a
            // BeerContainer whose EventID identifies the current event.
            $stmt = $pdo->prepare(
                'SELECT b.ID
                 FROM ContainerBeers cb
                 JOIN BeerCatalog b ON b.ID = cb.BeerID
                 LEFT JOIN BeerContainer bc ON bc.ID = cb.ContainerID
                 WHERE b.ID = ? AND (cb.EventID = ? OR bc.EventID = ?)
                 LIMIT 1'
            );
            $stmt->execute([$beerID, $eventID, $eventID]);

            if (!$stmt->fetch()) {
                $message = 'That beer is not part of this event.';
                $messageIsError = true;
            } else {
                // Current rule: one review per participant per beer.
                $stmt = $pdo->prepare(
                    'SELECT ID FROM Reviews WHERE ParticipantID = ? AND BeerID = ?'
                );
                $stmt->execute([$participantID, $beerID]);
                $existingReview = $stmt->fetch();

                if ($existingReview) {
                    $message = 'You have already submitted a review for this beer.';
                    $duplicateReviewID = (string) $existingReview['ID'];
                    $messageIsError = true;
                } else {
                    /*
                     * FUTURE FEATURE: validate tag IDs here, then insert them
                     * into ReviewTags inside the transaction below.
                     */
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
                            $participantID,
                            $beerID,
                            $rating,
                            $quantity,
                            $comment,
                        ]);

                        /*
                         * FUTURE FEATURE: review tags.
                         * foreach ($validTags as $tagID) {
                         *     $stmt = $pdo->prepare(
                         *         'INSERT INTO ReviewTags (ReviewID, TagID) VALUES (?, ?)'
                         *     );
                         *     $stmt->execute([$reviewID, $tagID]);
                         * }
                         */

                        $pdo->commit();
                        header('Location: reviews.php');
                        exit;
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Review save error: ' . $error->getMessage());
                        $message = 'Could not save the review.';
                        $messageIsError = true;
                    }
                }
            }
        }
    }
}

// Load direct event beers and beers held in the event's containers.
$stmt = $pdo->prepare(
    'SELECT DISTINCT b.ID, b.Name, b.Brewery, b.Type,
            b.ABV, b.IBU, b.Description
     FROM ContainerBeers cb
     JOIN BeerCatalog b ON b.ID = cb.BeerID
     LEFT JOIN BeerContainer bc ON bc.ID = cb.ContainerID
     WHERE cb.EventID = ? OR bc.EventID = ?
     ORDER BY b.Name'
);
$stmt->execute([$eventID, $eventID]);
$eventBeers = $stmt->fetchAll();

/*
 * FUTURE FEATURE: tags.
 * $tags = $pdo->query('SELECT ID, Name FROM Tags ORDER BY Name')->fetchAll();
 */
$tags = [];

$stmt = $pdo->prepare(
    'SELECT r.ID, r.Rating, r.Quantity, r.Comment, r.CreatedAt,
            p.AccountID AS ReviewAccountID, b.Name AS BeerName, b.Brewery
     FROM Reviews r
     JOIN Participants p ON p.ID = r.ParticipantID
     JOIN BeerCatalog b ON b.ID = r.BeerID
     WHERE p.EventID = ?
     ORDER BY r.CreatedAt DESC'
);
$stmt->execute([$eventID]);
$reviews = $stmt->fetchAll();

/*
 * FUTURE QUERY ADDITIONS for tags and upvotes:
 * LEFT JOIN ReviewUpvotes up ON up.ReviewID = r.ID
 * LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID
 * LEFT JOIN Tags t ON t.ID = rt.TagID
 * COUNT(DISTINCT up.AccountID) AS Upvotes
 * MAX(CASE WHEN up.AccountID = ? THEN 1 ELSE 0 END) AS HasUpvoted
 * GROUP_CONCAT(DISTINCT t.Name ORDER BY t.Name SEPARATOR ", ") AS TagNames
 */
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
        .review-form > label > select,
        .review-form > label > input,
        .review-form > label > textarea { width: 100%; box-sizing: border-box; padding: .45rem; }
        .save-review { margin-top: 1.25rem; }
        .rating-stars { white-space: nowrap; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="event-lobby.php">Event Lobby</a>
        <?php if ($accountID !== null): ?>
            <a href="account.php">My Account</a>
        <?php endif; ?>
        <a href="index.php">Home</a>
    </nav>

    <h1><?= h($event['Title']) ?> Reviews</h1>

    <?php if ($message): ?>
        <p class="<?= $messageIsError ? 'error' : 'message' ?>">
            <?= h($message) ?>
            <?php if ($duplicateReviewID && $accountID !== null): ?>
                You can edit your review
                <a href="account.php?edit=<?= h($duplicateReviewID) ?>">here</a>.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($event['State'] === 'open'): ?>
        <h2>Submit a Review</h2>
        <?php if (!$eventBeers): ?>
            <p>No beers have been added to this event yet.</p>
        <?php else: ?>
            <form method="post" class="review-form">
                <input type="hidden" name="action" value="save_review">

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

                <!-- FUTURE FEATURE: restore the tag checkboxes here after
                     Tags and ReviewTags are added to the shared schema. -->

                <button type="submit" class="save-review">Save Review</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p>This event is closed, so new reviews cannot be submitted.</p>
    <?php endif; ?>

    <h2>Reviews for <?= h($event['Title']) ?></h2>
    <?php if (!$reviews): ?>
        <p>No reviews have been submitted for this event yet.</p>
    <?php endif; ?>

    <?php foreach ($reviews as $review): ?>
        <article class="card">
            <h3><?= h($review['BeerName']) ?></h3>
            <p>Rating: <span class="rating-stars"><?= h(stars((int) $review['Rating'])) ?></span></p>
            <p>Quantity: <?= h($review['Quantity']) ?></p>

            <!-- FUTURE FEATURE: display review tags here. -->

            <?php if ($review['Comment']): ?>
                <p><?= nl2br(h($review['Comment'])) ?></p>
            <?php endif; ?>

            <!-- FUTURE FEATURE: restore the upvote form here after
                 ReviewUpvotes is added to the shared schema. -->
        </article>
    <?php endforeach; ?>
</body>
</html>
