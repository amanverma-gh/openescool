<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Digest;
use App\Notifications\LevelUp;
use App\Notifications\NewComment;
use App\Services\LikeService;
use App\Services\MembershipService;
use App\Support\Unsubscribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigestTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->owner)->save();

        $memberships = app(MembershipService::class);
        $memberships->addOwner($this->community, $this->owner);
        $this->member = User::factory()->create();
        $memberships->join($this->community, $this->member);
    }

    protected function makePost(User $author, array $attributes = []): Post
    {
        return $this->community->posts()->create(array_merge(['user_id' => $author->id, 'title' => 'Hello', 'body' => 'Body text'], $attributes));
    }

    public function test_member_updates_email_preferences(): void
    {
        $this->actingAs($this->member)->get('/settings/notifications')
            ->assertInertia(fn (Assert $page) => $page->component('settings/notifications')->where('digest', 'weekly')->where('preferences.comments', true));

        $this->patch('/settings/notifications', ['preferences' => ['comments' => false, 'mentions' => true], 'digest' => 'daily'])
            ->assertSessionHasNoErrors();

        $user = $this->member->fresh();
        $this->assertFalse($user->wantsEmail('comments'));
        $this->assertTrue($user->wantsEmail('events'));
        $this->assertSame('daily', $user->digest_frequency);

        $comment = $this->makePost($this->owner)->comments()->create(['user_id' => $this->owner->id, 'body' => 'Hi']);
        $this->assertSame(['database'], (new NewComment($comment))->via($user));
        $this->assertSame(['database', 'mail'], (new LevelUp($this->community, 2))->via($user));
    }

    public function test_unsubscribe_links_are_signed_and_work_without_login(): void
    {
        $url = Unsubscribe::url($this->member, 'digest');

        $this->get("/email/unsubscribe/{$this->member->id}/digest")->assertForbidden();
        $this->get(str_replace('digest?', 'all?', $url))->assertForbidden(); // tampered

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('unsubscribe')->where('done', false));
        $this->assertSame('weekly', $this->member->fresh()->digest_frequency);

        // One-click POST from a mail client: no session, no CSRF token.
        $this->post($url)->assertOk();
        $this->assertSame('off', $this->member->fresh()->digest_frequency);

        $this->post(Unsubscribe::url($this->member, 'all'))->assertOk();
        $this->assertFalse($this->member->fresh()->wantsEmail('mentions'));
    }

    public function test_weekly_digest_sends_popular_posts_once(): void
    {
        $this->makePost($this->owner, ['title' => 'Popular', 'likes_count' => 0])->forceFill(['likes_count' => 9])->save();
        $this->makePost($this->owner, ['title' => 'Quiet']);
        $this->makePost($this->member, ['title' => 'My own post']);
        $this->makePost($this->owner, ['title' => 'Too old'])->forceFill(['created_at' => now()->subDays(10)])->save();

        $quiet = User::factory()->create(['digest_frequency' => 'off']);
        app(MembershipService::class)->join($this->community, $quiet);

        $this->artisan('app:send-digests weekly')->assertSuccessful();

        Notification::assertSentTo($this->member, Digest::class, function (Digest $digest) {
            $titles = array_column($digest->sections[0]['posts'], 'title');

            return $titles === ['Popular', 'Quiet'] && $digest->sections[0]['new_posts'] === 2;
        });
        Notification::assertNotSentTo($quiet, Digest::class);

        $mail = (new Digest('weekly', [['community' => ['name' => 'Test', 'url' => 'http://x'], 'new_posts' => 1, 'posts' => [], 'events' => []]]))
            ->toMail($this->member);
        $this->assertStringContainsString('Unsubscribe from digests', (string) $mail->render());

        // Running again right away sends nothing new.
        $this->artisan('app:send-digests weekly')->assertSuccessful();
        Notification::assertSentToTimes($this->member, Digest::class, 1);
    }

    public function test_no_digest_when_nothing_happened(): void
    {
        $this->artisan('app:send-digests weekly')->assertSuccessful();

        Notification::assertNothingSentTo($this->member);
    }

    public function test_level_up_is_celebrated_once(): void
    {
        $this->community->members()->where('user_id', $this->member->id)->update(['points' => 4]); // 5 = level 2
        $post = $this->makePost($this->member);
        $likes = app(LikeService::class);

        $likes->toggle($this->owner, $post);
        Notification::assertSentTo($this->member, LevelUp::class, fn (LevelUp $n) => $n->level === 2);

        $likes->toggle($this->owner, $post); // unlike
        $likes->toggle($this->owner, $post); // like again
        Notification::assertSentToTimes($this->member, LevelUp::class, 1);
    }

    public function test_owner_names_levels(): void
    {
        $names = array_fill(0, 9, '');
        $names[0] = 'Rookie';
        $names[2] = ' Builder ';

        $this->actingAs($this->owner)->post('/c/test/settings', [
            'name' => 'Test', 'privacy' => 'public', 'level_names' => $names,
        ])->assertSessionHasNoErrors();

        $community = $this->community->fresh();
        $this->assertSame('Level 3 (Builder)', $community->levelLabel(3));
        $this->assertSame('Level 2', $community->levelLabel(2));

        $this->post('/c/test/settings', ['name' => 'Test', 'privacy' => 'public', 'level_names' => array_fill(0, 9, '')]);
        $this->assertNull($this->community->fresh()->level_names);
    }
}
