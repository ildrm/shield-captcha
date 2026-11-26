<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCredential extends Model
{
    protected $fillable = ['site_id', 'scope', 'secret_digest', 'prefix', 'revoked_at'];

    protected $hidden = ['secret_digest'];

    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
