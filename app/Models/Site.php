<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    public const CHALLENGE_MODES = [
        'checkbox' => 'Manual',
        'invisible' => 'Automatic on submit',
        'adaptive' => 'Adaptive on submit',
    ];

    protected $fillable = [
        'name',
        'public_key',
        'environment',
        'origin',
        'domain_verification_token',
        'domain_verified_at',
        'actions',
        'challenge_mode',
        'is_active',
    ];

    protected $hidden = ['domain_verification_token'];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'domain_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(SiteCredential::class);
    }
}
