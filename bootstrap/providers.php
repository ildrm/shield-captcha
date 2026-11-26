<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CustomerPanelProvider;
use App\Providers\Filament\OperatorPanelProvider;

return [
    AppServiceProvider::class,
    CustomerPanelProvider::class,
    OperatorPanelProvider::class,
];
