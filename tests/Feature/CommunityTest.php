<?php

namespace Tests\Feature;

use App\Jobs\SyncBrevoContact;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Notifications\MembershipUpdate;
use App\Notifications\Mentioned;
use App\Notifications\NewComment;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCommunity(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $owner->forceFill(['is_admin' => true])->save();

        $community = new Community(array_merge([
            'name' => 'Test Community',
            'slug' => 'test',
            'privacy' => Community::PRIVACY_PUBLIC,
        ], $attributes));
        $community->owner()->associate($owner)->save();
        app(MembershipService::class)->addOwner($community, $owner);

        return [$community, $owner];
    }

    public function test_only_admins_can_create_communities(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('communities.store'), [
            'name' => 'Nope', 'slug' => 'nope', 'privacy' => 'public',
        ])->assertForbidden();

        $user->forceFill(['is_admin' => true])->save();

        $this->actingAs($user)->post(route('communities.store'), [
            'name' => 'My Group', 'slug' => 'my-group', 'privacy' => 'public',
        ])->assertRedirect(route('communities.feed', 'my-group'));

        $community = Community::where('slug', 'my-group')->firstOrFail();
        $this->assertSame(CommunityMember::ROLE_OWNER, $community->membershipFor($user)->role);
        $this->assertSame(3, $community->categories()->count());
        $this->assertSame(1, $community->members_count);
    }

    public function test_guests_can_read_public_feed_but_not_private(): void
    {
        [$public] = $this->makeCommunity();
        [$private] = $this->makeCommunity(['slug' => 'secret', 'privacy' => Community::PRIVACY_PRIVATE]);

        $this->get(route('communities.feed', $public))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('communities/feed')->where('community.can.participate', false));

        $this->get(route('communities.feed', $private))->assertRedirect(route('communities.about', $private));
        $this->get(route('communities.about', $private))->assertOk();
    }

    public function test_user_joins_public_community_and_syncs_to_brevo(): void
    {
        Queue::fake();
        [$community] = $this->makeCommunity();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('communities.join', $community))
            ->assertRedirect(route('communities.feed', $community));

        $this->assertTrue($community->fresh()->membershipFor($user)->isActive());
        $this->assertSame(2, $community->fresh()->members_count);
        Queue::assertPushed(SyncBrevoContact::class, fn ($job) => $job->user->is($user));
    }

    public function test_private_community_requires_approval(): void
    {
        Notification::fake();
        [$community, $owner] = $this->makeCommunity(['privacy' => Community::PRIVACY_PRIVATE, 'join_questions' => ['Why join?']]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('communities.join', $community), ['answers' => ['To learn']]);

        $member = $community->members()->where('user_id', $user->id)->first();
        $this->assertSame(CommunityMember::STATUS_PENDING, $member->status);
        $this->assertSame(['Why join?' => 'To learn'], $member->answers);
        Notification::assertSentTo($owner, MembershipUpdate::class);

        // Pending members cannot post.
        $this->actingAs($user)->post(route('posts.store', $community), ['title' => 'Hi', 'body' => 'Hello'])->assertForbidden();

        $this->actingAs($owner)->post(route('members.approve', [$community, $member]))->assertRedirect();
        $this->assertSame(CommunityMember::STATUS_ACTIVE, $member->fresh()->status);
        Notification::assertSentTo($user, MembershipUpdate::class);
    }

    public function test_members_post_comment_and_like_earning_points(): void
    {
        Notification::fake();
        [$community, $owner] = $this->makeCommunity();
        $member = User::factory()->create(['username' => 'jane']);
        app(MembershipService::class)->join($community, $member);

        $this->actingAs($member)->post(route('posts.store', $community), [
            'title' => 'My first post',
            'body' => 'Hello everyone!',
        ])->assertSessionHasNoErrors();
        $post = $community->posts()->firstOrFail();

        // Owner comments and mentions jane → jane gets a mention, not a duplicate comment notification.
        $this->actingAs($owner)->post(route('comments.store', [$community, $post]), ['body' => 'Welcome @jane!'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $post->fresh()->comments_count);
        Notification::assertSentToTimes($member, Mentioned::class, 1);
        Notification::assertNotSentTo($member, NewComment::class);

        // Like awards the author a point; unliking removes it.
        $this->actingAs($owner)->post(route('posts.like', [$community, $post]));
        $this->assertSame(1, $post->fresh()->likes_count);
        $this->assertSame(1, $community->membershipFor($member)->fresh()->points);

        $this->actingAs($owner)->post(route('posts.like', [$community, $post]));
        $this->assertSame(0, $post->fresh()->likes_count);
        $this->assertSame(0, $community->membershipFor($member)->fresh()->points);

        // You can't like your own post.
        $this->actingAs($member)->post(route('posts.like', [$community, $post]))->assertStatus(422);
    }

    public function test_members_cannot_edit_others_posts_or_use_admin_categories(): void
    {
        [$community, $owner] = $this->makeCommunity();
        $announcements = $community->categories()->create(['name' => 'Announcements', 'slug' => 'announcements', 'admin_only' => true]);
        $member = User::factory()->create();
        app(MembershipService::class)->join($community, $member);

        $this->actingAs($member)->post(route('posts.store', $community), [
            'title' => 'Sneaky', 'body' => 'Body', 'category_id' => $announcements->id,
        ])->assertSessionHasErrors('category_id');

        $ownerPost = $community->posts()->create(['user_id' => $owner->id, 'title' => 'Rules', 'body' => 'Be kind']);

        $this->actingAs($member)->patch(route('posts.update', [$community, $ownerPost]), ['title' => 'Hacked', 'body' => 'x'])->assertForbidden();
        $this->actingAs($member)->delete(route('posts.destroy', [$community, $ownerPost]))->assertForbidden();
        $this->actingAs($member)->post(route('posts.pin', [$community, $ownerPost]))->assertForbidden();

        $this->actingAs($owner)->post(route('posts.pin', [$community, $ownerPost]));
        $this->assertTrue($ownerPost->fresh()->is_pinned);
    }

    public function test_posts_from_another_community_are_not_reachable(): void
    {
        [$a, $owner] = $this->makeCommunity();
        [$b] = $this->makeCommunity(['slug' => 'other']);
        $post = $a->posts()->create(['user_id' => $owner->id, 'title' => 'A', 'body' => 'A']);

        $this->get(route('posts.show', [$b, $post]))->assertNotFound();
    }

    public function test_only_owner_can_promote_admins_and_owner_cannot_be_removed(): void
    {
        [$community, $owner] = $this->makeCommunity();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $service = app(MembershipService::class);
        $service->join($community, $admin);
        $service->join($community, $member);
        $adminMembership = $community->members()->where('user_id', $admin->id)->first();
        $memberMembership = $community->members()->where('user_id', $member->id)->first();
        $ownerMembership = $community->members()->where('user_id', $owner->id)->first();

        $this->actingAs($owner)->patch(route('members.update', [$community, $adminMembership]), ['role' => 'admin'])->assertRedirect();
        $this->assertSame('admin', $adminMembership->fresh()->role);

        $this->actingAs($admin)->patch(route('members.update', [$community, $memberMembership]), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($admin)->delete(route('members.destroy', [$community, $ownerMembership]))->assertForbidden();

        $this->actingAs($admin)->delete(route('members.destroy', [$community, $memberMembership]), ['ban' => true])->assertRedirect();
        $this->assertSame(CommunityMember::STATUS_BANNED, $memberMembership->fresh()->status);

        // Banned users can't rejoin.
        $this->actingAs($member)->post(route('communities.join', $community))->assertForbidden();
    }

    public function test_feed_renders_with_posts_and_profile_page_works(): void
    {
        [$community, $owner] = $this->makeCommunity();
        $community->posts()->create(['user_id' => $owner->id, 'title' => 'Hello', 'body' => 'World']);

        $this->actingAs($owner)->get(route('communities.feed', $community))
            ->assertInertia(fn (Assert $page) => $page
                ->component('communities/feed')
                ->has('posts', 1)
                ->where('posts.0.title', 'Hello')
                ->where('posts.0.can.delete', true));

        $this->get(route('users.show', $owner->username))
            ->assertInertia(fn (Assert $page) => $page->component('users/show')->has('posts', 1));
    }
}
