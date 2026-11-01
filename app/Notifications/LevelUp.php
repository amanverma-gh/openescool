<?php

namespace App\Notifications;

use App\Models\Community;
use App\Models\Course;
use App\Models\User;

class LevelUp extends CommunityActivity
{
    public function __construct(public Community $community, public int $level) {}

    public function emailType(): ?string
    {
        return 'level_up';
    }

    public function actor(): ?User
    {
        return null;
    }

    public function message(): string
    {
        return "You reached {$this->community->levelLabel($this->level)} in {$this->community->name}! 🎉";
    }

    public function excerpt(): ?string
    {
        $unlocked = $this->community->courses()
            ->where('is_published', true)
            ->where('access', Course::ACCESS_LEVEL)
            ->where('unlock_level', $this->level)
            ->pluck('title');

        return $unlocked->isNotEmpty() ? 'Unlocked: '.$unlocked->join(', ') : null;
    }

    public function url(): string
    {
        return route('leaderboards.index', $this->community);
    }
}
