<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';

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

function quantityText(mixed $quantity): string
{
    $number = (float) $quantity;
    return $number == floor($number)
        ? number_format($number, 0)
        : number_format($number, 2, '.', '');
}

function reviewDate(mixed $value): string
{
    return (new DateTime((string) $value))->format('M j, Y H:i');
}

$eventID = (string) $_SESSION['event_id'];
$participantID = (string) $_SESSION['participant_id'];
$accountID = isset($_SESSION['account_id']) ? (string) $_SESSION['account_id'] : null;
$units = ['ml', 'oz', 'glass', 'pint', 'bottle', 'can', 'serving'];
$message = '';
$isError = false;

$stmt = $pdo->prepare(
    'SELECT e.ID, e.Title, e.Type, e.State
     FROM Events e JOIN Participants p ON p.EventID = e.ID
     WHERE e.ID = ? AND p.ID = ? LIMIT 1'
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
    $reviewID = (string) ($_POST['review_id'] ?? '');

    if ($action === 'upvote' && $accountID !== null) {
        $stmt = $pdo->prepare(
            'SELECT r.ID FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID
             WHERE r.ID = ? AND p.EventID = ?'
        );
        $stmt->execute([$reviewID, $eventID]);

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
        header('Location: reviews.php');
        exit;
    }

    if ($action === 'delete_review' && $accountID !== null) {
        $stmt = $pdo->prepare(
            'SELECT r.ID FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID
             WHERE r.ID = ? AND p.EventID = ? AND p.AccountID = ? LIMIT 1'
        );
        $stmt->execute([$reviewID, $eventID, $accountID]);

        if ($stmt->fetch()) {
            $pdo->beginTransaction();
            try {
                foreach (['ReviewTags', 'ReviewUpvotes'] as $table) {
                    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE ReviewID = ?");
                    $stmt->execute([$reviewID]);
                }
                $stmt = $pdo->prepare('DELETE FROM Reviews WHERE ID = ?');
                $stmt->execute([$reviewID]);
                $pdo->commit();
            } catch (Throwable $error) {
                $pdo->rollBack();
                error_log($error->getMessage());
            }
        }
        header('Location: reviews.php');
        exit;
    }

    if ($action === 'save_review') {
        $beerID = (string) ($_POST['beer_id'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 0);
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $unit = trim((string) ($_POST['quantity_unit'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $postedTags = $_POST['tags'] ?? [];
        $tagIDs = array_values(array_unique(array_filter(
            $postedTags, static fn ($tag): bool => is_string($tag)
        )));

        if ($beerID === '') {
            $message = 'Please choose a beer.'; $isError = true;
        } elseif ($rating < 1 || $rating > 5) {
            $message = 'Please choose a star rating.'; $isError = true;
        } elseif ($quantity < 0) {
            $message = 'Quantity cannot be negative.'; $isError = true;
        } elseif (!in_array($unit, $units, true)) {
            $message = 'Please choose a valid quantity unit.'; $isError = true;
        } elseif (mb_strlen($comment) > 150) {
            $message = 'Comment must be 150 characters or fewer.'; $isError = true;
        } elseif ($event['State'] !== 'open') {
            $message = 'Reviews are only available while the event is open.'; $isError = true;
        } else {
            $stmt = $pdo->prepare(
                'SELECT b.ID FROM ContainerBeers cb JOIN BeerCatalog b ON b.ID = cb.BeerID
                 LEFT JOIN BeerContainer bc ON bc.ID = cb.ContainerID
                 WHERE b.ID = ? AND (cb.EventID = ? OR bc.EventID = ?) LIMIT 1'
            );
            $stmt->execute([$beerID, $eventID, $eventID]);

            if (!$stmt->fetch()) {
                $message = 'That beer is not part of this event.'; $isError = true;
            } else {
                $stmt = $pdo->prepare(
                    'SELECT ID FROM Reviews WHERE ParticipantID = ? AND BeerID = ?'
                );
                $stmt->execute([$participantID, $beerID]);

                if ($stmt->fetch()) {
                    $message = 'You have already submitted a review for this beer.';
                    $isError = true;
                } else {
                    $placeholders = $tagIDs
                        ? implode(',', array_fill(0, count($tagIDs), '?'))
                        : '';
                    $validTags = [];
                    if ($tagIDs) {
                        $stmt = $pdo->prepare("SELECT ID FROM Tags WHERE ID IN ($placeholders)");
                        $stmt->execute($tagIDs);
                        $validTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }

                    try {
                        $pdo->beginTransaction();
                        $newID = bin2hex(random_bytes(16));
                        $stmt = $pdo->prepare(
                            'INSERT INTO Reviews
                             (ID, ParticipantID, BeerID, Rating, Quantity, QuantityUnit, Comment)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([$newID, $participantID, $beerID, $rating, $quantity, $unit, $comment]);
                        foreach ($validTags as $tagID) {
                            $stmt = $pdo->prepare(
                                'INSERT INTO ReviewTags (ReviewID, TagID) VALUES (?, ?)'
                            );
                            $stmt->execute([$newID, $tagID]);
                        }
                        $pdo->commit();
                        header('Location: reviews.php');
                        exit;
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log($error->getMessage());
                        $message = 'Could not save the review.'; $isError = true;
                    }
                }
            }
        }
    }
}

$tags = $pdo->query('SELECT ID, Name FROM Tags ORDER BY Name')->fetchAll();

$stmt = $pdo->prepare(
    'SELECT DISTINCT b.ID, b.Name, b.Brewery
     FROM ContainerBeers cb JOIN BeerCatalog b ON b.ID = cb.BeerID
     LEFT JOIN BeerContainer bc ON bc.ID = cb.ContainerID
     WHERE cb.EventID = ? OR bc.EventID = ? ORDER BY b.Name'
);
$stmt->execute([$eventID, $eventID]);
$eventBeers = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT r.ID, r.Rating, r.Quantity, r.QuantityUnit, r.Comment, r.CreatedAt,
            b.Name AS BeerName, p.AccountID AS ReviewAccountID,
            COUNT(DISTINCT up.AccountID) AS Upvotes,
            MAX(CASE WHEN up.AccountID = ? THEN 1 ELSE 0 END) AS HasUpvoted,
            GROUP_CONCAT(DISTINCT t.Name ORDER BY t.Name SEPARATOR ", ") AS TagNames
     FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID
     JOIN BeerCatalog b ON b.ID = r.BeerID
     LEFT JOIN ReviewUpvotes up ON up.ReviewID = r.ID
     LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID
     LEFT JOIN Tags t ON t.ID = rt.TagID
     WHERE p.EventID = ?
     GROUP BY r.ID, r.Rating, r.Quantity, r.QuantityUnit, r.Comment,
              r.CreatedAt, b.Name, p.AccountID
     ORDER BY r.CreatedAt DESC'
);
$stmt->execute([$accountID, $eventID]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($event['Title']) ?> Reviews | Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 1000px; margin: 2rem auto; padding: 0 1rem; color: #202124; background: #fafafa; }
        .top-nav { display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 1.5rem; }
        .message { color: #075f20; font-weight: bold; }
        .error { color: #a40000; font-weight: bold; }
        .review-form { max-width: 760px; background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .review-form > label { display: grid; grid-template-columns: 90px 1fr; gap: .6rem; align-items: center; margin: .8rem 0; }
        .review-form > label:nth-of-type(4) { align-items: start; }
        .review-form select, .review-form input, .review-form textarea { width: 100%; box-sizing: border-box; padding: .55rem; border: 1px solid #bbb; border-radius: 6px; font: inherit; }
        .quantity-fields { display: grid; grid-template-columns: minmax(0, 1fr) 150px; gap: .6rem; }
        .star-rating { display: flex; gap: .2rem; }
        .star-button { width: auto !important; padding: 0; border: 0; background: transparent; color: #c7c7c7; font-size: 2rem; line-height: 1; cursor: pointer; }
        .star-button.selected { color: #e09a00; }
        .star-button:focus { outline: 2px solid #e09a00; outline-offset: 2px; border-radius: 4px; }
        .review-tags { display: flex; align-items: flex-start; gap: 1rem; margin-top: 1rem; }
        .review-tags strong { min-width: 80px; }
        .tag-options { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1.25rem; }
        .tag-options label { display: inline-flex; align-items: center; gap: .35rem; margin: 0; white-space: nowrap; }
        .tag-options input[type="checkbox"] { display: inline-block; width: 16px !important; height: 16px; min-width: 16px; margin: 0; padding: 0; }
        .comment-wrapper { position: relative; }
        .comment-wrapper textarea { padding-bottom: 2rem; resize: vertical; }
        .comment-counter { position: absolute; right: .75rem; bottom: .6rem; color: #666; font-size: .85rem; }
        .comment-counter.limit-reached { color: #c00000; font-weight: bold; }
        .save-review { margin-top: 1.25rem; padding: .65rem 1rem; border: 0; border-radius: 6px; cursor: pointer; }
        .review-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; margin-top: 1rem; }
        .review-card { align-self: start; min-width: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid #d5d5d5; border-radius: 12px; padding: 1.1rem; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
        .review-card h3 { margin-top: 0; margin-bottom: .4rem; }
        .review-card p { margin: .45rem 0; }
        .review-card-content { overflow-wrap: anywhere; }
        .review-date { color: #666; font-size: .9rem; }
        .rating-stars { color: #d69200; white-space: nowrap; }
        .review-actions { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: .85rem; }
        .review-actions form { margin: 0; }
        .review-action-group { display: flex; gap: .5rem; }
        .review-actions button, .edit-button { padding: .5rem .75rem; border: 1px solid #aaa; border-radius: 6px; background: #f7f7f7; cursor: pointer; font: inherit; text-decoration: none; color: #202124; }
        .edit-button { color: #174ea6; border-color: #9db9e8; background: #f4f7ff; }
        .delete-button { color: #a40000 !important; border-color: #d3a4a4 !important; background: #fff5f5 !important; }
        @media (max-width: 700px) { .review-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="top-nav">
        <?php if ($accountID !== null): ?><a href="account.php">My Account</a><?php endif; ?>
        <a href="event-lobby.php">Event Lobby</a>
        <a href="index.php">Home</a>
    </nav>

    <h1><?= h($event['Title']) ?> Reviews</h1>

    <?php if ($message): ?>
        <p class="<?= $isError ? 'error' : 'message' ?>"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($event['State'] === 'open'): ?>
        <h2>Submit a Review</h2>
        <?php if (!$eventBeers): ?>
            <p>No beers have been added to this event yet.</p>
        <?php else: ?>
            <form method="post" class="review-form" id="review-form">
                <input type="hidden" name="action" value="save_review">
                <label>Beer:
                    <select name="beer_id" required>
                        <option value="">Select a beer</option>
                        <?php foreach ($eventBeers as $beer): ?>
                            <option value="<?= h($beer['ID']) ?>"><?= h($beer['Name']) ?> — <?= h($beer['Brewery'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Rating:
                    <span>
                        <input type="hidden" name="rating" id="rating" value="">
                        <span class="star-rating" id="star-rating" role="radiogroup" aria-label="Choose a rating from one to five stars">
                            <?php for ($rating = 1; $rating <= 5; $rating++): ?><button type="button" class="star-button" data-rating="<?= $rating ?>" aria-label="<?= $rating ?> stars" role="radio">☆</button><?php endfor; ?>
                        </span>
                    </span>
                </label>
                <label>Quantity:
                    <span class="quantity-fields">
                        <input type="number" name="quantity" min="0" step="0.01" value="1" required>
                        <select name="quantity_unit" required><option value="">Unit</option><?php foreach ($units as $unit): ?><option value="<?= h($unit) ?>"><?= h($unit) ?></option><?php endforeach; ?></select>
                    </span>
                </label>
                <label>Comment:
                    <span class="comment-wrapper"><textarea id="comment" name="comment" maxlength="150" rows="4"></textarea><span id="comment-counter" class="comment-counter">0/150</span></span>
                </label>
                <div class="review-tags"><strong>Tags:</strong><div class="tag-options"><?php foreach ($tags as $tag): ?><label><input type="checkbox" name="tags[]" value="<?= h($tag['ID']) ?>"> <?= h($tag['Name']) ?></label><?php endforeach; ?></div></div>
                <button type="submit" class="save-review">Save Review</button>
            </form>
        <?php endif; ?>
    <?php else: ?><p>This event is closed, so new reviews cannot be submitted.</p><?php endif; ?>

    <h2>Reviews for <?= h($event['Title']) ?></h2>
    <?php if (!$reviews): ?>
        <p>No reviews have been submitted for this event yet.</p>
    <?php else: ?>
        <section class="review-grid">
            <?php foreach ($reviews as $review): ?>
                <article class="review-card">
                    <div class="review-card-content">
                        <h3><?= h($review['BeerName']) ?></h3>
                        <p class="review-date">Submitted: <?= h(reviewDate($review['CreatedAt'])) ?></p>
                        <p>Rating: <span class="rating-stars"><?= h(stars((int) $review['Rating'])) ?></span></p>
                        <p>Quantity: <?= h(quantityText($review['Quantity'])) ?> <?= h($review['QuantityUnit']) ?></p>
                        <?php if ($review['TagNames']): ?><p>Tags: <?= h($review['TagNames']) ?></p><?php endif; ?>
                        <?php if ($review['Comment']): ?><p><?= nl2br(h($review['Comment'])) ?></p><?php endif; ?>
                    </div>
                    <div class="review-actions">
                        <?php if ($accountID !== null): ?>
                            <div class="review-action-group">
                                <form method="post"><input type="hidden" name="action" value="upvote"><input type="hidden" name="review_id" value="<?= h($review['ID']) ?>"><button type="submit"><?= $review['HasUpvoted'] ? 'Remove Upvote' : 'Upvote' ?> (<?= (int) $review['Upvotes'] ?>)</button></form>
                                <?php if ((string) $review['ReviewAccountID'] === $accountID): ?><a class="edit-button" href="edit-review.php?id=<?= h($review['ID']) ?>&return=reviews">Edit</a><?php endif; ?>
                            </div>
                            <?php if ((string) $review['ReviewAccountID'] === $accountID): ?><form method="post" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= h($review['ID']) ?>"><button type="submit" class="delete-button">Delete</button></form><?php endif; ?>
                        <?php else: ?><em>Log in to upvote reviews.</em><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <script>
        const commentBox = document.getElementById('comment');
        const commentCounter = document.getElementById('comment-counter');
        if (commentBox && commentCounter) {
            const updateCounter = () => {
                commentCounter.textContent = commentBox.value.length + '/150';
                commentCounter.classList.toggle('limit-reached', commentBox.value.length >= 150);
            };
            commentBox.addEventListener('input', updateCounter);
            updateCounter();
        }

        const ratingInput = document.getElementById('rating');
        const starRating = document.getElementById('star-rating');
        const starButtons = document.querySelectorAll('.star-button');
        let selectedRating = 0;
        const updateStars = (rating) => starButtons.forEach((button) => {
            const on = Number(button.dataset.rating) <= rating;
            button.textContent = on ? '★' : '☆';
            button.classList.toggle('selected', on);
        });
        starButtons.forEach((button) => {
            button.addEventListener('mouseenter', () => updateStars(Number(button.dataset.rating)));
            button.addEventListener('click', () => { selectedRating = Number(button.dataset.rating); ratingInput.value = selectedRating; updateStars(selectedRating); });
        });
        starRating?.addEventListener('mouseleave', () => updateStars(selectedRating));
        document.getElementById('review-form')?.addEventListener('submit', (event) => {
            if (!ratingInput.value) { event.preventDefault(); alert('Please choose a star rating.'); }
        });
    </script>
</body>
</html>
