<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSubscription extends Model
{
    protected $primaryKey = 'invoice_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'organization_id', 'starts_at', 'expires_at', 'daily_verifications'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'daily_verifications' => 'integer',
        ];
    }
}
