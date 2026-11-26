<?php

namespace App\Risk;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectionEvent extends Model
{
    protected $table = 'shield_protection_events';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['event_digest', 'request_digest'];

    protected function casts(): array
    {
        return ['reason_codes' => 'array'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
