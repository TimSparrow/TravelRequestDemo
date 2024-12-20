<?php

/**
 * Should be a config file with a parser
 */
class Rules
{

    public const array LANG_CODES_ALLOWED = ['en', 'fr', 'de', 'es'];

    public const int OPTIONS_QUOTA_DEFAULT = 20;
    public const int OPTIONS_QUOTA_MAX = 50;

    public const int EARLIEST_START_DAYS = 2;

    public const int MIN_STAY_DAYS = 3;


    public const array CURRENCY_ALLOWED = ['USD', 'EUR', 'GBP'];

    public const array NATIONALITY_ALLOWED = ['US', 'GB', 'CA'];

    public const array MARKETS_ALLOWED = ['ES', 'US', 'GB', 'CA'];

    public const int MAX_AGE_CHILD = 5;

    public const float DISCOUNT = 0.8; // affects minimumSellingPrice
}