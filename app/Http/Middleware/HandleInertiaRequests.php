<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'logo' => fn () => AppSetting::logoUrl(),
            'auth' => [
                'user' => $user ? $user->only(['id', 'name', 'username', 'email', 'avatar', 'bio', 'is_admin', 'email_verified_at']) + [
                    'restricted' => $user->isRestricted(),
                ] : null,
                'communities' => fn () => $user?->communities()->orderBy('name')->get(['communities.id', 'name', 'slug', 'icon_path'])
                    ->map->only(['id', 'name', 'slug', 'icon']),
                'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
                'unreadChats' => fn () => $user ? Conversation::unreadFor($user)->count() : 0,
                // Platform admins see how many accounts are waiting for a decision.
                'moderationQueue' => fn () => $user?->is_admin ? User::whereNotNull('restricted_at')->whereNull('banned_at')->count() : 0,
            ],
            'features' => [
                'google' => filled(config('services.google.client_id')),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
