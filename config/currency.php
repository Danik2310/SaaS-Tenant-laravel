<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Base currency
    |--------------------------------------------------------------------------
    |
    | All plan prices and payment amounts are stored in this currency. Display
    | values are converted from this base to the admin display currency.
    |
    */

    'base_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Live exchange rate API
    |--------------------------------------------------------------------------
    |
    | Free, no-key endpoint used to fetch USD-based rates. Rates are cached
    | server-side for 'cache_ttl' seconds and fall back to 'fallback_rates'
    | whenever the API is unreachable.
    |
    */

    'api_url' => 'https://open.er-api.com/v6/latest/USD',

    'api_timeout' => 5,

    'cache_key' => 'exchange_rates_usd',

    'cache_ttl' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    |
    | Curated list of world currencies the admin panel can display. The API
    | response only carries rates for these codes.
    |
    */

    'currencies' => [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CA$'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => 'CN¥'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'MX$'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => 'AR$'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => 'CLP$'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => 'COL$'],
        'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/'],
        'UYU' => ['name' => 'Uruguayan Peso', 'symbol' => '$U'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KD'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SR'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QR'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£'],
        'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'MAD'],
        'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => '₨'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Offline fallback rates
    |--------------------------------------------------------------------------
    |
    | USD -> currency rates used when the live API is unreachable. These are
    | static approximations and are only meant to keep the admin panel usable
    | during an outage; they are replaced as soon as the API responds again.
    |
    */

    'fallback_rates' => [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'JPY' => 150.5,
        'CHF' => 0.88,
        'CAD' => 1.37,
        'AUD' => 1.53,
        'NZD' => 1.68,
        'CNY' => 7.15,
        'HKD' => 7.81,
        'SGD' => 1.35,
        'INR' => 83.5,
        'KRW' => 1380.0,
        'THB' => 35.5,
        'MYR' => 4.4,
        'IDR' => 16300.0,
        'PHP' => 58.5,
        'VND' => 25500.0,
        'MXN' => 18.2,
        'BRL' => 5.4,
        'ARS' => 950.0,
        'CLP' => 960.0,
        'COP' => 4100.0,
        'PEN' => 3.75,
        'UYU' => 39.0,
        'KWD' => 0.307,
        'SAR' => 3.75,
        'AED' => 3.67,
        'QAR' => 3.64,
        'TRY' => 35.0,
        'RUB' => 92.0,
        'ZAR' => 18.1,
        'NGN' => 1550.0,
        'KES' => 129.0,
        'EGP' => 48.0,
        'MAD' => 10.0,
        'ILS' => 3.7,
        'SEK' => 10.5,
        'NOK' => 10.7,
        'DKK' => 6.9,
        'PLN' => 3.95,
        'CZK' => 23.3,
        'HUF' => 380.0,
        'RON' => 4.6,
        'BGN' => 1.8,
        'UAH' => 41.0,
        'PKR' => 278.0,
        'BDT' => 118.0,
    ],
];
