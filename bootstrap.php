<?php

use Automattic\WooCommerce\Client;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Google
$client = getClient();
$service = new Google_Service_Sheets($client);

// WooCommerce
$woocommerce = new Client(
    $_ENV['WOO_URL'],
    $_ENV['CONSUMER_KEY'],
    $_ENV['SECRET_KEY']
);