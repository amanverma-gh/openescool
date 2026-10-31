<?php

namespace App\Services;

use App\Jobs\SyncBrevoContact;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Notifications\MembershipUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class MembershipService
{
    /**
     * @param  array<string, string>|null  $answers
     */
    public function join(Community $community, User $user, ?array $answers = null): CommunityMember
    {
        $needsApproval = $community->requires_approval || ! $community->isPublic();

        $member = CommunityMember::firstOrNew([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        if ($member->exists && $member->status !== CommunityMember::STATUS_PENDING) {
            return $member; // already active, or banned
        }

        $member->fill([
            'role' => $member->role ?? CommunityMember::ROLE_MEMBER,
            'status' => $needsApproval ? CommunityMember::STATUS_PENDING : CommunityMember::STATUS_ACTIVE,
            'answers' => $answers,
            'joined_at' => $needsApproval ? null : now(),
        ])->save();

        $community->forgetMembership($user);

        if ($needsApproval) {
            Notification::send($this->managers($community), new MembershipUpdate($member, MembershipUpdate::REQUESTED));
        } else {
            $this->activated($community, $user);
        }

        return $member;
    }

    public function approve(CommunityMember $member): void
    {
        $member->update(['status' => CommunityMember::STATUS_ACTIVE, 'joined_at' => now()]);

        $member->user->notify(new MembershipUpdate($member, MembershipUpdate::APPROVED));

        $this->activated($member->community, $member->user);
    }

    public function remove(CommunityMember $member, bool $ban = false): void
    {
        if ($ban) {
            $member->update(['status' => CommunityMember::STATUS_BANNED, 'role' => CommunityMember::ROLE_MEMBER]);
        } else {
            $member->delete();
        }

        $member->community->refreshMembersCount();
        SyncBrevoContact::dispatch($member->user);
    }

    public function addOwner(Community $community, User $user): void
    {
        $community->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => CommunityMember::ROLE_OWNER, 'status' => CommunityMember::STATUS_ACTIVE, 'joined_at' => now()],
        );

        $this->activated($community, $user);
    }

    protected function activated(Community $community, User $user): void
    {
        $community->refreshMembersCount();
        SyncBrevoContact::dispatch($user);
    }

    /**
     * @return Collection<int, User>
     */
    protected function managers(Community $community)
    {
        return User::whereIn('id', $community->activeMembers()
            ->whereIn('role', [CommunityMember::ROLE_OWNER, CommunityMember::ROLE_ADMIN])
            ->select('user_id'))->get();
    }
}
