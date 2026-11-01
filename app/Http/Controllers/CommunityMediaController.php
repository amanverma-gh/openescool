<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * About-page gallery managed by owners and admins.
 */
class CommunityMediaController extends Controller
{
    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $data = $request->validate([
            'type' => ['required', Rule::in([CommunityMedia::IMAGE, CommunityMedia::VIDEO])],
            'image' => ['required_if:type,image', 'nullable', 'image', 'max:5120'],
            'url' => ['required_if:type,video', 'nullable', 'url', 'max:500', 'regex:'.CommunityMedia::VIDEO_PATTERN],
        ], ['url.regex' => 'Use a YouTube, Vimeo or Loom link.']);

        if ($community->media()->count() >= CommunityMedia::MAX_ITEMS) {
            return back()->with('error', 'The gallery holds up to '.CommunityMedia::MAX_ITEMS.' items. Remove one first.');
        }

        $community->media()->create([
            'type' => $data['type'],
            'path' => $data['type'] === CommunityMedia::IMAGE ? $request->file('image')->store("communities/{$community->id}/gallery", 'public') : null,
            'url' => $data['type'] === CommunityMedia::VIDEO ? $data['url'] : null,
            'position' => (int) $community->media()->max('position') + 1,
        ]);

        return back()->with('success', 'Added to the gallery.');
    }

    public function destroy(Community $community, CommunityMedia $media): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($media->community_id === $community->id, 404);

        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();

        return back()->with('success', 'Removed from the gallery.');
    }

    /**
     * Swap with the neighbour to the left or right.
     */
    public function move(Request $request, Community $community, CommunityMedia $media): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($media->community_id === $community->id, 404);

        $direction = $request->validate(['direction' => ['required', Rule::in(['left', 'right'])]])['direction'];
        $items = $community->media()->get()->values();
        $index = $items->search(fn (CommunityMedia $m) => $m->is($media));
        $neighbour = $items->get($direction === 'left' ? $index - 1 : $index + 1);

        if ($neighbour) {
            DB::transaction(function () use ($items) {
                // Normalise positions first so a swap always changes the order.
                $items->each(fn (CommunityMedia $m, int $i) => $m->update(['position' => $i]));
            });
            [$a, $b] = [$media->fresh()->position, $neighbour->fresh()->position];
            $media->update(['position' => $b]);
            $neighbour->update(['position' => $a]);
        }

        return back();
    }
}
