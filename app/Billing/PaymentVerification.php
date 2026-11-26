<?php

namespace App\Billing;

final readonly class PaymentVerification
{
    public function __construct(public ?int $code, public ?string $reference) {}
}
