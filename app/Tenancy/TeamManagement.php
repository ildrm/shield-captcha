<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final class TeamManagement
{
    public const ROLES = [
        'owner' => true,
        'admin' => true,
        'developer' => true,
        'security_analyst' => true,
        'billing_manager' => true,
        'viewer' => true,
    ];

    public const INVITATION_DAYS = 7;

    public static function canManage(User $user, Organization $organization): bool
    {
        return in_array(self::memberRole($user, $organization), ['owner', 'admin'], true);
    }

    public static function isOwner(User $user, Organization $organization): bool
    {
        return self::memberRole($user, $organization) === 'owner';
    }

    public static function assignableRoles(User $user, Organization $organization): array
    {
        return match (self::memberRole($user, $organization)) {
            'owner' => array_diff_key(self::ROLES, ['owner' => true]),
            'admin' => array_diff_key(self::ROLES, ['owner' => true, 'admin' => true]),
            default => [],
        };
    }

    public static function canEditMember(User $actor, User $target, Organization $organization, string $role): bool
    {
        $actorRole = self::memberRole($actor, $organization);

        return $actor->id !== $target->id && $role !== 'owner'
            && ($actorRole === 'owner' || ($actorRole === 'admin' && $role !== 'admin'));
    }

    public static function canAccept(User $user): bool
    {
        return ! $user->is_operator && $user->hasVerifiedEmail();
    }

    public function issue(User $actor, Organization $organization, string $email, string $role): string
    {
        return DB::transaction(function () use ($actor, $organization, $email, $role): string {
            $organization = $this->lock($organization);
            $actor = $actor->fresh() ?? abort(403);
            $roles = self::assignableRoles($actor, $organization);
            abort_unless(isset($roles[$role]), 403);
            $email = self::normalizeEmail($email);

            abort_if($organization->members()->whereRaw('LOWER(users.email) = ?', [$email])->exists(), 409, __('team.already_member'));

            $pending = $this->pending($organization)->where('email', $email);
            abort_if((clone $pending)->whereNotIn('role', array_keys($roles))->exists(), 403);
            $pending->update(['revoked_at' => now()]);
            $code = bin2hex(random_bytes(32));
            $invitation = TeamInvitation::create([
                'organization_id' => $organization->id,
                'invited_by' => $actor->id,
                'email' => $email,
                'role' => $role,
                'token_digest' => hash('sha256', $code),
                'created_at' => now(),
                'expires_at' => now()->addDays(self::INVITATION_DAYS),
            ]);
            Audit::record($actor, 'team.invitation_issued', $organization->id, reason: "Invitation {$invitation->id} for role {$role}.");

            return $code;
        }, 3);
    }

    public function accept(User $actor, #[SensitiveParameter] string $code): ?Organization
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $code)) {
            return null;
        }

        $digest = hash('sha256', $code);
        $organizationId = TeamInvitation::query()->where('token_digest', $digest)->value('organization_id');
        if ($organizationId === null) {
            return null;
        }

        return DB::transaction(function () use ($actor, $organizationId, $digest): ?Organization {
            $organization = Organization::query()->lockForUpdate()->find($organizationId);
            $actor = User::query()->lockForUpdate()->find($actor->id);

            if (! $organization?->is_active || $actor === null || ! self::canAccept($actor)) {
                return null;
            }

            $invitation = $this->pending($organization)->where('token_digest', $digest)->lockForUpdate()->first();
            if ($invitation === null || $invitation->email !== self::normalizeEmail($actor->email)) {
                return null;
            }

            $creator = User::query()->find($invitation->invited_by);
            if ($creator === null || ! isset(self::assignableRoles($creator, $organization)[$invitation->role])) {
                return null;
            }

            // An invitation never overwrites an existing membership.
            if ($organization->members()->whereKey($actor->id)->exists()) {
                return null;
            }

            $organization->members()->attach($actor, ['role' => $invitation->role]);
            $invitation->forceFill(['accepted_by' => $actor->id, 'accepted_at' => now()])->save();
            Audit::record($actor, 'team.invitation_accepted', $organization->id, reason: "Invitation {$invitation->id} accepted.");

            return $organization;
        }, 3);
    }

    public function revoke(User $actor, Organization $organization, string $invitationId): void
    {
        DB::transaction(function () use ($actor, $organization, $invitationId): void {
            $organization = $this->lock($organization);
            $actor = $actor->fresh() ?? abort(403);
            abort_unless(self::canManage($actor, $organization), 403);
            $invitation = $this->pending($organization)->findOrFail($invitationId);
            abort_unless(isset(self::assignableRoles($actor, $organization)[$invitation->role]), 403);
            $invitation->forceFill(['revoked_at' => now()])->save();
            Audit::record($actor, 'team.invitation_revoked', $organization->id, reason: "Invitation {$invitation->id} revoked.");
        }, 3);
    }

    public function updateRole(User $actor, Organization $organization, int $userId, string $role): void
    {
        DB::transaction(function () use ($actor, $organization, $userId, $role): void {
            $organization = $this->lock($organization);
            $actor = $actor->fresh() ?? abort(403);
            abort_unless(isset(self::assignableRoles($actor, $organization)[$role]), 403);
            $member = $organization->members()->findOrFail($userId);
            $currentRole = $member->pivot->role;
            abort_unless(self::canEditMember($actor, $member, $organization, $currentRole), 403);

            if ($currentRole === $role) {
                return;
            }

            $organization->members()->updateExistingPivot($userId, ['role' => $role]);
            $this->pending($organization)->where('invited_by', $userId)->update(['revoked_at' => now()]);
            Audit::record($actor, 'team.role_changed', $organization->id, reason: "Member {$userId} changed from {$currentRole} to {$role}.");
        }, 3);
    }

    public function remove(User $actor, Organization $organization, int $userId): void
    {
        DB::transaction(function () use ($actor, $organization, $userId): void {
            $organization = $this->lock($organization);
            $actor = $actor->fresh() ?? abort(403);
            abort_unless(self::canManage($actor, $organization), 403);
            $member = $organization->members()->findOrFail($userId);
            abort_unless(self::canEditMember($actor, $member, $organization, $member->pivot->role), 403);
            $organization->members()->detach($userId);
            $this->pending($organization)->where('invited_by', $userId)->update(['revoked_at' => now()]);
            Audit::record($actor, 'team.member_removed', $organization->id, reason: "Member {$userId} removed.");
        }, 3);
    }

    public function transfer(User $actor, Organization $organization, int $userId): void
    {
        DB::transaction(function () use ($actor, $organization, $userId): void {
            $organization = $this->lock($organization);
            $actor = $actor->fresh() ?? abort(403);
            abort_unless(self::isOwner($actor, $organization), 403);
            $member = $organization->members()->findOrFail($userId);
            $target = User::query()->lockForUpdate()->findOrFail($member->id);
            abort_unless($actor->id !== $userId && self::canAccept($target), 422, __('team.verified_target'));

            $organization->members()->updateExistingPivot($userId, ['role' => 'owner']);
            $organization->members()->updateExistingPivot($actor->id, ['role' => 'admin']);
            $this->pending($organization)->where('invited_by', $actor->id)->update(['revoked_at' => now()]);
            Audit::record($actor, 'team.ownership_transferred', $organization->id, reason: "Ownership transferred to member {$userId}.");
        }, 3);
    }

    public function pending(Organization $organization): Builder
    {
        return TeamInvitation::query()->where('organization_id', $organization->id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    private function lock(Organization $organization): Organization
    {
        return Organization::query()->lockForUpdate()->findOrFail($organization->id);
    }

    private static function memberRole(User $actor, Organization $organization): ?string
    {
        return self::canAccept($actor) ? $actor->roleInOrganization($organization->id) : null;
    }

    private static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
