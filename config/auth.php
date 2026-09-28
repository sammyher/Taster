<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Auth0\SDK\Auth0;
use Dotenv\Dotenv;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$auth0 = new Auth0([
    'domain'        => $_ENV['AUTH0_DOMAIN'],
    'clientId'      => $_ENV['AUTH0_CLIENT_ID'],
    'clientSecret'  => $_ENV['AUTH0_CLIENT_SECRET'],
    'cookieSecret'  => $_ENV['AUTH0_COOKIE_SECRET'],
    // This must match the Allowed Callback URL in your Auth0 dashboard
    'redirectUri'   => $_ENV['AUTH0_BASE_URL'] . '/callback.php', 
]);
