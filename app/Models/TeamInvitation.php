<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TeamInvitation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'shield_team_invitations';

    protected $fillable = [
        'organization_id', 'invited_by', 'email', 'role', 'token_digest', 'created_at', 'expires_at',
    ];

    protected $hidden = ['token_digest'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
