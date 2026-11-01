<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $canManage = (bool) $request->user()?->can('manage', $community);
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['members', 'admins', 'pending'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $filters['tab'] ?? 'members';
        abort_if($tab === 'pending' && ! $canManage, 403);

        $members = $community->members()
            ->with('user')
            ->when($tab === 'pending', fn ($q) => $q->where('status', CommunityMember::STATUS_PENDING))
            ->when($tab !== 'pending', fn ($q) => $q->where('status', CommunityMember::STATUS_ACTIVE))
            ->when($tab === 'admins', fn ($q) => $q->whereIn('role', [
                CommunityMember::ROLE_OWNER, CommunityMember::ROLE_ADMIN, CommunityMember::ROLE_MODERATOR,
            ]))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 when 'moderator' then 2 else 3 end")
            ->orderByDesc('joined_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (CommunityMember $m) => $m->user->toPublicArray() + [
                'member_id' => $m->id,
                'bio' => $m->user->bio,
                'role' => $m->role,
                'level' => $m->level,
                'points' => $m->points,
                'joined_at' => $m->joined_at,
                'last_seen_at' => $m->user->last_seen_at,
                'answers' => $canManage ? $m->answers : null,
            ]);

        return Inertia::render('communities/members', [
            'community' => $this->communityProps($community),
            'members' => $members,
            'filters' => ['tab' => $tab, 'q' => $filters['q'] ?? ''],
            'counts' => [
                'members' => $community->members_count,
                'admins' => $community->activeMembers()->where('role', '!=', CommunityMember::ROLE_MEMBER)->count(),
                'pending' => $canManage ? $community->members()->where('status', CommunityMember::STATUS_PENDING)->count() : 0,
            ],
        ]);
    }

    public function approve(Community $community, CommunityMember $member, MembershipService $memberships): RedirectResponse
    {
        $this->authorizeMember($community, $member);
        abort_unless($member->status === CommunityMember::STATUS_PENDING, 422);

        $memberships->approve($member);

        return back()->with('success', "{$member->user->name} is now a member.");
    }

    public function update(Request $request, Community $community, CommunityMember $member): RedirectResponse
    {
        $this->authorizeMember($community, $member);

        $data = $request->validate([
            'role' => ['required', Rule::in([CommunityMember::ROLE_ADMIN, CommunityMember::ROLE_MODERATOR, CommunityMember::ROLE_MEMBER])],
        ]);

        // Only the owner (or a platform admin) can promote or demote admins.
        abort_if(
            ($data['role'] === CommunityMember::ROLE_ADMIN || $member->role === CommunityMember::ROLE_ADMIN)
                && ! $this->actsAsOwner($request, $community),
            403,
        );
        abort_unless($member->isActive(), 422, 'Only active members can join the team.');

        $member->update($data);

        return back()->with('success', $data['role'] === CommunityMember::ROLE_MEMBER
            ? "{$member->user->name} is no longer on the team."
            : "{$member->user->name} is now a {$data['role']}.");
    }

    /**
     * Find members to add to the team (Settings → Team).
     */
    public function search(Request $request, Community $community): JsonResponse
    {
        $this->authorize('manage', $community);
        $q = $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

        return response()->json($community->activeMembers()
            ->with('user')
            ->where('role', CommunityMember::ROLE_MEMBER)
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%"))
            ->limit(8)
            ->get()
            ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['member_id' => $m->id]));
    }

    protected function actsAsOwner(Request $request, Community $community): bool
    {
        return $community->membershipFor($request->user())?->role === CommunityMember::ROLE_OWNER || $request->user()->is_admin;
    }

    public function destroy(Request $request, Community $community, CommunityMember $member, MembershipService $memberships): RedirectResponse
    {
        $this->authorizeMember($community, $member);

        abort_if($member->role === CommunityMember::ROLE_ADMIN && ! $this->actsAsOwner($request, $community), 403);

        $memberships->remove($member, ban: $request->boolean('ban'));

        return back()->with('success', $request->boolean('ban') ? 'Member banned.' : 'Member removed.');
    }

    protected function authorizeMember(Community $community, CommunityMember $member): void
    {
        $this->authorize('manage', $community);
        abort_unless($member->community_id === $community->id, 404);
        abort_if($member->role === CommunityMember::ROLE_OWNER, 403, 'The owner cannot be changed.');
        abort_if($member->user_id === request()->user()->id, 403, 'You cannot change your own membership.');
    }
}
