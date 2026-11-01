<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ModerationAlert;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpamAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->owner->forceFill(['is_admin' => true])->save();

        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->owner)->save();
        app(MembershipService::class)->addOwner($this->community, $this->owner);
    }

    protected function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(MembershipService::class)->join($this->community, $user);

        return $user->fresh();
    }

    protected function established(array $attributes = []): User
    {
        return $this->member(['created_at' => now()->subMonth(), ...$attributes]);
    }

    protected function makePost(User $author, string $title = 'Hello', string $body = 'Welcome'): Post
    {
        return Post::forceCreate(['community_id' => $this->community->id, 'user_id' => $author->id, 'title' => $title, 'body' => $body]);
    }

    public function test_spam_post_is_hidden_and_author_held_for_review(): void
    {
        Notification::fake();
        $spammer = $this->established();

        $this->actingAs($spammer)->post(route('posts.store', $this->community), [
            'title' => 'Earn fast', 'body' => 'Guaranteed profit, WhatsApp me now',
        ])->assertSessionHas('error');

        $this->assertSame(0, Post::count(), 'spam is hidden from everyone');
        $this->assertTrue(Post::withoutGlobalScopes()->first()->is_spam);
        $this->assertTrue($spammer->fresh()->isRestricted());
        Notification::assertSentTo($this->owner, ModerationAlert::class);

        // Held users can't post again until an admin acts.
        $this->actingAs($spammer->fresh())->post(route('posts.store', $this->community), ['title' => 'Hi', 'body' => 'Normal'])->assertForbidden();
    }

    public function test_new_accounts_cannot_post_links_but_established_can(): void
    {
        $newbie = $this->member();
        $this->actingAs($newbie)->post(route('posts.store', $this->community), ['title' => 'Look', 'body' => 'see https://example.com']);
        $this->assertTrue($newbie->fresh()->isRestricted());

        $regular = $this->established();
        $this->actingAs($regular)->post(route('posts.store', $this->community), ['title' => 'Look', 'body' => 'see https://example.com']);
        $this->assertFalse($regular->fresh()->isRestricted());
        $this->assertSame(1, Post::count());
    }

    public function test_spam_comment_is_hidden(): void
    {
        $post = $this->makePost($this->owner);
        $spammer = $this->established();

        $this->actingAs($spammer)->post(route('comments.store', [$this->community, $post]), ['body' => 'crypto signals here']);

        $this->assertSame(0, Comment::count());
        $this->assertSame(0, $post->fresh()->comments_count);
        $this->assertTrue($spammer->fresh()->isRestricted());
    }

    public function test_admin_can_release_ban_and_delete(): void
    {
        $spammer = $this->established();
        $this->actingAs($spammer)->post(route('posts.store', $this->community), ['title' => 'x', 'body' => 'casino bonus']);

        $this->actingAs($this->owner)->get(route('moderation.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/moderation')->has('users.data', 1));

        // Not spam: content comes back and they can post again.
        $this->actingAs($this->owner)->post(route('moderation.release', $spammer));
        $this->assertFalse($spammer->fresh()->isRestricted());
        $this->assertSame(1, Post::count());

        // Ban: content hidden, signed out on their next request.
        $this->actingAs($this->owner)->post(route('moderation.ban', $spammer));
        $this->assertSame(0, Post::count());
        $this->actingAs($spammer->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Delete: account and content are gone.
        $this->actingAs($this->owner)->delete(route('moderation.destroy', $spammer));
        $this->assertNull(User::find($spammer->id));
        $this->assertSame(0, Post::withoutGlobalScopes()->count());
    }

    public function test_only_platform_admins_can_moderate(): void
    {
        $member = $this->established();
        $other = $this->established();

        $this->actingAs($member)->get(route('moderation.index'))->assertForbidden();
        $this->actingAs($member)->post(route('moderation.ban', $other))->assertForbidden();
        $this->actingAs($member)->delete(route('moderation.destroy', $other))->assertForbidden();
        $this->assertNotNull(User::find($other->id));
    }

    public function test_three_reports_put_author_under_review(): void
    {
        $author = $this->established();
        $post = $this->makePost($author, 'Buy', 'stuff');

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->established())->post(route('report', ['post', $post->id]))->assertSessionHas('success');
        }
        $this->assertFalse($author->fresh()->isRestricted());

        $this->actingAs($this->established())->post(route('report', ['post', $post->id]));
        $this->assertTrue($author->fresh()->isRestricted());
        $this->assertSame(0, Post::count());
    }

    /**
     * A member of the community team (can chat with members).
     */
    protected function moderator(array $attributes = []): User
    {
        $user = $this->established($attributes);
        $this->community->membershipFor($user)->update(['role' => CommunityMember::ROLE_MODERATOR]);

        return $user;
    }

    public function test_members_chat_with_the_team_but_not_each_other(): void
    {
        $alice = $this->established();
        $bob = $this->established();
        $mod = $this->moderator();

        $this->actingAs($alice)->post(route('chat.store', $bob->username), ['body' => 'Hi Bob!'])->assertSessionHas('error');
        $this->actingAs($alice)->post(route('chat.store', $this->owner->username), ['body' => 'Question for the admin'])->assertSessionHasNoErrors();
        $this->actingAs($alice)->post(route('chat.store', $mod->username), ['body' => 'Question for the mod'])->assertSessionHasNoErrors();
        $this->actingAs($mod)->post(route('chat.store', $bob->username), ['body' => 'Welcome Bob!'])->assertSessionHasNoErrors();

        $this->assertSame(3, Message::count());
        $this->assertSame(1, Conversation::unreadFor($bob)->count());

        $this->actingAs($bob)->get(route('chat.show', $mod->username))
            ->assertInertia(fn (Assert $page) => $page->component('chat/index')->has('active.messages', 1)->has('conversations', 1));
        $this->assertSame(0, Conversation::unreadFor($bob)->count());

        // The people search and profile button follow the same rule.
        $this->actingAs($alice)->get(route('chat.index', ['q' => $bob->name]))->assertInertia(fn (Assert $page) => $page->has('people', 0));
        $this->actingAs($alice)->get(route('chat.index', ['q' => $mod->name]))->assertInertia(fn (Assert $page) => $page->has('people', 1));
        $this->actingAs($alice)->get(route('users.show', $bob->username))->assertInertia(fn (Assert $page) => $page->where('profile.can_message', false));
        $this->actingAs($alice)->get(route('users.show', $mod->username))->assertInertia(fn (Assert $page) => $page->where('profile.can_message', true));
    }

    public function test_strangers_blocked_and_restricted_users_cannot_message(): void
    {
        $alice = $this->moderator();
        $stranger = User::factory()->create(['created_at' => now()->subMonth()]);

        $this->actingAs($stranger)->post(route('chat.store', $alice->username), ['body' => 'hey'])->assertSessionHas('error');

        $bob = $this->established();
        $this->actingAs($alice)->post(route('chat.block', $bob->username));
        $this->actingAs($bob)->post(route('chat.store', $alice->username), ['body' => 'hey'])->assertSessionHas('error');

        $carol = $this->established();
        $carol->forceFill(['restricted_at' => now()])->save();
        $this->actingAs($carol)->post(route('chat.store', $alice->username), ['body' => 'hey'])->assertSessionHas('error');

        $this->assertSame(0, Message::count());
    }

    public function test_copy_paste_dm_blast_is_caught(): void
    {
        // e.g. a hijacked team account blasting members.
        $bot = $this->moderator();
        $targets = collect(range(1, 3))->map(fn () => $this->established());

        foreach ($targets as $target) {
            $this->actingAs($bot)->post(route('chat.store', $target->username), ['body' => 'Hey friend, check my new course!']);
        }

        $this->assertTrue($bot->fresh()->isRestricted());
        // The third copy trips the filter, and the copies already sent are pulled back too.
        $this->assertSame(3, Message::where('is_spam', true)->count());
        $targets->each(fn (User $target) => $this->assertSame(0, Conversation::unreadFor($target)->count()));
        $this->actingAs($targets->first())->get(route('chat.index'))
            ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
    }

    public function test_new_accounts_have_a_daily_new_chat_limit(): void
    {
        config(['spam.new_conversations_per_day' => [2, 30]]);
        $newbie = $this->member();
        $this->community->membershipFor($newbie)->update(['role' => CommunityMember::ROLE_MODERATOR]);

        foreach (range(1, 3) as $i) {
            $this->actingAs($newbie)->post(route('chat.store', $this->established()->username), ['body' => "hello number {$i}"]);
        }

        $this->assertSame(2, Message::count());
    }

    public function test_community_search_finds_posts_and_members(): void
    {
        $jane = $this->member(['name' => 'Jane Searchable']);
        $this->makePost($jane, 'Pricing tips', 'Charge more');
        $this->makePost($jane, 'Other', 'Unrelated');

        $this->get(route('communities.search', [$this->community, 'q' => 'pricing']))
            ->assertInertia(fn (Assert $page) => $page->component('communities/search')->has('posts', 1)->has('members', 0));

        $this->get(route('communities.search', [$this->community, 'q' => 'searchable']))
            ->assertInertia(fn (Assert $page) => $page->has('members', 1)->where('members.0.name', 'Jane Searchable'));
    }
}
