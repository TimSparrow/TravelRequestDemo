<?php


namespace Services;

use GuzzleHttp\ClientInterface;

class ExchangeRateFetcher implements ExchangeRateInterface
{
    public const string API_URL = 'https://api.apilayer.com/exchangerates_data/latest';

    public const string BASE_CURRENCY = 'EUR'; // should be a parameter



    private array $rates;


    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $baseCurrency,
        string $apiKey)
    {
        $this->fetchRates($apiKey);
    }


    private function fetchRates(string $apiKey): void
    {
        $headers = [
            'apikey' => $apiKey,
        ];
        $response = $this->client->get(self::getServiceUrl($this->getBaseCurrency()),  ['headers' => $headers]);
        $rates = json_decode($response->getBody(), true);
        $this->rates = $rates['rates']; // discard the metadata
    }

    public function getExchangeRate(string $currencyCode): float
    {
        if (!array_key_exists($currencyCode, $this->rates)) {
            return 0.0; // rate not found
        }

        return $this->rates[$currencyCode];
    }

    public function




    getAmountConverted(float $amount, string $currencyCode): float
    {
        if ($currencyCode === $this->getBaseCurrency()) {
            return $amount;
        }

        $rate = $this->getExchangeRate($currencyCode);
        return ($rate > 0) ? $amount / $rate : $amount;
    }


    public static function getServiceUrl(string $baseCurrency): string
    {
        return self::API_URL . "?base=" . $baseCurrency;
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }
}