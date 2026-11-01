<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Tells platform admins someone was put under review.
 */
class ModerationAlert extends CommunityActivity
{
    public function __construct(public User $suspect, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database']; // a bot wave could otherwise flood the admin's inbox
    }

    public function actor(): ?User
    {
        return $this->suspect;
    }

    public function message(): string
    {
        return "{$this->suspect->name} was put under review: {$this->reason}";
    }

    public function url(): string
    {
        return route('moderation.index');
    }
}
