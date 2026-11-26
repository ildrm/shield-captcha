<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TeamManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class TeamController
{
    private const HEADERS = [
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex',
        'Content-Security-Policy' => "default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
    ];

    public function __construct(private readonly TeamManagement $teams) {}

    public function index(Request $request, Organization $organization): Response
    {
        $actor = $this->actor($request);
        abort_unless(TeamManagement::canManage($actor, $organization), 403);
        $roles = TeamManagement::assignableRoles($actor, $organization);
        $canTransfer = TeamManagement::isOwner($actor, $organization);

        return $this->page('index', [
            'actor' => $actor,
            'organization' => $organization,
            'roles' => $roles,
            'canTransfer' => $canTransfer,
            'members' => $organization->members()->orderBy('users.name')->orderBy('users.id')
                ->simplePaginate(20, ['users.id', 'users.name', 'users.email', 'users.email_verified_at', 'users.is_operator'], 'members')
                ->withQueryString(),
            'invitations' => $this->teams->pending($organization)->latest('created_at')->orderByDesc('id')
                ->simplePaginate(15, ['id', 'email', 'role', 'expires_at'], 'invites')->withQueryString(),
            'audit' => AuditEntry::query()->where('organization_id', $organization->id)->latest('id')
                ->simplePaginate(20, ['id', 'actor_id', 'site_id', 'operation', 'created_at'], 'audit')->withQueryString(),
        ]);
    }

    public function join(Request $request): Response|RedirectResponse
    {
        $actor = $this->actor($request);
        abort_if($actor->is_operator, 403);
        if (! $actor->hasVerifiedEmail()) {
            return redirect()->guest(route('filament.customer.auth.email-verification.prompt'), headers: self::HEADERS);
        }

        return $this->page('join');
    }

    public function issue(Request $request, Organization $organization): Response
    {
        $actor = $this->actor($request);
        abort_unless(TeamManagement::canManage($actor, $organization), 403);
        $input = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'role' => ['required', 'string', Rule::in(array_keys(TeamManagement::ROLES))],
        ]);
        $code = $this->teams->issue($actor, $organization, $input['email'], $input['role']);

        return $this->page('issued', ['code' => $code, 'organization' => $organization]);
    }

    public function accept(Request $request): Response|RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless(TeamManagement::canAccept($actor), 403);
        $code = $request->input('invitation_code');
        // Keep pasted codes out of validation flash data and session storage.
        $organization = is_string($code) ? $this->teams->accept($actor, trim($code)) : null;

        return $organization !== null
            ? redirect()->route('filament.customer.pages.dashboard', ['tenant' => $organization->slug], 303, self::HEADERS)
            : $this->page('join', ['invalid' => true], 422);
    }

    public function revoke(Request $request, Organization $organization, string $invitation): RedirectResponse
    {
        $this->teams->revoke($this->actor($request), $organization, $invitation);

        return $this->backToTeam($organization, __('team.invitation_revoked'));
    }

    public function updateRole(Request $request, Organization $organization, string $member): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless(TeamManagement::canManage($actor, $organization), 403);
        $input = $request->validate(['role' => ['required', 'string', Rule::in(array_keys(TeamManagement::ROLES))]]);
        $this->teams->updateRole($actor, $organization, (int) $member, $input['role']);

        return $this->backToTeam($organization, __('team.role_saved'));
    }

    public function remove(Request $request, Organization $organization, string $member): RedirectResponse
    {
        $this->teams->remove($this->actor($request), $organization, (int) $member);

        return $this->backToTeam($organization, __('team.member_removed'));
    }

    public function transfer(Request $request, Organization $organization): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless(TeamManagement::isOwner($actor, $organization), 403);
        $input = $request->validate([
            'member' => ['required', 'integer', 'min:1'],
            'current_password' => ['required', 'string', 'max:512', 'current_password:web'],
        ]);
        $this->teams->transfer($actor, $organization, (int) $input['member']);

        return $this->backToTeam($organization, __('team.ownership_transferred'));
    }

    public function export(Request $request, Organization $organization): JsonResponse
    {
        abort_unless(TeamManagement::isOwner($this->actor($request), $organization), 403);

        $members = $organization->members()->orderBy('users.id')->limit(251)->get(['users.id', 'users.name', 'users.email']);
        $audit = AuditEntry::query()->where('organization_id', $organization->id)->latest('id')
            ->limit(501)->get(['id', 'actor_id', 'site_id', 'operation', 'created_at']);

        return response()->json([
            'organization' => ['name' => $organization->name, 'slug' => $organization->slug],
            'exported_at' => now()->toIso8601String(),
            'members' => $members->take(250)->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])->all(),
            'audit' => $audit->take(500)->values()->all(),
            'truncated' => ['members' => $members->count() > 250, 'audit' => $audit->count() > 500],
        ], headers: self::HEADERS + ['Content-Disposition' => 'attachment; filename="shield-team-audit.json"']);
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 403);
        app()->setLocale($request->session()->get('locale') === 'fa' ? 'fa' : 'en');

        return $user;
    }

    private function page(string $view, array $data = [], int $status = 200): Response
    {
        return response()->view('team.'.$view, $data, $status, self::HEADERS);
    }

    private function backToTeam(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('team.index', ['organization' => $organization->slug], 303, self::HEADERS)
            ->with('team_status', $message);
    }
}
