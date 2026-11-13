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

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function stars(int $rating): string { return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); }
function quantityText(mixed $quantity): string { $n = (float) $quantity; return $n == floor($n) ? number_format($n, 0) : number_format($n, 2, '.', ''); }
function reviewDate(mixed $value): string { return (new DateTime((string) $value))->format('M j, Y H:i'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $reviewID = (string) ($_POST['review_id'] ?? '');

    if ($action === 'upvote') {
        $stmt = $pdo->prepare('SELECT r.ID FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID WHERE r.ID = ?');
        $stmt->execute([$reviewID]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('SELECT 1 FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?');
            $stmt->execute([$reviewID, $accountID]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare('DELETE FROM ReviewUpvotes WHERE ReviewID = ? AND AccountID = ?');
            } else {
                $stmt = $pdo->prepare('INSERT INTO ReviewUpvotes (ReviewID, AccountID) VALUES (?, ?)');
            }
            $stmt->execute([$reviewID, $accountID]);
        }
        header('Location: account.php');
        exit;
    }

    if ($action === 'delete_review') {
        $stmt = $pdo->prepare('SELECT r.ID FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID WHERE r.ID = ? AND p.AccountID = ? LIMIT 1');
        $stmt->execute([$reviewID, $accountID]);
        if ($stmt->fetch()) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM ReviewTags WHERE ReviewID = ?'); $stmt->execute([$reviewID]);
                $stmt = $pdo->prepare('DELETE FROM ReviewUpvotes WHERE ReviewID = ?'); $stmt->execute([$reviewID]);
                $stmt = $pdo->prepare('DELETE FROM Reviews WHERE ID = ?'); $stmt->execute([$reviewID]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($error->getMessage());
            }
        }
        header('Location: account.php');
        exit;
    }
}

$profileStmt = $pdo->prepare('SELECT * FROM Accounts WHERE ID = ?');
$profileStmt->execute([$accountID]);
$userProfile = $profileStmt->fetch();

$stmt = $pdo->prepare(
    'SELECT r.ID, r.Rating, r.Quantity, r.QuantityUnit, r.Comment, r.CreatedAt,
            b.Name AS BeerName, e.Title AS EventTitle, p.AccountID AS ReviewAccountID,
            COUNT(DISTINCT up.AccountID) AS Upvotes,
            MAX(CASE WHEN up.AccountID = ? THEN 1 ELSE 0 END) AS HasUpvoted,
            GROUP_CONCAT(DISTINCT t.Name ORDER BY t.Name SEPARATOR ", ") AS TagNames
     FROM Reviews r JOIN Participants p ON p.ID = r.ParticipantID
     JOIN Events e ON e.ID = p.EventID JOIN BeerCatalog b ON b.ID = r.BeerID
     LEFT JOIN ReviewUpvotes up ON up.ReviewID = r.ID
     LEFT JOIN ReviewTags rt ON rt.ReviewID = r.ID LEFT JOIN Tags t ON t.ID = rt.TagID
     WHERE p.AccountID = ?
     GROUP BY r.ID, r.Rating, r.Quantity, r.QuantityUnit, r.Comment, r.CreatedAt,
              b.Name, e.Title, p.AccountID ORDER BY r.CreatedAt DESC'
);
$stmt->execute([$accountID, $accountID]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT DISTINCT e.ID, e.Title, e.Type, e.State, e.CreatedAt
     FROM Events e JOIN Participants p ON p.EventID = e.ID
     WHERE p.AccountID = ? AND e.State = "closed" ORDER BY e.CreatedAt DESC'
);
$stmt->execute([$accountID]);
$archivedEvents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Details - Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 1000px; margin: 2rem auto; padding: 0 1rem; color: #202124; background: #fafafa; }
        .header { text-align: center; margin-bottom: 2rem; }
        .section-card { background: #fff; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .search-bar { display: flex; gap: .75rem; }
        .search-bar input { flex: 1; padding: .65rem; border: 1px solid #bbb; border-radius: 6px; font: inherit; }
        button, .button-link { padding: .6rem .9rem; border: 1px solid #aaa; border-radius: 6px; background: #f7f7f7; cursor: pointer; font: inherit; text-decoration: none; color: #202124; }
        .btn-add { color: #fff; background: #2563eb; border-color: #2563eb; }
        .empty-state { color: #666; font-style: italic; }
        .beer-list { padding: 0; list-style: none; }
        .beer-item { display: flex; justify-content: space-between; padding: .75rem 0; border-bottom: 1px solid #ddd; }
        .review-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; margin-top: 1rem; }
        .review-card { align-self: start; min-width: 0; padding: 1.1rem; background: #fff; border: 1px solid #d5d5d5; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
        .review-card h3 { margin-top: 0; margin-bottom: .4rem; }
        .review-card p { margin: .45rem 0; }
        .review-card-content { overflow-wrap: anywhere; }
        .review-date { color: #666; font-size: .9rem; }
        .rating-stars { color: #d69200; white-space: nowrap; }
        .review-actions { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: .85rem; }
        .review-actions form { margin: 0; }
        .review-action-group { display: flex; gap: .5rem; }
        .edit-button { color: #174ea6; border-color: #9db9e8; background: #f4f7ff; }
        .delete-button { color: #a40000; border-color: #d3a4a4; background: #fff5f5; }
        .nav-link { display: inline-block; margin-top: 1rem; color: #155eef; text-decoration: none; }
        @media (max-width: 700px) { .review-grid { grid-template-columns: 1fr; } .search-bar { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome, <?= h($userProfile['DisplayName'] ?? 'User') ?></h1>
        <p>Manage your favorite brews and past tasting events.</p>
    </div>

    <div class="section-card">
        <h2>My Favorite Beers</h2>
        <div class="search-bar"><input type="text" id="beerSearchInput" placeholder="Search for a beer to add..."><button class="btn-add" onclick="addBeer()">Add</button></div>
        <ul class="beer-list" id="favoriteBeersList"></ul>
        <div id="emptyListMessage" class="empty-state">No favorite beers added yet.</div>
    </div>

    <div class="section-card">
        <h2>My Reviews</h2>
        <?php if (!$reviews): ?>
            <p class="empty-state">You have not submitted any reviews yet.</p>
        <?php else: ?>
            <section class="review-grid">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <div class="review-card-content">
                            <h3><?= h($review['BeerName']) ?></h3>
                            <p>Event: <strong><?= h($review['EventTitle']) ?></strong></p>
                            <p class="review-date">Submitted: <?= h(reviewDate($review['CreatedAt'])) ?></p>
                            <p>Rating: <span class="rating-stars"><?= h(stars((int) $review['Rating'])) ?></span></p>
                            <p>Quantity: <?= h(quantityText($review['Quantity'])) ?> <?= h($review['QuantityUnit']) ?></p>
                            <?php if ($review['TagNames']): ?><p>Tags: <?= h($review['TagNames']) ?></p><?php endif; ?>
                            <?php if ($review['Comment']): ?><p><?= nl2br(h($review['Comment'])) ?></p><?php endif; ?>
                        </div>
                        <div class="review-actions">
                            <div class="review-action-group">
                                <form method="post"><input type="hidden" name="action" value="upvote"><input type="hidden" name="review_id" value="<?= h($review['ID']) ?>"><button type="submit"><?= $review['HasUpvoted'] ? 'Remove Upvote' : 'Upvote' ?> (<?= (int) $review['Upvotes'] ?>)</button></form>
                                <a class="button-link edit-button" href="edit-review.php?id=<?= h($review['ID']) ?>&return=account">Edit</a>
                            </div>
                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= h($review['ID']) ?>"><button type="submit" class="delete-button">Delete</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="section-card">
        <h2>Archived Events</h2>
        <?php if (!$archivedEvents): ?><p class="empty-state">No past events found.</p><?php else: ?><ul><?php foreach ($archivedEvents as $archivedEvent): ?><li><?= h($archivedEvent['Title']) ?> <?= h($archivedEvent['Type']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>

    <a href="index.php" class="nav-link">&larr; Back to Home</a>
    <script>
        function updateEmptyState() { const list = document.getElementById('favoriteBeersList'); document.getElementById('emptyListMessage').style.display = list.children.length === 0 ? 'block' : 'none'; }
        function addBeer() { const input = document.getElementById('beerSearchInput'); const name = input.value.trim(); if (!name) return; const item = document.createElement('li'); item.className = 'beer-item'; item.innerHTML = `<span>${name}</span><button class="delete-button" onclick="removeBeer(this)">Delete</button>`; document.getElementById('favoriteBeersList').appendChild(item); input.value = ''; updateEmptyState(); }
        function removeBeer(button) { button.parentElement.remove(); updateEmptyState(); }
        updateEmptyState();
    </script>
</body>
</html>
