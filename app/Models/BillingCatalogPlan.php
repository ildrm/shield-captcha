<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCatalogPlan extends Model
{
    protected $table = 'billing_plans';

    protected $fillable = [
        'name', 'term_days', 'daily_verifications', 'zarinpal_amount_irr',
        'stripe_amount_minor', 'stripe_currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'term_days' => 'integer',
            'daily_verifications' => 'integer',
            'zarinpal_amount_irr' => 'integer',
            'stripe_amount_minor' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }
}
