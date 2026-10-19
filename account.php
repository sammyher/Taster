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
$message = '';
$messageIsError = false;
$editReviewID = (string) ($_GET['edit'] ?? '');

$tags = $pdo->query(
    'SELECT ID, Name FROM Tags ORDER BY Name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $reviewID = (string) ($_POST['review_id'] ?? '');

    if ($action === 'update_review') {
        $editReviewID = $reviewID;
    }

    $ownership = $pdo->prepare(
        'SELECT r.ID, r.BeerID
         FROM Reviews r
         JOIN Participants p ON p.ID = r.ParticipantID
         WHERE r.ID = ? AND p.AccountID = ?'
    );
    $ownership->execute([$reviewID, $accountID]);
    $ownedReview = $ownership->fetch();

    if (!$ownedReview) {
        $message = 'That review does not belong to your account.';
        $messageIsError = true;
    } elseif ($action === 'delete_review') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('DELETE FROM ReviewTags WHERE ReviewID = ?');
            $stmt->execute([$reviewID]);

            $stmt = $pdo->prepare('DELETE FROM ReviewUpvotes WHERE ReviewID = ?');
            $stmt->execute([$reviewID]);

            $stmt = $pdo->prepare('DELETE FROM Reviews WHERE ID = ?');
            $stmt->execute([$reviewID]);

            $pdo->commit();
            header('Location: account.php?deleted=1');
            exit;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'The review could not be deleted.';
            $messageIsError = true;
        }
    } elseif ($action === 'update_review') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $postedTags = $_POST['tags'] ?? [];
        $selectedTags = array_values(array_unique(array_filter(
            $postedTags,
            static fn ($tag): bool => is_string($tag)
        )));

        if ($rating < 1 || $rating > 5) {
            $message = 'Rating must be between 1 and 5.';
            $messageIsError = true;
        } elseif ($quantity < 0) {
            $message = 'Quantity cannot be negative.';
            $messageIsError = true;
        } elseif (strlen($comment) > 500) {
            $message = 'Comment must be 500 characters or fewer.';
            $messageIsError = true;
        } else {
            $placeholders = $selectedTags
                ? implode(',', array_fill(0, count($selectedTags), '?'))
                : '';
            $validTags = [];

            if ($selectedTags) {
                $stmt = $pdo->prepare(
                    "SELECT ID FROM Tags WHERE ID IN ($placeholders)"
                );
                $stmt->execute($selectedTags);
                $validTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'UPDATE Reviews
                     SET Rating = ?, Quantity = ?, Comment = ?
                     WHERE ID = ?'
                );
                $stmt->execute([$rating, $quantity, $comment, $reviewID]);

                $stmt = $pdo->prepare('DELETE FROM ReviewTags WHERE ReviewID = ?');
                $stmt->execute([$reviewID]);

                foreach ($validTags as $tagID) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ReviewTags (ReviewID, TagID) VALUES (?, ?)'
                    );
                    $stmt->execute([$reviewID, $tagID]);
                }

                $pdo->commit();
                header('Location: account.php?updated=1');
                exit;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'The review could not be updated.';
                $messageIsError = true;
            }
        }
    }
}

if (isset($_GET['deleted'])) {
    $message = 'Review deleted.';
} elseif (isset($_GET['updated'])) {
    $message = 'Review updated successfully.';
}

$editReview = null;
$selectedTags = [];

if ($editReviewID !== '') {
    $stmt = $pdo->prepare(
        'SELECT r.ID, r.BeerID, r.Rating, r.Quantity, r.Comment,
                b.Name AS BeerName, b.Brewery, e.Title AS EventTitle
         FROM Reviews r
         JOIN Participants p ON p.ID = r.ParticipantID
         JOIN BeerCatalog b ON b.ID = r.BeerID
         JOIN Events e ON e.ID = p.EventID
         WHERE r.ID = ? AND p.AccountID = ?'
    );
    $stmt->execute([$editReviewID, $accountID]);
    $editReview = $stmt->fetch();

    if ($editReview) {
        $stmt = $pdo->prepare(
            'SELECT TagID FROM ReviewTags WHERE ReviewID = ?'
        );
        $stmt->execute([$editReviewID]);
        $selectedTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $message = 'That review could not be found.';
        $messageIsError = true;
    }
}

$stmt = $pdo->prepare(
    'SELECT r.ID, r.Rating, r.Quantity, r.Comment, r.CreatedAt,
            b.Name AS BeerName, b.Brewery, e.Title AS EventTitle,
            e.State AS EventState,
            GROUP_CONCAT(DISTINCT t.Name ORDER BY t.Name SEPARATOR ", ") AS TagNames
     FROM Reviews r
     JOIN Participants p ON p.ID = r.ParticipantID
     JOIN Events e ON e.ID = p.EventID
     JOIN BeerCatalog b ON b.ID = r.BeerID
     LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID
     LEFT JOIN Tags t ON t.ID = rt.TagID
     WHERE p.AccountID = ?
     GROUP BY r.ID, r.Rating, r.Quantity, r.Comment, r.CreatedAt,
              b.Name, b.Brewery, e.Title, e.State
     ORDER BY r.CreatedAt DESC'
);
$stmt->execute([$accountID]);
$myReviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Reviews | Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .top-nav { display: flex; justify-content: flex-end; gap: 1rem; }
        .card { border: 1px solid #bbb; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .message { color: #075f20; font-weight: bold; }
        .error { color: #a40000; }
        label { display: block; margin: .7rem 0; }
        input, select, textarea { box-sizing: border-box; padding: .45rem; max-width: 30rem; }
        textarea { width: 100%; }
        .edit-form { max-width: 575px; }
        .edit-form > label { display: grid; grid-template-columns: 90px 1fr; gap: .5rem; align-items: center; }
        .edit-form > label > textarea { grid-column: 2; }
        .review-tags { display: flex; gap: 1rem; align-items: flex-start; margin-top: 1rem; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .7rem; }
        .tag-options label { margin: 0; white-space: nowrap; }
        .rating-stars { white-space: nowrap; letter-spacing: 0; }
        .actions { display: flex; gap: .75rem; align-items: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="reviews.php">Beer Reviews</a>
        <a href="index.php">Home</a>
    </nav>

    <h1>My Reviews</h1>

    <?php if ($message): ?>
        <p class="<?= $messageIsError ? 'error' : 'message' ?>"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($editReview): ?>
        <section class="card">
            <h2>Edit Review</h2>
            <p><strong><?= h($editReview['BeerName']) ?></strong> — <?= h($editReview['EventTitle']) ?></p>
            <form method="post" class="edit-form">
                <input type="hidden" name="action" value="update_review">
                <input type="hidden" name="review_id" value="<?= h($editReview['ID']) ?>">

                <label>
                    Rating:
                    <select name="rating" required>
                        <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                            <option value="<?= $rating ?>" <?= (int) $editReview['Rating'] === $rating ? 'selected' : '' ?>>
                                <?= $rating ?>/5
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>

                <label>
                    Quantity:
                    <input type="number" name="quantity" min="0" step="0.01" value="<?= h($editReview['Quantity']) ?>" required>
                </label>

                <label>
                    Comment:
                    <textarea name="comment" maxlength="500" rows="4"><?= h($editReview['Comment']) ?></textarea>
                </label>

                <div class="review-tags">
                    <strong>Tags:</strong>
                    <div class="tag-options">
                        <?php foreach ($tags as $tag): ?>
                            <label>
                                <input type="checkbox" name="tags[]" value="<?= h($tag['ID']) ?>" <?= in_array($tag['ID'], $selectedTags, true) ? 'checked' : '' ?>>
                                <?= h($tag['Name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit">Save Changes</button>
                    <a href="account.php">Cancel</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <h2>Reviews You Have Submitted</h2>
    <?php if (!$myReviews): ?>
        <p>You have not submitted any reviews yet.</p>
    <?php else: ?>
        <?php foreach ($myReviews as $review): ?>
            <article class="card">
                <h3><?= h($review['BeerName']) ?></h3>
                <p>Event: <?= h($review['EventTitle']) ?></p>
                <p>Rating: <span class="rating-stars"><?= h(stars((int) $review['Rating'])) ?></span></p>
                <p>Quantity: <?= h($review['Quantity']) ?></p>
                <?php if ($review['TagNames']): ?>
                    <p>Tags: <?= h($review['TagNames']) ?></p>
                <?php endif; ?>
                <?php if ($review['Comment']): ?>
                    <p><?= nl2br(h($review['Comment'])) ?></p>
                <?php endif; ?>
                <div class="actions">
                    <a href="account.php?edit=<?= h($review['ID']) ?>">Edit Review</a>
                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                        <input type="hidden" name="action" value="delete_review">
                        <input type="hidden" name="review_id" value="<?= h($review['ID']) ?>">
                        <button type="submit">Delete Review</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
