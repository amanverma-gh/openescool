<?php

namespace App\Support;

use App\Models\Community;
use App\Models\CommunityMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuthorLevels
{
    /**
     * Set `author_level` on each model (post/comment) using one query.
     *
     * @param  iterable<Model>  $models
     */
    public static function attach(Community $community, iterable $models): void
    {
        $models = Collection::wrap($models);

        $points = CommunityMember::query()
            ->where('community_id', $community->id)
            ->whereIn('user_id', $models->pluck('user_id')->unique())
            ->pluck('points', 'user_id');

        foreach ($models as $model) {
            $model->setAttribute('author_level', CommunityMember::levelForPoints((int) ($points[$model->user_id] ?? 0)));
        }
    }
}
