<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Create or update your own review. Only current members (not the owner) can review.
     */
    public function store(Request $request, Community $community): RedirectResponse
    {
        $member = $community->membershipFor($request->user());
        abort_unless($member?->isActive() && $member->role !== CommunityMember::ROLE_OWNER, 403, 'Only members can review this community.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review = $community->reviews()->firstOrNew(['user_id' => $request->user()->id]);
        $review->fill($data)->save();

        return back()->with('success', $review->wasRecentlyCreated ? 'Thanks for your review!' : 'Review updated.');
    }

    public function destroy(Request $request, Community $community): RedirectResponse
    {
        $community->reviews()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Review deleted.');
    }

    /**
     * Owners can hide abusive reviews (they can't edit them).
     */
    public function toggleHidden(Community $community, Review $review): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($review->community_id === $community->id, 404);

        $review->update(['is_hidden' => ! $review->is_hidden]);

        return back()->with('success', $review->is_hidden ? 'Review hidden.' : 'Review is visible again.');
    }
}
