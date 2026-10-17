<?php

declare(strict_types=1);

session_start();

// Reuse the existing Auth0 configuration without changing the main login flow.
require_once __DIR__ . '/config/auth.php';

function statsHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$credentials = $auth0->getCredentials();

// The existing login.php and callback.php remain unchanged. A user who is not
// signed in can use the existing attendee login, then return to this page.
if (!$credentials) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Archived Event Stats | Taster</title>
        <style>
            body { margin: 0; background: #f8fafc; color: #1f2937; font-family: sans-serif; }
            main { width: min(34rem, calc(100% - 2rem)); margin: 8rem auto; padding: 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: .85rem; }
            a { display: inline-block; margin: .5rem .75rem .25rem 0; color: #2563eb; }
        </style>
    </head>
    <body>
    <main>
        <h1>Archived event stats</h1>
        <p>Sign in through the existing attendee login before viewing archived event statistics.</p>
        <a href="login.php?role=attendee">Sign in</a>
        <a href="index.php">Back to Home</a>
    </main>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/config/db.php';

$accountID = (string) ($credentials->user['sub'] ?? '');

if ($accountID === '') {
    http_response_code(403);
    exit('Unable to identify the signed-in account.');
}

$requestedEventID = trim((string) ($_GET['event'] ?? $_GET['event_id'] ?? ''));
$eventID = $requestedEventID;
$events = [];
$selectedEvent = null;
$eventStats = [
    'review_count' => 0,
    'reviewer_count' => 0,
    'beer_count' => 0,
    'average_rating' => 0,
    'total_quantity' => 0,
];
$ratingDistribution = array_fill(1, 5, 0);
$beerStats = [];
$errorMessage = '';

try {
    // Event membership connects the authenticated account to archived events.
    $eventStatement = $pdo->prepare(
        "SELECT DISTINCT e.ID, e.Title, e.Type, e.State, e.CreatedAt
         FROM Events e
         INNER JOIN Participants event_participant
             ON event_participant.EventID = e.ID
         WHERE event_participant.AccountID = :account_id
           AND LOWER(e.State) = 'archived'
         ORDER BY e.CreatedAt DESC, e.Title ASC"
    );
    $eventStatement->execute([':account_id' => $accountID]);
    $events = $eventStatement->fetchAll();

    if ($events !== []) {
        if ($eventID === '') {
            $eventID = (string) $events[0]['ID'];
        }

        foreach ($events as $event) {
            if ((string) $event['ID'] === $eventID) {
                $selectedEvent = $event;
                break;
            }
        }

        if ($selectedEvent !== null) {
            $statsStatement = $pdo->prepare(
                "SELECT COUNT(r.ID) AS review_count,
                        COUNT(DISTINCT r.ParticipantID) AS reviewer_count,
                        COUNT(DISTINCT r.BeerID) AS beer_count,
                        COALESCE(ROUND(AVG(r.Rating), 2), 0) AS average_rating,
                        COALESCE(SUM(r.Quantity), 0) AS total_quantity
                 FROM Reviews r
                 INNER JOIN Participants review_participant
                     ON review_participant.ID = r.ParticipantID
                 WHERE review_participant.EventID = :event_id"
            );
            $statsStatement->execute([':event_id' => $eventID]);
            $eventStats = $statsStatement->fetch() ?: $eventStats;

            $ratingStatement = $pdo->prepare(
                "SELECT r.Rating, COUNT(*) AS rating_count
                 FROM Reviews r
                 INNER JOIN Participants review_participant
                     ON review_participant.ID = r.ParticipantID
                 WHERE review_participant.EventID = :event_id
                 GROUP BY r.Rating"
            );
            $ratingStatement->execute([':event_id' => $eventID]);

            foreach ($ratingStatement->fetchAll() as $rating) {
                $ratingValue = (int) $rating['Rating'];
                if ($ratingValue >= 1 && $ratingValue <= 5) {
                    $ratingDistribution[$ratingValue] = (int) $rating['rating_count'];
                }
            }

            $beerStatement = $pdo->prepare(
                "SELECT b.ID,
                        b.Name,
                        b.Brewery,
                        b.Type,
                        COUNT(r.ID) AS review_count,
                        COUNT(DISTINCT r.ParticipantID) AS reviewer_count,
                        ROUND(AVG(r.Rating), 2) AS average_rating,
                        COALESCE(SUM(r.Quantity), 0) AS total_quantity
                 FROM Reviews r
                 INNER JOIN Participants review_participant
                     ON review_participant.ID = r.ParticipantID
                 INNER JOIN BeerCatalog b
                     ON b.ID = r.BeerID
                 WHERE review_participant.EventID = :event_id
                 GROUP BY b.ID, b.Name, b.Brewery, b.Type
                 ORDER BY average_rating DESC, review_count DESC, b.Name ASC"
            );
            $beerStatement->execute([':event_id' => $eventID]);
            $beerStats = $beerStatement->fetchAll();
        } elseif ($eventID !== '') {
            $errorMessage = 'That archived event is not available for this account.';
        }
    }
} catch (PDOException $exception) {
    $errorMessage = 'Stats are not available right now. Make sure the event and review tables are set up.';
}

$reviewCount = (int) ($eventStats['review_count'] ?? 0);
$reviewerCount = (int) ($eventStats['reviewer_count'] ?? 0);
$beerCount = (int) ($eventStats['beer_count'] ?? 0);
$averageRating = (float) ($eventStats['average_rating'] ?? 0);
$totalQuantity = (float) ($eventStats['total_quantity'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archived Event Stats | Taster</title>
    <style>
        :root { color-scheme: light; --ink: #1f2937; --muted: #64748b; --line: #e2e8f0; --panel: #fff; --accent: #2563eb; --page: #f8fafc; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .page { width: min(1100px, calc(100% - 2rem)); margin: 0 auto; padding: 2.5rem 0 4rem; }
        .header { margin-bottom: 1.5rem; }
        .header h1 { margin: 0 0 .45rem; }
        .header p { margin: 0; color: var(--muted); }
        .panel, .stat-card { background: var(--panel); border: 1px solid var(--line); border-radius: .85rem; box-shadow: 0 5px 18px rgba(15, 23, 42, .04); }
        .panel { padding: 1.25rem; margin-bottom: 1.25rem; }
        .event-picker { display: flex; align-items: end; gap: .75rem; flex-wrap: wrap; }
        .event-picker label { display: grid; gap: .35rem; min-width: min(100%, 28rem); flex: 1; font-weight: 600; }
        select, button { font: inherit; border: 1px solid #cbd5e1; border-radius: .45rem; padding: .65rem .75rem; background: #fff; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
        .stat-card { padding: 1rem; }
        .stat-card span { display: block; color: var(--muted); font-size: .9rem; }
        .stat-card strong { display: block; margin-top: .3rem; font-size: 1.65rem; }
        .content-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(16rem, .8fr); gap: 1.25rem; align-items: start; }
        h2 { margin: 0 0 .85rem; font-size: 1.15rem; }
        .muted, .empty { color: var(--muted); }
        .empty { margin: 0; }
        .error { margin: 0; border-left: 4px solid #dc2626; background: #fef2f2; color: #991b1b; padding: .85rem 1rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .75rem .5rem; border-bottom: 1px solid var(--line); text-align: left; }
        th { color: var(--muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        td:not(:first-child), th:not(:first-child) { text-align: right; }
        .beer-name { font-weight: 600; }
        .beer-meta { display: block; color: var(--muted); font-size: .85rem; margin-top: .2rem; }
        .rating-row { display: grid; grid-template-columns: 3.5rem 1fr 2.5rem; gap: .6rem; align-items: center; margin: .75rem 0; }
        .rating-bar { height: .55rem; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .rating-bar span { display: block; height: 100%; background: var(--accent); border-radius: inherit; }
        .rating-row strong { text-align: right; font-size: .9rem; }
        .footer-link { display: inline-block; margin-top: .25rem; color: var(--accent); }
        @media (max-width: 760px) { .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 440px) { .summary-grid { grid-template-columns: 1fr; } .page { width: min(100% - 1rem, 1100px); padding-top: 1.25rem; } }
    </style>
</head>
<body>
<main class="page">
    <header class="header">
        <h1>Archived event stats</h1>
        <p>Review-based results for events you participated in.</p>
    </header>

    <section class="panel" aria-labelledby="event-selection-heading">
        <h2 id="event-selection-heading">Choose an archived event</h2>

        <?php if ($events === []): ?>
            <p class="empty">No archived events are available for this account yet.</p>
        <?php else: ?>
            <form class="event-picker" method="get">
                <label for="event">
                    Archived event
                    <select id="event" name="event" onchange="this.form.submit()">
                        <?php foreach ($events as $event): ?>
                            <option
                                value="<?= statsHtml((string) $event['ID']) ?>"
                                <?= $selectedEvent !== null && (string) $selectedEvent['ID'] === (string) $event['ID'] ? 'selected' : '' ?>
                            >
                                <?= statsHtml((string) $event['Title']) ?>
                                (<?= statsHtml((string) $event['CreatedAt']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript><button type="submit">View stats</button></noscript>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <section class="panel"><p class="error"><?= statsHtml($errorMessage) ?></p></section>
    <?php elseif ($selectedEvent !== null): ?>
        <section class="panel">
            <h2><?= statsHtml((string) $selectedEvent['Title']) ?></h2>
            <p class="muted">
                <?= statsHtml((string) $selectedEvent['Type']) ?>
                &middot; Archived <?= statsHtml((string) $selectedEvent['CreatedAt']) ?>
            </p>
        </section>

        <section class="summary-grid" aria-label="Event summary">
            <article class="stat-card"><span>Reviews</span><strong><?= $reviewCount ?></strong></article>
            <article class="stat-card"><span>Reviewers</span><strong><?= $reviewerCount ?></strong></article>
            <article class="stat-card"><span>Beers reviewed</span><strong><?= $beerCount ?></strong></article>
            <article class="stat-card"><span>Average rating</span><strong><?= number_format($averageRating, 2) ?>/5</strong></article>
        </section>

        <div class="content-grid">
            <section class="panel" aria-labelledby="beer-results-heading">
                <h2 id="beer-results-heading">Beer results</h2>
                <?php if ($beerStats === []): ?>
                    <p class="empty">No reviews have been recorded for this event.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th scope="col">Beer</th>
                                <th scope="col">Avg. rating</th>
                                <th scope="col">Reviews</th>
                                <th scope="col">Reviewers</th>
                                <th scope="col">Quantity</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($beerStats as $beer): ?>
                                <?php $beerQuantity = (float) ($beer['total_quantity'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <span class="beer-name"><?= statsHtml((string) $beer['Name']) ?></span>
                                        <span class="beer-meta">
                                            <?= statsHtml((string) ($beer['Brewery'] ?? '')) ?>
                                            <?= $beer['Type'] !== null ? '&middot; ' . statsHtml((string) $beer['Type']) : '' ?>
                                        </span>
                                    </td>
                                    <td><?= number_format((float) $beer['average_rating'], 2) ?>/5</td>
                                    <td><?= (int) $beer['review_count'] ?></td>
                                    <td><?= (int) $beer['reviewer_count'] ?></td>
                                    <td><?= $beerQuantity > 0 ? number_format($beerQuantity, 2) : '&mdash;' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel" aria-labelledby="rating-distribution-heading">
                <h2 id="rating-distribution-heading">Rating distribution</h2>
                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                    <?php
                    $ratingCount = (int) ($ratingDistribution[$rating] ?? 0);
                    $ratingPercentage = $reviewCount > 0 ? ($ratingCount / $reviewCount) * 100 : 0;
                    ?>
                    <div class="rating-row">
                        <span><?= $rating ?> star</span>
                        <div class="rating-bar" aria-hidden="true">
                            <span style="width: <?= number_format($ratingPercentage, 2, '.', '') ?>%"></span>
                        </div>
                        <strong><?= $ratingCount ?></strong>
                    </div>
                <?php endfor; ?>
                <p class="muted">
                    Recorded quantity: <?= $totalQuantity > 0 ? number_format($totalQuantity, 2) : '&mdash;' ?>
                </p>
            </section>
        </div>
    <?php endif; ?>

    <a class="footer-link" href="index.php">&larr; Back to Home</a>
</main>
</body>
</html>
