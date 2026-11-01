<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function store(Request $request, Community $community, MembershipService $memberships): RedirectResponse
    {
        $questions = $community->join_questions ?? [];

        $data = $request->validate([
            'answers' => ['array', 'max:'.count($questions)],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = $community->membershipFor($request->user());
        abort_if($existing?->status === CommunityMember::STATUS_BANNED, 403, 'You cannot join this community.');

        // Paid communities: payment is the admission ticket (no approval queue).
        if ($community->isPaid() && ! $existing?->isActive()) {
            return to_route('billing.checkout', $community);
        }

        $answers = collect($questions)
            ->mapWithKeys(fn (string $question, int $i) => [$question => $data['answers'][$i] ?? null])
            ->all();

        $member = $memberships->join($community, $request->user(), $answers ?: null);

        return $member->isActive()
            ? to_route('communities.feed', $community)->with('success', "Welcome to {$community->name}!")
            : back()->with('success', 'Request sent. You will get an email once an admin approves it.');
    }

    public function destroy(Request $request, Community $community, MembershipService $memberships): RedirectResponse
    {
        $member = $community->membershipFor($request->user());

        abort_unless($member, 404);
        abort_if($member->role === CommunityMember::ROLE_OWNER, 422, 'The owner cannot leave their own community.');

        $memberships->remove($member);

        return to_route('home')->with('success', "You left {$community->name}.");
    }
}
