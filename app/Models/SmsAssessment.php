<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsAssessment extends Model
{
    protected $table = 'shield_sms_assessments';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['event_digest', 'request_digest'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
