<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Private community content: send visitors to the About page (where they can
     * request to join) instead of showing a bare 403.
     */
    protected function ensureCanView(Community $community): void
    {
        if (! Gate::allows('view', $community)) {
            throw new HttpResponseException(to_route('communities.about', $community));
        }
    }

    /**
     * Community header data shared by every page inside a community.
     *
     * @return array<string, mixed>
     */
    protected function communityProps(Community $community): array
    {
        $user = request()->user();
        $membership = $community->membershipFor($user);
        $team = (bool) $membership?->canModerate();

        return [
            'id' => $community->id,
            'name' => $community->name,
            'slug' => $community->slug,
            'tagline' => $community->tagline,
            'icon' => $community->icon,
            'cover' => $community->cover,
            'privacy' => $community->privacy,
            'access_type' => $community->access_type,
            'level_names' => $community->levelNames(),
            'members_count' => $community->members_count,
            'media' => [
                // The team can always add media; the community switches apply to members.
                'images' => $community->allow_images || $team,
                // GIFs also need the platform GIPHY key.
                'gifs' => ($community->allow_gifs || $team) && filled(AppSetting::get('giphy_api_key')),
                'comments' => $community->allow_comment_media || $team,
                'max_images' => AppSetting::maxImages(),
                'max_kb' => AppSetting::maxUploadKb(),
                'resource_kb' => AppSetting::uploadLimitKb('max_resource_kb'),
                'video_kb' => AppSetting::uploadLimitKb('max_video_kb'),
            ],
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
                'points' => $membership->points,
                'level' => $membership->level,
            ] : null,
            'can' => [
                'participate' => (bool) $user?->can('participate', $community),
                'moderate' => (bool) $user?->can('moderate', $community),
                'manage' => (bool) $user?->can('manage', $community),
                'email_post' => $user?->can('manage', $community) ? $community->canEmailPost() : false,
            ],
        ];
    }
}
