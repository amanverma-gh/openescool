<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\PointEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $active = $community->activeMembers();

        // Share of members at each level, for the level ladder.
        $levels = collect(CommunityMember::LEVEL_THRESHOLDS)->map(fn (int $threshold, int $i) => [
            'level' => $i + 1,
            'points' => $threshold,
            'members' => 0,
        ])->all();
        foreach ((clone $active)->pluck('points') as $points) {
            $levels[CommunityMember::levelForPoints((int) $points) - 1]['members']++;
        }
        $total = max(1, array_sum(array_column($levels, 'members')));

        $member = $community->membershipFor($request->user());
        $next = $member ? (CommunityMember::LEVEL_THRESHOLDS[$member->level] ?? null) : null;

        return Inertia::render('leaderboards/index', [
            'community' => $this->communityProps($community),
            'levels' => array_map(fn ($l) => $l + ['percent' => (int) round(100 * $l['members'] / $total)], $levels),
            'me' => $member?->isActive() ? [
                'level' => $member->level,
                'points' => $member->points,
                'points_to_next' => $next !== null ? $next - $member->points : null,
            ] : null,
            'boards' => [
                'week' => $this->windowed($community, 7),
                'month' => $this->windowed($community, 30),
                'all' => (clone $active)->where('points', '>', 0)->orderByDesc('points')->with('user')->limit(10)->get()
                    ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['points' => $m->points, 'level' => $m->level])
                    ->all(),
            ],
        ]);
    }

    /**
     * Top 10 by points earned in the last N days.
     *
     * @return list<array<string, mixed>>
     */
    protected function windowed(Community $community, int $days): array
    {
        $rows = PointEvent::query()
            ->where('community_id', $community->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('user_id', $community->activeMembers()->select('user_id'))
            ->selectRaw('user_id, sum(points) as total')
            ->groupBy('user_id')
            ->havingRaw('sum(points) > 0')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
        $levels = $community->members()->whereIn('user_id', $rows->pluck('user_id'))->pluck('points', 'user_id');

        return $rows->map(fn ($row) => $users[$row->user_id]->toPublicArray() + [
            'points' => (int) $row->total,
            'level' => CommunityMember::levelForPoints((int) ($levels[$row->user_id] ?? 0)),
        ])->all();
    }
}
