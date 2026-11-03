<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunitySettingsController extends Controller
{
    public function edit(Request $request, Community $community): Response
    {
        $this->authorize('manage', $community);

        return Inertia::render('communities/settings', [
            'community' => $this->communityProps($community),
            'settings' => $community->only(['name', 'tagline', 'about', 'privacy', 'requires_approval', 'allow_images', 'allow_gifs', 'allow_comment_media', 'seo_index', 'seo_index_posts']) + [
                'join_questions' => $community->join_questions ?? [],
                'level_names' => $community->levelNames(),
            ],
            'categories' => $community->categories()->withCount('posts')->get(),
            'media' => $community->media()->get()->map->toPublicArray(),
            'giphyReady' => filled(AppSetting::get('giphy_api_key')),
            'siteIndexed' => Seo::siteIndexed(),
            'team' => $community->activeMembers()
                ->with('user')
                ->whereIn('role', CommunityMember::STAFF_ROLES)
                ->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 else 2 end")
                ->get()
                ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['member_id' => $m->id, 'role' => $m->role]),
            'canManageAdmins' => $community->membershipFor($request->user())?->role === CommunityMember::ROLE_OWNER || $request->user()->is_admin,
            // Set platform-wide in Admin settings; shown here so owners know the limits.
            'photoLimits' => ['max_images' => AppSetting::maxImages(), 'max_kb' => AppSetting::maxUploadKb()],
        ]);
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:140'],
            'about' => ['nullable', 'string', 'max:10000'],
            'privacy' => ['required', Rule::in([Community::PRIVACY_PUBLIC, Community::PRIVACY_PRIVATE])],
            'requires_approval' => ['boolean'],
            'allow_images' => ['boolean'],
            'allow_gifs' => ['boolean'],
            'allow_comment_media' => ['boolean'],
            'seo_index' => ['boolean'],
            'seo_index_posts' => ['boolean'],
            'join_questions' => ['array', 'max:3'],
            'join_questions.*' => ['string', 'max:200'],
            'level_names' => ['array', 'size:'.count(CommunityMember::LEVEL_THRESHOLDS)],
            'level_names.*' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'image', 'max:2048'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach (['icon', 'cover'] as $field) {
            if ($request->hasFile($field)) {
                if ($community->{$field.'_path'}) {
                    Storage::disk('public')->delete($community->{$field.'_path'});
                }
                $community->{$field.'_path'} = $request->file($field)->store("communities/{$community->id}", 'public');
            }
            unset($data[$field]);
        }

        $data['join_questions'] = array_values(array_filter($data['join_questions'] ?? []));

        if (array_key_exists('level_names', $data)) {
            $names = array_map(fn ($name) => filled($name) ? trim($name) : null, $data['level_names']);
            $data['level_names'] = array_filter($names) ? $names : null;
        }
        $community->fill($data)->save();

        return back()->with('success', 'Settings saved.');
    }

    public function storeCategory(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $data = $this->validateCategory($request);

        $community->categories()->create($data + [
            'slug' => $this->uniqueSlug($community, $data['name']),
            'position' => (int) $community->categories()->max('position') + 1,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, Community $community, Category $category): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($category->community_id === $community->id, 404);

        $category->update($this->validateCategory($request));

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(Community $community, Category $category): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($category->community_id === $community->id, 404);

        $category->delete(); // posts keep existing with no category

        return back()->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCategory(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'admin_only' => ['boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    protected function uniqueSlug(Community $community, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($i = 2; $community->categories()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
