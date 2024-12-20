<?php

// use Dom\XPath; - not available before PHP 8.4
use DOMXPath;
use Exceptions\ParserException;

/**
 * __define-ocg__
 */

class XmlValidator
{
    private const int CODE_BAD_REQUEST = 400;

    private const int XML_LOAD_OPTIONS = LIBXML_DTDLOAD; // PHP 8.4 adds LIBXML_NO_XXE - enable it for security


    // parser and accessories
    private DOMDocument $document;
    private DOMXPath $xpath;


    // values extracted or assumed by default through the validation phase - @see validate


    private array $destinations = [];

    private string $currency;

    private float $markup;

    /**
     * @param string $xml
     * @param DomDocument|null $document
     * @param DOMXPath|null $xpath
     *
     * Notes:
     * on production, I would rather have these dependencies injected by
     * an autowiring mechanism (Laravel/Symfony),
     * $document and $xpath are left open for injection for unit tests/mocking
     */
    public function __construct(string $xml,
    ?DomDocument $document = null,
    ?DOMXPath $xpath = null)
    {

        $this->document = $document ?? new DOMDocument();
        $this->xpath = $xpath ?? new DOMXPath($this->document);
        if (false === $this->document->loadXML($xml, self::XML_LOAD_OPTIONS))
        {
            throw new ParserException("Not well-formed XML, or XML DTD validation failed ");
        }
    }

    /**
     * Validate XML feed
     * @throws ParserException on any inconsistency within the XML file
     * @return void
     */
    public function validate():void
    {
        $this->getValidatedLangCode();
        $this->getValidatedOptionsQuota();
        $this->getValidatedCredentials();
        $searchMultipleDestinations = $this->getValidatedSearchType();
        $allowedHotelCount = $this->getAllowedHotelCount();
        $this->destinations = $this->getValidatedDestinations($searchMultipleDestinations, $allowedHotelCount);
        try {
            $startDate = $this->getStartDateValidated();
            $endDate = $this->getEndDateValidated();
        } catch (DateMalformedStringException $x) {
            throw new ParserException("Error parsing date: " . $x->getMessage(), self::CODE_BAD_REQUEST, $x);
        }
        $this->validateRequestedDatesDiff($startDate, $endDate);
        $this->currency = $this->getValidatedCurrency();
        $this->getValidatedNationality();
        $this->getValidatedMarket();

        $this->validateRoomsAndPassengers();
        $this->markup = $this->getValidatedMarkup();
    }

    public function getDocument(): DOMDocument
    {
        return $this->document;
    }


    /**
     * For this and other validation methods, the return value is never used - left it for proper use cases.
     * @return string
     */
    private function getValidatedLangCode(): string
    {
        return $this->getPropertyValidated('/source/languageCode', Rules::LANG_CODES_ALLOWED, 'language');
    }


    private function getValidatedOptionsQuota(): int
    {
        return $this->getNumericValueFromXml("/optionsQuota", 'options quota', null, Rules::OPTIONS_QUOTA_MAX, Rules::OPTIONS_QUOTA_DEFAULT);
    }



    private function getValidatedCredentials(): Credentials
    {
        $nodes = $this->xpath->query("/Configuration/Parameters/Parameter");
        if ($nodes->length == 0) {
            throw new ParserException("Missing required parameters");
        }
        $credentials = $nodes->item(0)->attributes;
        $username = $credentials->getNamedItem("username");
        if (empty($username)) {
            throw new ParserException("Username is missing or empty");
        }

        $password = $credentials->getNamedItem("password");
        if (empty($password)) {
            throw new ParserException("Username is missing or empty");
        }

        $companyId = $credentials->getNamedItem("CompanyID");

        if (empty($companyId) || !is_numeric($companyId)) {
            throw new ParserException("Company ID is missing, empty or non-numeric");
        }
        // the constructor here may have additional domain specific checks. such as account validation
        return new Credentials($username, $password, (int)$companyId);
    }

    private function getValidatedSearchType(): bool
    {
        return ($this->getPropertyValidated('/SearchType', ['Single', 'Multiple'], 'search type') == 'Multiple');
    }

    private function getValidatedDestinations(bool $searchMultiple, int $allowedHotelCount): array
    {
        $nodes = $this->xpath->query("/AvailDestinations/Destination");
        if ($nodes->length == 0) {
            throw new ParserException("Missing required destinations");
        }

        if (!$searchMultiple && $nodes->length > 1) {
            throw new ParserException("Single search cannot request multiple destinations");
        }


        if ($nodes->length > $allowedHotelCount) {
            throw new ParserException("Number of requested destinations exceed the allowed hotels count: $allowedHotelCount");
        }

        $destinations = [];
        foreach ($nodes as $node) {
            $destinations[] = ($node->getAttributes());
        }

        return $destinations;
    }

    private function getAllowedHotelCount(): int
    {
        return $this->getNumericValueFromXml('/AllowedHotelCount', 'allowed hotel count');
    }

    /**
     * @throws DateMalformedStringException
     */
    private function getStartDateValidated(): DateTimeInterface
    {
        $nodes = $this->xpath->query("/StartDate");
        if ($nodes->length == 0) {
            throw new ParserException("Missing start date");
        }

        return new DateTimeImmutable($nodes->item(0)->nodeValue);
    }


    /**
     * @throws DateMalformedStringException
     */
    private function getEndDateValidated(): DateTimeInterface
    {
        $nodes = $this->xpath->query("/EndDate");
        if ($nodes->length == 0) {
            throw new ParserException("Missing end date");
        }

        return new DateTimeImmutable($nodes->item(0)->nodeValue);
    }

    private function validateStartDate(DateTimeInterface $startDate): void
    {
        // check if start date is well in advance
        $today = new DateTimeImmutable();
        $startDiff = $startDate->diff($today);
        $days = intval($startDiff->format('%a'));
        if ($days < Rules::EARLIEST_START_DAYS) {
            throw new ParserException("Start date should be at least " . Rules::EARLIEST_START_DAYS . " from now, got ". $startDate->format("Y-m-d"));
        }
    }

    private function validateRequestedDatesDiff(DateTimeInterface $startDate, DateTimeInterface $endDate): void
    {
        $endDiff = $endDate->diff($startDate);
        $days = intval($endDiff->format('%a'));
        if ($days < Rules::MIN_STAY_DAYS) {
            throw new ParserException("Stay (difference between start and end dates) must be at least " . Rules::MIN_STAY_DAYS . " days, got $days");
        }
    }

    private function getValidatedCurrency(): string
    {
        return $this->getPropertyValidated('/Currency', Rules::CURRENCY_ALLOWED, 'currency');
    }

    private function getValidatedNationality(): string
    {
       return $this->getPropertyValidated('/Nationality', Rules::NATIONALITY_ALLOWED, 'nationality');
    }


    private function getPropertyValidated(string $xpath, array $allowedValues, string $property): string
    {
        $nodes = $this->xpath->query($xpath);
        if ($nodes->length == 0) {
            return $allowedValues[0];
        }

        $result = $nodes->item(0)->nodeValue ?: $allowedValues[0];
        if (!in_array($result, $allowedValues)) {
            throw new ParserException("Invalid $property value: '$result'");
        }

        return $result;
    }

    private function getValidatedMarket(): string
    {
        return $this->getPropertyValidated('/Markets/Market', Rules::MARKETS_ALLOWED, 'market');
    }


    private function getNumericValueFromXml(string $xpath, string $property, ?int $min = null, ?int $max = null, ?int $default=null): int
    {
        $nodes = $this->xpath->query($xpath);
        if ($nodes->length == 0) {
            if ($default !== null) {
                return $default;
            }
            throw new ParserException("Missing '$property'");
        }
        $strValue = $nodes->item(0)->nodeValue;

        if (empty($strValue) || !is_numeric($strValue)) {
            throw new ParserException("'$property' must be a number");
        }

        $value = intval($strValue);

        if ((null !== $min) && ($value < $min)) {
            throw new ParserException("Minimum '$property' value must be greater than $min");
        }

        if ((null !== $max) && ($value > $max)) {
            throw new ParserException("Maximum '$property' value must be less than $max");
        }

        return $value;
    }


    private function validateRoomsAndPassengers(): void
    {
        $maxGuestsPerRoom = $this->getAllowedGuestsPerRoom();
        $maxChildrenPerRoom = $this->getMaxChildrenPerRoom();
        $roomNodes = $this->xpath->query('Paxes');
        if ($roomNodes->length == 0) {
            throw new ParserException("Missing rooms");
        }

        foreach($roomNodes as $roomNode) {
            $this->validateRoom($roomNode, $maxGuestsPerRoom, $maxChildrenPerRoom);
        }
    }


    private function validateRoom(DOMElement $roomNode, int $maxGuestsPerRoom, int $maxChildrenPerRoom): void
    {
        $adults = 0;
        $children = 0;
        $guestNodes = $this->xpath->query('//Pax', $roomNode);
        if ($guestNodes->length == 0) {
            throw new ParserException("Missing guests per room");
        }

        if ($guestNodes->length > $maxGuestsPerRoom) {
            throw new ParserException("Number of passengers per room exceeded");
        }

        foreach($guestNodes as $guestNode) {
            if($this->isPassengerAChild($guestNode, $maxGuestsPerRoom, $maxChildrenPerRoom)) {
                $children++;
            } else {
                $adults++;
            }
        }

        if ($children > $maxChildrenPerRoom) {
            throw new ParserException("Number of children per room exceeded");
        }

        if (($children > 0) && ($adults == 0)) {
            throw new ParserException("Children not accompanied by adults not allowed");
        }

    }

    private function isPassengerAChild(DOMElement $guestNode, int $maxGuestsPerRoom, int $maxChildrenPerRoom): bool
    {
        $age = $guestNode->getAttribute('age');
        return ($age <= Rules::MAX_AGE_CHILD);
    }


    private function getAllowedGuestsPerRoom(): int
    {
        return $this->getNumericValueFromXml('/AllowedRoomGuestCount', 'allowed guests per room', 1);
    }

    private function getMaxChildrenPerRoom(): int
    {
        return $this->getNumericValueFromXml('/AllowedChildCountPerRoom', 'allowed children per room');;
    }

    private function getValidatedMarkup(): float
    {
        $nodes = $this->xpath->query('/Markup');
        if ($nodes->length == 0) {
            throw new ParserException("Missing markup");
        }
        $markup = $nodes->item(0)->nodeValue;
        if (empty($markup) || !is_numeric($markup)) {
            throw new ParserException("Markup must be a number");
        }

        return (float) $markup;
    }

    public function getMarkup(): float
    {
        return $this->markup;
    }

    public function getDestinations(): array
    {
        return $this->destinations;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}

