<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['account_id'])) {
    $_SESSION['target_role'] = 'host';
    header('Location: login.php?role=host');
    exit;
}

header('Location: create-event.php');
exit;