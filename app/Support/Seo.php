<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Community;
use Illuminate\Http\Request;

/**
 * Search engine rules: a site-wide switch (Admin settings), then per community
 * whether its pages and its posts may be indexed. Private communities and
 * personal pages (settings, chat, checkout...) are never indexed.
 */
class Seo
{
    /** Public pages that may appear in search results. */
    public const INDEXABLE_ROUTES = [
        'home', 'discover', 'users.show',
        'communities.about', 'communities.feed', 'posts.show',
        'classroom.index', 'calendar.index', 'leaderboards.index', 'members.index',
    ];

    public static function siteIndexed(): bool
    {
        return AppSetting::flag('seo_index', true);
    }

    public static function communityIndexed(Community $community): bool
    {
        return $community->privacy === Community::PRIVACY_PUBLIC && $community->seo_index;
    }

    public static function postsIndexed(Community $community): bool
    {
        return self::communityIndexed($community) && $community->seo_index_posts;
    }

    public static function robots(Request $request): string
    {
        $route = $request->route();
        if (! self::siteIndexed() || ! $route || ! in_array($route->getName(), self::INDEXABLE_ROUTES, true)) {
            return 'noindex, nofollow';
        }

        $community = $route->parameter('community');
        if ($community instanceof Community) {
            $allowed = $route->getName() === 'posts.show' ? self::postsIndexed($community) : self::communityIndexed($community);
            if (! $allowed) {
                return 'noindex, nofollow';
            }
        }

        return 'index, follow';
    }
}
