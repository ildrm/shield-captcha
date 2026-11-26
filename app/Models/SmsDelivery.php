<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsDelivery extends Model
{
    use HasUuids;

    public const DISPATCHING = 'dispatching';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const UNKNOWN = 'unknown';

    public const DISPATCH_TIMEOUT_SECONDS = 20;

    protected $table = 'shield_sms_deliveries';

    protected $fillable = [
        'site_id',
        'action',
        'assessment_id',
        'event_digest',
        'request_digest',
        'state',
        'provider',
        'provider_reference',
    ];

    protected $hidden = ['event_digest', 'request_digest'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
