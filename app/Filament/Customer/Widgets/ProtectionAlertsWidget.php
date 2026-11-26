<?php

namespace App\Filament\Customer\Widgets;

use App\Filament\Customer\Resources\ProtectionEventResource;
use App\Risk\ProtectionAlerts;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class ProtectionAlertsWidget extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.customer.widgets.protection-alerts';

    public static function canView(): bool
    {
        return ProtectionEventResource::canViewAny();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);

        return [
            'alerts' => app(ProtectionAlerts::class)->forOrganization(Filament::getTenant()),
            'window' => ProtectionAlerts::WINDOW_MINUTES,
            'sampleSize' => ProtectionAlerts::MAX_ASSESSMENTS,
            'velocityThreshold' => ProtectionAlerts::VELOCITY_THRESHOLD,
            'abuseThreshold' => ProtectionAlerts::ABUSE_THRESHOLD,
        ];
    }
}
