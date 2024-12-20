<?php

namespace Exceptions;

/**
 * This is a generic exception which occurs on XML content violating the business logic.
 * It is advisable to extend it in particular use cases, also for unit testing and debugging.
 */
class ParserException extends \RuntimeException implements HttpAwareExceptionInterface
{
    private int $httpCode = self::HTTP_GENERIC_ERROR;
    public function __construct(string $message, int $code = 0, string $httpCode = self::HTTP_GENERIC_ERROR, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
    }


    public function getHttpStatusCode(): int
    {
        return $this->httpCode;
    }

    public function getXml(): string
    {
        return <<< EOT
        <applicationErrors>
            <code>{$this->getCode()}</code>
            <type>{get_class($this)}</type>
            <description>{$this->getMessage()}</description>
            <httpStatusCode>{$this->getHttpStatusCode()}</httpStatusCode>
        </applicationErrors>
EOT;
    }
}