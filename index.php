<?php

use GuzzleHttp\Client;
use Services\ExchangeRateFetcher;

require_once  __DIR__ . "/vendor/autoload.php";


// these initializations are supposed to be done in the framework's container through autowiring
define('BASE_CURRENCY', 'USD');
$apikey = getenv('APIKEY');

$client = new Client();
$exchangeRateFetcher = new ExchangeRateFetcher($client, BASE_CURRENCY, $apikey);
$faker = Faker\Factory::create();
$xmlValidator = new XmlValidator();
$generator = new ResponseGenerator($exchangeRateFetcher, $faker, $xmlValidator);

$_POST = file_get_contents('./sample.xml');

$generator->execute($_POST);