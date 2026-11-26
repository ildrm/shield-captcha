<?php

namespace App\Filament\Customer;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\Audit;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create organization';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Organization name')->required()->maxLength(120),
        ]);
    }

    protected function handleRegistration(array $data): Organization
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User && ! $user->is_operator && $user->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($data, $user): Organization {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => Str::uuid()->toString(),
            ]);
            $organization->members()->attach($user, ['role' => 'owner']);
            Audit::record($user, 'organization.created', $organization->id);

            return $organization;
        });
    }
}
