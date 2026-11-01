<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;

class EventReminder extends CommunityActivity
{
    public function __construct(public Event $event, public CarbonImmutable $startsAt) {}

    public function actor(): ?User
    {
        return $this->event->host;
    }

    public function message(): string
    {
        $minutes = max(1, (int) round(now()->diffInMinutes($this->startsAt)));

        return "\"{$this->event->title}\" starts in {$minutes} minutes";
    }

    public function excerpt(): ?string
    {
        return $this->event->location ? 'Join here: '.$this->event->location : null;
    }

    public function url(): string
    {
        return route('calendar.index', $this->event->community);
    }
}
