<?php

namespace App\Http\Controllers;

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

        return [
            'id' => $community->id,
            'name' => $community->name,
            'slug' => $community->slug,
            'tagline' => $community->tagline,
            'icon' => $community->icon,
            'cover' => $community->cover,
            'privacy' => $community->privacy,
            'access_type' => $community->access_type,
            'members_count' => $community->members_count,
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
            ],
        ];
    }
}
