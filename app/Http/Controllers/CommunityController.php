<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Review;
use App\Models\Subscription;
use App\Services\MembershipService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    /**
     * Discovery page: the signed-in user's communities plus public ones.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $mine = $user
            ? $user->communities()->select('communities.*')
                ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
                ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
                ->orderBy('name')->get()
            : collect();

        $discover = Community::query()
            ->where('privacy', Community::PRIVACY_PUBLIC)
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->whereNotIn('id', $mine->pluck('id'))
            ->orderByDesc('members_count')
            ->limit(30)
            ->get();

        return Inertia::render('communities/index', [
            'mine' => $mine->map(fn (Community $c) => $this->card($c)),
            'discover' => $discover->map(fn (Community $c) => $this->card($c)),
            'canCreate' => (bool) $user?->can('create', Community::class),
        ]);
    }

    /**
     * After login: jump straight into the user's community when they have exactly one.
     */
    public function dashboard(Request $request): RedirectResponse
    {
        $communities = $request->user()->communities()->limit(2)->get();

        return $communities->count() === 1
            ? to_route('communities.feed', $communities->first())
            : to_route('home');
    }

    public function create(): Response
    {
        $this->authorize('create', Community::class);

        return Inertia::render('communities/create');
    }

    public function store(Request $request, MembershipService $memberships): RedirectResponse
    {
        $this->authorize('create', Community::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('communities', 'slug')],
            'tagline' => ['nullable', 'string', 'max:140'],
            'privacy' => ['required', Rule::in([Community::PRIVACY_PUBLIC, Community::PRIVACY_PRIVATE])],
        ]);

        $community = new Community([...$data, 'slug' => Str::lower($data['slug'])]);
        $community->owner()->associate($request->user());
        $community->save();

        foreach ([['General discussion', '💬'], ['Wins', '🏆'], ['Introductions', '👋']] as $position => [$name, $emoji]) {
            $community->categories()->create(['name' => $name, 'slug' => Str::slug($name), 'emoji' => $emoji, 'position' => $position]);
        }

        $memberships->addOwner($community, $request->user());

        return to_route('communities.feed', $community)->with('success', 'Community created. Say hello with your first post!');
    }

    /**
     * Public "About" page — the landing page for non-members.
     */
    public function show(Request $request, Community $community): Response
    {
        $admins = $community->activeMembers()
            ->whereIn('role', [CommunityMember::ROLE_OWNER, CommunityMember::ROLE_ADMIN])
            ->with('user')
            ->limit(6)
            ->get()
            ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['role' => $m->role]);

        return Inertia::render('communities/about', [
            'community' => $this->communityProps($community) + [
                'about' => $community->about,
                'requires_approval' => $community->requires_approval || ! $community->isPublic(),
                'join_questions' => $community->join_questions ?? [],
                'created_at' => $community->created_at,
                'plans' => $community->isPaid()
                    ? $community->plans()->where('is_active', true)->get()->map->toPublicArray()
                    : [],
            ],
            'admins' => $admins,
            'media' => $community->media()->get()->map->toPublicArray(),
            'rating' => $community->ratingSummary(),
            'reviews' => $this->reviews($request, $community),
            'canReview' => ($me = $community->membershipFor($request->user()))?->isActive() && $me->role !== CommunityMember::ROLE_OWNER,
            'myReview' => $request->user() ? $community->reviews()->where('user_id', $request->user()->id)->first()?->only(['rating', 'body']) : null,
            'stats' => [
                'members' => $community->members_count,
                'posts' => $community->posts()->count(),
                'online' => $community->activeMembers()
                    ->whereHas('user', fn ($q) => $q->where('last_seen_at', '>=', now()->subMinutes(5)))
                    ->count(),
            ],
        ]);
    }

    /**
     * Newest reviews with a tenure label ("Still a paying member after 4 months").
     * Managers also see hidden reviews so they can restore them.
     *
     * @return list<array<string, mixed>>
     */
    protected function reviews(Request $request, Community $community): array
    {
        $canManage = (bool) $request->user()?->can('manage', $community);

        $reviews = $community->reviews()
            ->when(! $canManage, fn ($q) => $q->visible())
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        $userIds = $reviews->pluck('user_id');
        $members = $community->members()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $paying = $community->subscriptions()->whereIn('user_id', $userIds)->where('status', '!=', Subscription::ENDED)->get()
            ->filter(fn (Subscription $s) => $s->grantsAccess())
            ->pluck('user_id')
            ->flip();

        return $reviews->map(function (Review $review) use ($members, $paying) {
            $member = $members[$review->user_id] ?? null;
            $since = $member?->joined_at ? $member->joined_at->min(now()->subDay())->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, parts: 1) : null;

            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'body' => $review->body,
                'is_hidden' => $review->is_hidden,
                'created_at' => $review->created_at,
                'user' => $review->user->toPublicArray(),
                'tenure' => match (true) {
                    ! $member?->isActive() => 'Former member',
                    isset($paying[$review->user_id]) => "Still a paying member after {$since}",
                    default => "Member for {$since}",
                },
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function card(Community $community): array
    {
        return $community->only(['id', 'name', 'slug', 'tagline', 'icon', 'cover', 'privacy', 'members_count']) + [
            'rating' => $community->rating_count ? ['average' => round((float) $community->rating_avg, 1), 'count' => (int) $community->rating_count] : null,
        ];
    }
}
