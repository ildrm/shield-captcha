<?php

namespace App\Models;

use App\Billing\BillingAmount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillingInvoice extends Model
{
    public const ZARINPAL = 'zarinpal';

    public const STRIPE = 'stripe';

    public const CREATING = 'creating';

    public const PENDING = 'pending';

    public const CREATION_FAILED = 'creation_failed';

    public const PAID = 'paid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = ['provider' => self::ZARINPAL, 'currency' => 'IRR'];

    protected $fillable = [
        'id', 'organization_id', 'created_by', 'checkout_key', 'environment', 'merchant_fingerprint',
        'amount_irr', 'amount_minor', 'currency', 'provider', 'term_days', 'daily_verifications', 'status',
        'billing_plan_id', 'plan_name',
    ];

    protected $hidden = ['authority', 'merchant_fingerprint', 'checkout_key', 'stripe_session_id', 'stripe_checkout_url'];

    protected function casts(): array
    {
        return [
            'amount_irr' => 'integer',
            'amount_minor' => 'integer',
            'stripe_checkout_url' => 'encrypted',
            'term_days' => 'integer',
            'daily_verifications' => 'integer',
            'billing_plan_id' => 'integer',
            'verified_code' => 'integer',
            'paid_at' => 'immutable_datetime',
            'next_verify_at' => 'immutable_datetime',
        ];
    }

    public function minorAmount(): int
    {
        return $this->amount_minor ?? $this->amount_irr ?? 0;
    }

    public function displayAmount(): string
    {
        return BillingAmount::format($this->minorAmount(), $this->currency);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(BillingSubscription::class, 'invoice_id');
    }
}
