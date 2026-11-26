<?php

namespace App\Filament\Operator\Resources\AuditEntryResource\Pages;

use App\Filament\Operator\Resources\AuditEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEntries extends ListRecords
{
    protected static string $resource = AuditEntryResource::class;
}
