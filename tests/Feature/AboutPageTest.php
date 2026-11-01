<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityMedia;
use App\Models\User;
use App\Services\Billing\AccessService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->owner)->save();

        $memberships = app(MembershipService::class);
        $memberships->addOwner($this->community, $this->owner);
        $this->member = User::factory()->create();
        $memberships->join($this->community, $this->member);
    }

    public function test_members_review_and_rating_is_averaged(): void
    {
        $this->actingAs($this->member)->post('/c/test/reviews', ['rating' => 5, 'body' => 'Great community, learned a lot.'])->assertSessionHasNoErrors();
        $this->post('/c/test/reviews', ['rating' => 4, 'body' => 'Updated: still great, a bit busy.']); // edits, no duplicate

        $other = User::factory()->create();
        app(MembershipService::class)->join($this->community, $other);
        $this->actingAs($other)->post('/c/test/reviews', ['rating' => 2, 'body' => 'Not for me, but well run.']);

        $this->assertSame(['average' => 3.0, 'count' => 2], $this->community->ratingSummary());

        $this->get('/c/test/about')->assertInertia(fn (Assert $page) => $page
            ->component('communities/about')
            ->where('rating.count', 2)
            ->has('reviews', 2)
            ->where('reviews.1.tenure', fn (string $tenure) => str_starts_with($tenure, 'Member for'))
            ->where('myReview.rating', 2)
            ->where('canReview', true));
    }

    public function test_only_current_members_except_the_owner_can_review(): void
    {
        $this->actingAs(User::factory()->create())->post('/c/test/reviews', ['rating' => 5, 'body' => 'Never joined but love it'])->assertForbidden();
        $this->actingAs($this->owner)->post('/c/test/reviews', ['rating' => 5, 'body' => 'My own group is the best'])->assertForbidden();
        $this->actingAs($this->member)->post('/c/test/reviews', ['rating' => 6, 'body' => 'short'])->assertSessionHasErrors(['rating', 'body']);
    }

    public function test_paying_members_are_labelled(): void
    {
        $plan = $this->community->plans()->create(['name' => 'Monthly', 'interval' => 'month', 'amount' => 1000, 'currency' => 'USD']);
        app(AccessService::class)->activate($this->member, $this->community, $plan, 'stripe', 'sub_1', now()->addMonth());
        $this->community->reviews()->create(['user_id' => $this->member->id, 'rating' => 5, 'body' => 'Worth every cent.']);

        $this->get('/c/test/about')->assertInertia(fn (Assert $page) => $page
            ->where('reviews.0.tenure', fn (string $tenure) => str_starts_with($tenure, 'Still a paying member after')));
    }

    public function test_owner_can_hide_a_review_from_visitors(): void
    {
        $review = $this->community->reviews()->create(['user_id' => $this->member->id, 'rating' => 1, 'body' => 'Spam spam spam spam']);

        $this->actingAs($this->member)->post("/c/test/reviews/{$review->id}/hide")->assertForbidden();
        $this->actingAs($this->owner)->post("/c/test/reviews/{$review->id}/hide");

        $this->assertTrue($review->fresh()->is_hidden);
        $this->assertSame(0, $this->community->ratingSummary()['count']);

        $this->get('/c/test/about')->assertInertia(fn (Assert $page) => $page->has('reviews', 1)); // owner still sees it
        $this->actingAs($this->member)->get('/c/test/about')->assertInertia(fn (Assert $page) => $page->has('reviews', 0));
    }

    public function test_owner_manages_the_gallery(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner);

        $this->post('/c/test/media', ['type' => 'image', 'image' => UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='))])->assertSessionHasNoErrors();
        $this->post('/c/test/media', ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->assertSessionHasNoErrors();
        $this->post('/c/test/media', ['type' => 'video', 'url' => 'https://evil.example.com/video'])->assertSessionHasErrors('url');

        [$image, $video] = $this->community->media()->get()->all();
        Storage::disk('public')->assertExists($image->path);

        $this->post("/c/test/media/{$video->id}/move", ['direction' => 'left']);
        $this->assertSame(['video', 'image'], $this->community->media()->pluck('type')->all());

        $this->get('/c/test/about')->assertInertia(fn (Assert $page) => $page->has('media', 2)->where('media.0.type', 'video'));

        $this->delete("/c/test/media/{$image->id}");
        Storage::disk('public')->assertMissing($image->path);

        $this->actingAs($this->member)->post('/c/test/media', ['type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
    }

    public function test_gallery_is_capped(): void
    {
        foreach (range(1, CommunityMedia::MAX_ITEMS) as $i) {
            $this->community->media()->create(['type' => 'video', 'url' => 'https://vimeo.com/'.$i, 'position' => $i]);
        }

        $this->actingAs($this->owner)->post('/c/test/media', ['type' => 'video', 'url' => 'https://vimeo.com/99'])->assertSessionHas('error');
        $this->assertSame(CommunityMedia::MAX_ITEMS, $this->community->media()->count());
    }

    public function test_discovery_cards_show_ratings(): void
    {
        $this->community->reviews()->create(['user_id' => $this->member->id, 'rating' => 4, 'body' => 'Good stuff here.']);

        $this->actingAs($this->member)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('mine.0.rating.count', 1)
            ->where('mine.0.id', $this->community->id));
    }
}
