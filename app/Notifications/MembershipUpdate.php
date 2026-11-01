<?php

namespace App\Notifications;

use App\Models\CommunityMember;
use App\Models\User;

/**
 * Join requests (sent to admins) and approvals (sent to the member).
 */
class MembershipUpdate extends CommunityActivity
{
    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public function __construct(public CommunityMember $member, public string $event) {}

    public function actor(): ?User
    {
        return $this->member->user;
    }

    public function emailType(): ?string
    {
        return 'membership';
    }

    public function message(): string
    {
        $community = $this->member->community->name;

        return $this->event === self::REQUESTED
            ? "{$this->member->user->name} requested to join {$community}"
            : "Welcome! Your request to join {$community} was approved";
    }

    public function url(): string
    {
        return $this->event === self::REQUESTED
            ? route('members.index', [$this->member->community, 'tab' => 'pending'])
            : route('communities.feed', $this->member->community);
    }
}
