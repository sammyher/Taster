<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Auth0\SDK\Auth0;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->load();

$auth0 = new Auth0([
    'domain'       => $_ENV['AUTH0_DOMAIN'],
    'clientId'     => $_ENV['AUTH0_CLIENT_ID'],
    'clientSecret' => $_ENV['AUTH0_CLIENT_SECRET'],
    'cookieSecret' => $_ENV['AUTH0_COOKIE_SECRET'],
    'redirectUri'  => $_ENV['AUTH0_BASE_URL'] . '/callback.php',
]);