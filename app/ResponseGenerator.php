<?php

use Exceptions\ParserException;
use Faker\Generator as Faker;
use Services\ExchangeRateInterface;

class ResponseGenerator
{

    public function __construct(
        private readonly ExchangeRateInterface $exchange,   // real life currency exchange API
        private readonly Faker $faker,                    // to generate random data
        private readonly XmlValidator $xmlConverter,
    ) {}


    public function execute(string $varOcg): void
    {
        // sample data is provided as an example,
        // proper handling would be through inheriting Symfony\Command, etc.
        try {


            $this->importAndValidateXml($varOcg);
            $result = $this->generate($this->xmlConverter->getDestinations(), $this->xmlConverter->getMarkup(), $this->xmlConverter->getCurrency());

            echo json_encode($result, JSON_THROW_ON_ERROR);
        } catch (ParserException $exception) {
            echo $exception->getXml();
        } catch (\Throwable $exception) {

        }
    }

    private function importAndValidateXml(string $xml): void
    {
        $this->xmlConverter->setXml($xml);
        $this->xmlConverter->validate();
    }

    private function generate(array $destinations, float $markup, string $requestCurrency): array
    {
        $result = [];
        $market = $this->getRandomMarket();
        foreach ($destinations as $destination) {
            $destinationData = [
                'id' => $this->generateDestinationId(),
                'hotelCodeSupplier' => $destination['code'],
                'market' => $market,
                'price' => $this->generatePrice($market, $markup, $requestCurrency),
            ];
            $result[] = $destinationData;
        }
        return $result;
    }

    private function generateDestinationId(): string
    {
        return $this->faker->regexify('[A-Z]{2}#[0-9]{2}'); // AB#56 would be a valid id
    }

    private function generatePrice(string $market, float $markup, string $targetCurrency): array
    {
        $net = $this->faker->randomFloat(); // generate a random float
        $currency = $this->getRandomCurrency();
        $sellingPrice = (1 + $markup / 100) * $net;

        if ($targetCurrency !== $currency) {
            $sellingPrice = $this->exchange->getAmountConverted($sellingPrice, $targetCurrency);

        }

        return [
            'minimumSellingPrice' => Rules::DISCOUNT * $sellingPrice, // (20% discount allowed)
            'net' => $net,
            'currency' => $currency,
            'selling_price' => $sellingPrice,
            'selling_currency' => $targetCurrency,
            'markup' => $markup,
            'exchange_rate' => $this->exchange->getExchangeRate($targetCurrency),
        ];
    }

    private function getRandomMarket(): string
    {
        return $this->faker->randomElement(Rules::MARKETS_ALLOWED);
    }

    private function getRandomCurrency(): string
    {
        return $this->faker->randomElement(Rules::CURRENCY_ALLOWED);
    }
}