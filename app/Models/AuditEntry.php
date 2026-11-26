<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    protected $table = 'shield_audit_entries';

    public $timestamps = false;
}
