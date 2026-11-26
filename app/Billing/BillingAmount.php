<?php

namespace App\Billing;

final class BillingAmount
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'IRR', 'JPY', 'KMF', 'KRW',
        'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function format(int $minor, string $currency): string
    {
        $scale = match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, ['BHD', 'JOD', 'KWD', 'OMR', 'TND'], true) => 3,
            default => 2,
        };

        return number_format($minor / (10 ** $scale), $scale).' '.$currency;
    }
}
