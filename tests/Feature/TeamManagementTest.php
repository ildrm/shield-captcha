<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Tenancy\Audit;
use App\Tenancy\TeamManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_verified_invitee_can_use_an_invitation_once(): void
    {
        [$organization, $owner] = $this->organization();
        $invitee = User::factory()->create(['email' => 'Invitee@example.net']);
        $otherUser = User::factory()->create();
        $join = route('team-invitations.show');

        $this->get($join)->assertRedirect(route('login'));
        $this->actingAs($owner)->get($this->teamRoute('index', $organization))
            ->assertOk()->assertSee('Team & audit')->assertSee($owner->email);

        $code = $this->issue($owner, $organization, 'INVITEE@example.net', 'developer');
        $stored = TeamInvitation::query()->sole();
        $this->assertSame('invitee@example.net', $stored->email);
        $this->assertSame(hash('sha256', $code), $stored->token_digest);
        $this->assertSame('developer', $stored->role);
        $this->assertStringNotContainsString($code, $stored->toJson());
        $this->get($this->teamRoute('index', $organization))
            ->assertOk()->assertSee('invitee@example.net')->assertDontSee($code);

        $this->actingAs($otherUser)->post($join, ['invitation_code' => $code])
            ->assertUnprocessable()->assertDontSee($code)->assertSessionMissing('_old_input.invitation_code');
        $invitee->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($invitee)->get($join)
            ->assertRedirect(route('filament.customer.auth.email-verification.prompt'))->assertSessionHas('url.intended', $join);
        $this->post($join, ['invitation_code' => $code])->assertForbidden();
        $this->assertNull($stored->fresh()->accepted_at);

        $invitee->markEmailAsVerified();
        $this->actingAs($invitee)->get($join)->assertOk()->assertSee('Invitation code');
        $this->post($join, ['invitation_code' => ['invalid']])->assertUnprocessable();
        $this->post($join, ['invitation_code' => "  {$code}\n"])
            ->assertRedirect(route('filament.customer.pages.dashboard', ['tenant' => $organization->slug]))->assertStatus(303);
        $this->assertSame('developer', $invitee->roleInOrganization($organization->id));
        $this->assertSame($invitee->id, $stored->fresh()->accepted_by);

        $this->post($join, ['invitation_code' => $code])->assertUnprocessable();
        $this->get($this->teamRoute('index', $organization))->assertForbidden();
        $this->assertSame(1, DB::table('shield_audit_entries')->where('operation', 'team.invitation_accepted')->count());
    }

    public function test_reissuance_revocation_and_expiry_invalidate_codes(): void
    {
        [$organization, $owner] = $this->organization();
        $invitee = User::factory()->create();
        $first = $this->issue($owner, $organization, $invitee->email, 'viewer');
        $second = $this->issue($owner, $organization, $invitee->email, 'security_analyst');
        $firstRecord = TeamInvitation::query()->where('token_digest', hash('sha256', $first))->sole();
        $secondRecord = TeamInvitation::query()->where('token_digest', hash('sha256', $second))->sole();
        $this->assertNotNull($firstRecord->revoked_at);

        $this->delete($this->teamRoute('revoke', $organization, ['invitation' => $secondRecord->id]))->assertStatus(303);
        $third = $this->issue($owner, $organization, $invitee->email, 'viewer');

        $this->travel(TeamManagement::INVITATION_DAYS)->days();
        $this->issue($owner, $organization, User::factory()->create()->email, 'developer');
        $this->actingAs($invitee);
        foreach ([$first, $second, $third] as $code) {
            $this->post(route('team-invitations.accept'), ['invitation_code' => $code])->assertUnprocessable();
        }

        $this->assertNull($invitee->roleInOrganization($organization->id));
        $this->artisan('shield:prune-team-invitations')->assertSuccessful();
        $this->assertSame(1, TeamInvitation::query()->count());
    }

    public function test_admins_cannot_escalate_privileges_or_cross_tenant_boundaries(): void
    {
        [$organization, $owner] = $this->organization();
        [$otherOrganization, $otherOwner] = $this->organization('other');
        $actor = User::factory()->create();
        $peer = User::factory()->create();
        $viewer = User::factory()->create();
        $pendingAdmin = User::factory()->create();
        $organization->members()->attach([$actor->id => ['role' => 'admin'], $peer->id => ['role' => 'admin'], $viewer->id => ['role' => 'viewer']]);
        $this->issue($owner, $organization, $pendingAdmin->email, 'admin');
        $invite = TeamInvitation::query()->sole();

        $this->actingAs($actor)->get($this->teamRoute('index', $otherOrganization))->assertForbidden();
        $this->get($this->teamRoute('index', $organization))->assertOk()->assertDontSee('Download team and audit JSON');
        $this->post($this->teamRoute('issue', $organization), ['email' => $pendingAdmin->email, 'role' => 'viewer'])->assertForbidden();
        $this->delete($this->teamRoute('revoke', $organization, ['invitation' => $invite->id]))->assertForbidden();
        $this->assertNull($invite->fresh()->revoked_at);
        $this->post($this->teamRoute('issue', $organization), ['email' => 'new@example.net', 'role' => 'admin'])->assertForbidden();
        $this->patch($this->teamRoute('update-role', $organization, ['member' => $viewer->id]), ['role' => 'owner'])->assertForbidden();
        $this->patch($this->teamRoute('update-role', $organization, ['member' => $peer->id]), ['role' => 'viewer'])->assertForbidden();
        $this->delete($this->teamRoute('remove', $organization, ['member' => $owner->id]))->assertForbidden();
        $this->delete($this->teamRoute('remove', $organization, ['member' => $otherOwner->id]))->assertNotFound();
        $this->post($this->teamRoute('transfer', $organization), ['member' => $actor->id, 'current_password' => 'password'])->assertForbidden();

        $this->patch($this->teamRoute('update-role', $organization, ['member' => $viewer->id]), ['role' => 'developer'])->assertStatus(303);
        $this->assertSame('developer', $viewer->roleInOrganization($organization->id));
        $this->delete($this->teamRoute('remove', $organization, ['member' => $viewer->id]))->assertStatus(303);
        $this->assertNull($viewer->roleInOrganization($organization->id));
    }

    public function test_owner_changes_are_atomic_require_reauthentication_and_revoke_pending_codes(): void
    {
        [$organization, $owner] = $this->organization();
        $nextOwner = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $invitee = User::factory()->create();
        $organization->members()->attach([$nextOwner->id => ['role' => 'viewer'], $unverified->id => ['role' => 'viewer']]);
        $code = $this->issue($owner, $organization, $invitee->email, 'admin');
        $transfer = $this->teamRoute('transfer', $organization);

        $this->delete($this->teamRoute('remove', $organization, ['member' => $owner->id]))->assertForbidden();
        $this->patch($this->teamRoute('update-role', $organization, ['member' => $owner->id]), ['role' => 'viewer'])->assertForbidden();
        $this->postJson($transfer, ['member' => $nextOwner->id, 'current_password' => 'incorrect'])->assertUnprocessable();
        $this->post($transfer, ['member' => $unverified->id, 'current_password' => 'password'])->assertUnprocessable();
        $this->assertSame('owner', $owner->roleInOrganization($organization->id));
        $this->post($transfer, ['member' => $nextOwner->id, 'current_password' => 'password'])->assertStatus(303);
        $this->assertSame('admin', $owner->roleInOrganization($organization->id));
        $this->assertSame('owner', $nextOwner->roleInOrganization($organization->id));
        $this->assertSame(1, $organization->members()->wherePivot('role', 'owner')->count());
        $this->assertDatabaseHas('shield_audit_entries', ['organization_id' => $organization->id, 'operation' => 'team.ownership_transferred']);
        $this->actingAs($invitee)->post(route('team-invitations.accept'), ['invitation_code' => $code])->assertUnprocessable();
        $this->assertNotNull(TeamInvitation::query()->sole()->revoked_at);
    }

    public function test_export_is_bounded_owner_only_and_redacts_operator_reasons(): void
    {
        [$organization, $owner] = $this->organization();
        [$otherOrganization, $otherOwner] = $this->organization('other');
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin']);
        Audit::record($otherOwner, 'other.only', $otherOrganization->id, reason: 'Another tenant secret');
        foreach (array_chunk(range(1, 501), 100) as $chunk) {
            $rows = array_map(fn (int $index): array => [
                'organization_id' => $organization->id,
                'actor_id' => $owner->id,
                'operation' => 'tenant.test',
                'reason' => "Private operator note {$index}",
                'created_at' => now(),
            ], $chunk);
            DB::table('shield_audit_entries')->insert($rows);
        }

        $url = $this->teamRoute('export', $organization);
        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->actingAs($owner)->get($this->teamRoute('export', $otherOrganization))->assertForbidden();
        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="shield-team-audit.json"')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('organization.slug', $organization->slug)
            ->assertJsonPath('members.0.email', $owner->email)
            ->assertJsonPath('members.1.role', 'admin')
            ->assertJsonCount(500, 'audit')
            ->assertJsonPath('truncated.audit', true)
            ->assertJsonPath('truncated.members', false)
            ->assertJsonMissingPath('audit.0.reason')
            ->assertDontSee('other.only');
    }

    private function issue(User $owner, Organization $organization, string $email, string $role): string
    {
        $response = $this->actingAs($owner)->post($this->teamRoute('issue', $organization), compact('email', 'role'))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, preg_match('/id="invitation-code">([a-f0-9]{64})</', $response->getContent(), $matches));

        return $matches[1];
    }

    private function organization(string $slug = 'team'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true]);
        $owner = User::factory()->create();
        $organization->members()->attach($owner, ['role' => 'owner']);

        return [$organization, $owner];
    }

    private function teamRoute(string $action, Organization $organization, array $parameters = []): string
    {
        return route('team.'.$action, ['organization' => $organization->slug] + $parameters);
    }
}
