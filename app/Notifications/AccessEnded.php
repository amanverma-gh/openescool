<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;

class AccessEnded extends CommunityActivity
{
    public function __construct(public Subscription $subscription) {}

    public function actor(): ?User
    {
        return null;
    }

    public function message(): string
    {
        return "Your membership in {$this->subscription->community->name} has ended. Rejoin any time to pick up where you left off.";
    }

    public function url(): string
    {
        return route('communities.about', $this->subscription->community);
    }
}
