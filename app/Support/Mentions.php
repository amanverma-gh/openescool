<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Collection;

class Mentions
{
    /**
     * Active members of the community mentioned as @username in the text.
     *
     * @return Collection<int, User>
     */
    public static function resolve(string $text, Community $community, ?User $except = null): Collection
    {
        preg_match_all('/(?<![\w@])@([a-z0-9][a-z0-9_-]{1,49})/i', $text, $matches);

        $usernames = array_unique(array_map('strtolower', $matches[1]));

        if ($usernames === []) {
            return collect();
        }

        return User::query()
            ->whereIn('username', array_slice($usernames, 0, 20))
            ->whereHas('memberships', fn ($q) => $q->where('community_id', $community->id)->where('status', 'active'))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get();
    }
}
