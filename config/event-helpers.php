<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$databaseResult = require_once __DIR__ . '/db.php';

if (!isset($pdo) && $databaseResult instanceof PDO) {
    $pdo = $databaseResult;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('config/db.php must provide a PDO connection in $pdo or return one.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function requireAccountID(): string
{
    $accountID = $_SESSION['account_id'] ?? null;

    if (!is_string($accountID) || $accountID === '') {
        header('Location: dev-login.php'); // Replace with login.php when Auth0 is ready.
        exit;
    }

    return $accountID;
}

function generateID(): string
{
    return bin2hex(random_bytes(16));
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireValidCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($expected) ||
        $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(400);
        exit('Invalid form submission. Please return to the previous page and try again.');
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function eventForHost(PDO $pdo, string $eventID, string $accountID): array
{
    $statement = $pdo->prepare(
        'SELECT e.*
         FROM Events e
         JOIN Participants host ON host.ID = e.HostID
         WHERE e.ID = ? AND host.AccountID = ?'
    );
    $statement->execute([$eventID, $accountID]);
    $event = $statement->fetch();

    if (!$event) {
        http_response_code(403);
        exit('You do not have permission to edit this event.');
    }

    return $event;
}

function requireDraft(array $event): void
{
    if (($event['State'] ?? null) !== 'draft') {
        http_response_code(409);
        exit('This event is no longer a draft.');
    }
}

function containerForHost(
    PDO $pdo,
    string $containerID,
    string $accountID
): array {
    $statement = $pdo->prepare(
        'SELECT bc.*, e.Type AS EventType, e.State AS EventState
         FROM BeerContainer bc
         JOIN Events e ON e.ID = bc.EventID
         JOIN Participants host ON host.ID = e.HostID
         WHERE bc.ID = ? AND host.AccountID = ?'
    );
    $statement->execute([$containerID, $accountID]);
    $container = $statement->fetch();

    if (!$container) {
        http_response_code(403);
        exit('You do not have permission to edit this container.');
    }

    return $container;
}

function html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pageStart(string $title): void
{
    $safeTitle = html($title);

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | Taster</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 850px; margin: 2rem auto; padding: 0 1rem; }
        label { display: block; margin: 0.8rem 0; }
        input[type="text"], select { box-sizing: border-box; width: 100%; max-width: 32rem; padding: 0.55rem; }
        button, .button { display: inline-block; padding: 0.55rem 0.9rem; margin: 0.3rem 0.3rem 0.3rem 0; text-decoration: none; cursor: pointer; }
        .error { color: #a40000; }
        .beer-list { columns: 2; padding-left: 0; list-style: none; }
        .beer-list label { break-inside: avoid; }
        .card { border: 1px solid #bbb; border-radius: 0.4rem; margin: 0.75rem 0; padding: 0.9rem; }
        .muted { color: #666; }
        .join-code { font-size: 2.5rem; letter-spacing: 0.25rem; font-weight: 700; }
    </style>
</head>
<body>
HTML;
}

function pageEnd(): void
{
    echo '</body></html>';
}

