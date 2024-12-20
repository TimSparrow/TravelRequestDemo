<?php

namespace Exceptions;

interface HttpAwareExceptionInterface
{
    public const int HTTP_GENERIC_ERROR = 500;
    public function getHttpStatusCode(): int;
}