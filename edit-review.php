<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

$credentials = $auth0->getCredentials();
if (!$credentials) {
    $_SESSION['target_role'] = 'account';
    header('Location: ' . $auth0->login());
    exit;
}

$accountID = (string) $credentials->user['sub'];
$reviewID = (string) ($_GET['id'] ?? $_POST['review_id'] ?? '');
$returnSource = (string) ($_GET['return'] ?? $_POST['return'] ?? 'account');
$returnPage = $returnSource === 'reviews' ? 'reviews.php' : 'account.php';
$units = ['ml', 'oz', 'glass', 'pint', 'bottle', 'can', 'serving'];

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$stmt = $pdo->prepare(
    'SELECT r.ID, r.Rating, r.Quantity, r.QuantityUnit, r.Comment,
            b.Name AS BeerName, e.Title AS EventTitle
     FROM Reviews r
     JOIN Participants p ON p.ID = r.ParticipantID
     JOIN Events e ON e.ID = p.EventID
     JOIN BeerCatalog b ON b.ID = r.BeerID
     WHERE r.ID = ? AND p.AccountID = ? LIMIT 1'
);
$stmt->execute([$reviewID, $accountID]);
$review = $stmt->fetch();
if (!$review) { http_response_code(404); exit('You do not have permission to edit this review.'); }

$message = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $quantity = (float) ($_POST['quantity'] ?? 0);
    $unit = trim((string) ($_POST['quantity_unit'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $postedTags = $_POST['tags'] ?? [];
    $tagIDs = array_values(array_unique(array_filter($postedTags, static fn ($tag): bool => is_string($tag))));

    if ($rating < 1 || $rating > 5) { $message = 'Please choose a star rating.'; $isError = true; }
    elseif ($quantity < 0) { $message = 'Quantity cannot be negative.'; $isError = true; }
    elseif (!in_array($unit, $units, true)) { $message = 'Please choose a valid quantity unit.'; $isError = true; }
    elseif (mb_strlen($comment) > 150) { $message = 'Comment must be 150 characters or fewer.'; $isError = true; }
    else {
        $validTags = [];
        if ($tagIDs) {
            $placeholders = implode(',', array_fill(0, count($tagIDs), '?'));
            $stmt = $pdo->prepare("SELECT ID FROM Tags WHERE ID IN ($placeholders)");
            $stmt->execute($tagIDs);
            $validTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE Reviews SET Rating = ?, Quantity = ?, QuantityUnit = ?, Comment = ? WHERE ID = ?');
            $stmt->execute([$rating, $quantity, $unit, $comment, $reviewID]);
            $stmt = $pdo->prepare('DELETE FROM ReviewTags WHERE ReviewID = ?');
            $stmt->execute([$reviewID]);
            foreach ($validTags as $tagID) {
                $stmt = $pdo->prepare('INSERT INTO ReviewTags (ReviewID, TagID) VALUES (?, ?)');
                $stmt->execute([$reviewID, $tagID]);
            }
            $pdo->commit();
            header('Location: ' . $returnPage);
            exit;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($error->getMessage());
            $message = 'Could not update the review.';
            $isError = true;
        }
    }
}

$stmt = $pdo->prepare('SELECT TagID FROM ReviewTags WHERE ReviewID = ?');
$stmt->execute([$reviewID]);
$selectedTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
$tags = $pdo->query('SELECT ID, Name FROM Tags ORDER BY Name')->fetchAll();
$selectedRating = (int) $review['Rating'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Review | Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 850px; margin: 2rem auto; padding: 0 1rem; color: #202124; background: #fafafa; }
        .review-form { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .review-form > label { display: grid; grid-template-columns: 100px 1fr; gap: .6rem; align-items: center; margin: .8rem 0; }
        .review-form select, .review-form input, .review-form textarea { width: 100%; box-sizing: border-box; padding: .55rem; border: 1px solid #bbb; border-radius: 6px; font: inherit; }
        .quantity-fields { display: grid; grid-template-columns: minmax(0, 1fr) 150px; gap: .6rem; }
        .star-rating { display: flex; gap: .2rem; }
        .star-button { width: auto !important; padding: 0; border: 0; background: transparent; color: #c7c7c7; font-size: 2rem; line-height: 1; cursor: pointer; }
        .star-button.selected { color: #e09a00; }
        .star-button:focus { outline: 2px solid #e09a00; outline-offset: 2px; }
        .review-tags { display: flex; align-items: flex-start; gap: 1rem; margin-top: 1rem; }
        .review-tags strong { min-width: 80px; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; }
        .tag-options label { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; }
        .tag-options input[type="checkbox"] { width: 16px !important; height: 16px; min-width: 16px; }
        .comment-wrapper { position: relative; }
        .comment-wrapper textarea { padding-bottom: 2rem; resize: vertical; }
        .comment-counter { position: absolute; right: .75rem; bottom: .6rem; color: #666; font-size: .85rem; }
        .comment-counter.limit-reached { color: #c00000; font-weight: bold; }
        button, .back-link { display: inline-block; margin-top: 1.25rem; padding: .65rem 1rem; border: 1px solid #aaa; border-radius: 6px; background: #f7f7f7; cursor: pointer; font: inherit; text-decoration: none; color: #202124; }
        .message { color: #075f20; font-weight: bold; }.error { color: #a40000; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Edit Review</h1>
    <p><strong>Beer:</strong> <?= h($review['BeerName']) ?></p>
    <p><strong>Event:</strong> <?= h($review['EventTitle']) ?></p>

    <?php if ($message): ?><p class="<?= $isError ? 'error' : 'message' ?>"><?= h($message) ?></p><?php endif; ?>

    <form method="post" class="review-form" id="edit-review-form">
        <input type="hidden" name="review_id" value="<?= h($reviewID) ?>">
        <input type="hidden" name="return" value="<?= h($returnSource) ?>">
        <label>Rating:
            <span>
                <input type="hidden" name="rating" id="rating" value="<?= $selectedRating ?>">
                <span class="star-rating" id="star-rating">
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?><button type="button" class="star-button <?= $rating <= $selectedRating ? 'selected' : '' ?>" data-rating="<?= $rating ?>"><?= $rating <= $selectedRating ? '★' : '☆' ?></button><?php endfor; ?>
                </span>
            </span>
        </label>
        <label>Quantity:
            <span class="quantity-fields"><input type="number" name="quantity" min="0" step="0.01" value="<?= h($review['Quantity']) ?>" required><select name="quantity_unit" required><?php foreach ($units as $unit): ?><option value="<?= h($unit) ?>" <?= $review['QuantityUnit'] === $unit ? 'selected' : '' ?>><?= h($unit) ?></option><?php endforeach; ?></select></span>
        </label>
        <label>Comment:
            <span class="comment-wrapper"><textarea id="comment" name="comment" maxlength="150" rows="4"><?= h($review['Comment']) ?></textarea><span id="comment-counter" class="comment-counter"><?= mb_strlen((string) $review['Comment']) ?>/150</span></span>
        </label>
        <div class="review-tags"><strong>Tags:</strong><div class="tag-options"><?php foreach ($tags as $tag): ?><label><input type="checkbox" name="tags[]" value="<?= h($tag['ID']) ?>" <?= in_array($tag['ID'], $selectedTags, true) ? 'checked' : '' ?>> <?= h($tag['Name']) ?></label><?php endforeach; ?></div></div>
        <button type="submit">Save Changes</button>
        <a href="<?= h($returnPage) ?>" class="back-link">Cancel</a>
    </form>

    <script>
        const ratingInput = document.getElementById('rating');
        const starRating = document.getElementById('star-rating');
        const starButtons = document.querySelectorAll('.star-button');
        let selectedRating = Number(ratingInput.value);
        const updateStars = (rating) => starButtons.forEach((button) => { const on = Number(button.dataset.rating) <= rating; button.textContent = on ? '★' : '☆'; button.classList.toggle('selected', on); });
        starButtons.forEach((button) => { button.addEventListener('mouseenter', () => updateStars(Number(button.dataset.rating))); button.addEventListener('click', () => { selectedRating = Number(button.dataset.rating); ratingInput.value = selectedRating; updateStars(selectedRating); }); });
        starRating.addEventListener('mouseleave', () => updateStars(selectedRating));
        const commentBox = document.getElementById('comment'); const counter = document.getElementById('comment-counter');
        const updateCounter = () => { counter.textContent = commentBox.value.length + '/150'; counter.classList.toggle('limit-reached', commentBox.value.length >= 150); };
        commentBox.addEventListener('input', updateCounter); updateCounter();
    </script>
</body>
</html>
