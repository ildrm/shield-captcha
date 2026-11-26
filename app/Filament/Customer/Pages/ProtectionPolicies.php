<?php

namespace App\Filament\Customer\Pages;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;
use App\Risk\ProtectionPolicies as PolicyVersions;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

class ProtectionPolicies extends Page
{
    protected static ?string $title = 'Protection policies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected string $view = 'filament.customer.pages.protection-policies';

    public array $data = [];

    #[Locked]
    public ?int $publishedVersion = null;

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        return $tenant instanceof Organization && $user instanceof User
            && app(SitePolicy::class)->createIn($user, $tenant);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(PolicyVersions::DEFAULTS);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('site_id')
                ->label('Site')
                ->options(function () {
                    abort_unless(static::canAccess(), 403);

                    return Filament::getTenant()->sites()->orderBy('name')->pluck('name', 'id');
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('action', null);
                    $set('revision_to_load', null);
                    $this->publishedVersion = null;
                }),
            Select::make('action')
                ->options(fn (Get $get): array => $this->actionOptions($get('site_id')))
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, PolicyVersions $policies): void {
                    $set('revision_to_load', null);
                    $site = $this->siteForAction($get);
                    if ($site === null) {
                        $this->publishedVersion = null;

                        return;
                    }

                    $policy = $policies->current($site, $get('action'));
                    $this->publishedVersion = $policy['version'];
                    $this->loadFields($set, $policy);
                }),
            Select::make('revision_to_load')
                ->label('Load a published revision')
                ->placeholder('Select a revision')
                ->helperText('Loading a revision updates these fields. Publish to apply the values as a new revision.')
                ->visible(fn (Get $get): bool => $this->siteForAction($get) !== null && $this->publishedVersion > 0)
                ->options(function (Get $get, PolicyVersions $policies): array {
                    $site = $this->siteForAction($get);
                    if ($site === null) {
                        return [];
                    }

                    $options = [];
                    foreach ($policies->recentVersions($site, $get('action')) as $version) {
                        $options[$version] = 'Revision '.$version;
                    }

                    return $options + [0 => 'Built-in policy'];
                })
                ->dehydrated(false)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, PolicyVersions $policies): void {
                    $version = filter_var($get('revision_to_load'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    $site = $this->siteForAction($get);
                    if ($site === null || $version === false) {
                        return;
                    }

                    $policy = $policies->version($site, $get('action'), $version);
                    if ($policy !== null) {
                        $this->loadFields($set, $policy);
                    }
                })
                ->columnSpanFull(),
            Select::make('mode')
                ->options(['observe' => 'Observe', 'enforce' => 'Enforce'])
                ->helperText('Observe records the recommended decision and returns allow. In enforce mode, your application must apply the returned decision.')
                ->required()
                ->selectablePlaceholder(false),
            Select::make('velocity_decision')
                ->label('Decision when a limit is exceeded')
                ->options(PolicyVersions::DECISIONS)
                ->required()
                ->selectablePlaceholder(false),
            TextInput::make('window_seconds')->label('Window in seconds')->integer()->minValue(10)->maxValue(3600)->required(),
            TextInput::make('subject_limit')->label('Requests per subject per window')->integer()->minValue(1)->maxValue(1000)->required(),
            TextInput::make('site_limit')->label('Requests per action and protection endpoint per window')->integer()->minValue(1)->maxValue(3000)->required(),
            Toggle::make('require_registered_automation')
                ->label('Require a registered automation key')
                ->helperText('Applies to bot assessments. Velocity limits also apply to registered automation.'),
        ])->columns(2)->statePath('data');
    }

    public function save(PolicyVersions $policies): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $site = $this->site($data['site_id']);
        abort_unless($site !== null, 404);

        $this->publishedVersion = $policies->publish(Filament::auth()->user(), $site, $data['action'], $data);
        $this->data['revision_to_load'] = null;

        Notification::make()->title('Protection policy published')->success()->send();
    }

    private function loadFields(Set $set, array $policy): void
    {
        unset($policy['version']);
        foreach ($policy as $field => $value) {
            $set($field, $value);
        }
    }

    private function siteForAction(Get $get): ?Site
    {
        $site = $this->site($get('site_id'));

        return $site !== null && in_array($get('action'), $site->actions, true) ? $site : null;
    }

    private function actionOptions(mixed $siteId): array
    {
        $actions = $this->site($siteId)?->actions ?? [];

        return array_combine($actions, $actions);
    }

    private function site(mixed $siteId): ?Site
    {
        abort_unless(static::canAccess(), 403);

        $id = filter_var($siteId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : Filament::getTenant()->sites()->find($id);
    }
}
