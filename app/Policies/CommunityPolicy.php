<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class CommunityPolicy
{
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Read the feed and classroom. Free public communities are readable by anyone;
     * paid ones show only their About page to non-members.
     */
    public function view(?User $user, Community $community): bool
    {
        return ($community->isPublic() && ! $community->isPaid()) || (bool) $community->membershipFor($user)?->isActive();
    }

    /**
     * Post, comment and like.
     */
    public function participate(User $user, Community $community): bool
    {
        // Accounts under spam review can read but not post until an admin decides.
        return ! $user->isRestricted() && (bool) $community->membershipFor($user)?->isActive();
    }

    public function moderate(User $user, Community $community): bool
    {
        return (bool) $community->membershipFor($user)?->canModerate();
    }

    public function manage(User $user, Community $community): bool
    {
        return (bool) $community->membershipFor($user)?->canManage();
    }
}
