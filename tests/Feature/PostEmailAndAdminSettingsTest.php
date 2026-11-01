<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostBroadcast;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostEmailAndAdminSettingsTest extends TestCase
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

    protected function member(): User
    {
        $user = User::factory()->create(['created_at' => now()->subMonth()]);
        app(MembershipService::class)->join($this->community, $user);

        return $user;
    }

    protected function postAs(User $user, array $extra = [])
    {
        return $this->actingAs($user)->post(route('posts.store', $this->community), ['title' => 'Big news', 'body' => 'Live call on Friday', ...$extra]);
    }

    public function test_owner_can_email_a_post_to_all_members(): void
    {
        Notification::fake();
        $alice = $this->member();
        $bob = $this->member();
        $bob->forceFill(['email_preferences' => ['announcements' => false]])->save();
        $outsider = User::factory()->create();

        $this->postAs($this->owner, ['email_members' => true])->assertSessionHas('success');

        $this->assertNotNull(Post::firstOrFail()->emailed_at);
        Notification::assertSentTo($alice, PostBroadcast::class, fn ($n, array $channels) => in_array('mail', $channels));
        // Opted out of post emails: in-app only.
        Notification::assertSentTo($bob, PostBroadcast::class, fn ($n, array $channels) => $channels === ['database']);
        Notification::assertNotSentTo([$outsider, $this->owner], PostBroadcast::class);
    }

    public function test_only_owners_and_admins_can_email_and_only_once_per_day(): void
    {
        Notification::fake();
        $member = $this->member();

        $this->postAs($member, ['email_members' => true]);
        $this->assertNull(Post::firstOrFail()->emailed_at);
        Notification::assertNothingSent();

        $this->postAs($this->owner, ['email_members' => true]);
        $this->postAs($this->owner, ['email_members' => true])->assertSessionHas('error');
        $this->assertSame(1, Post::whereNotNull('emailed_at')->count());

        $this->travel(Post::EMAIL_COOLDOWN_HOURS + 1)->hours();
        $this->postAs($this->owner, ['email_members' => true])->assertSessionHas('success');
        $this->assertSame(2, Post::whereNotNull('emailed_at')->count());
    }

    public function test_community_admins_can_email_too(): void
    {
        Notification::fake();
        $admin = $this->member();
        $this->community->membershipFor($admin)->update(['role' => CommunityMember::ROLE_ADMIN]);
        $this->member();

        $this->postAs($admin, ['email_members' => true]);

        Notification::assertSentTimes(PostBroadcast::class, 2); // the owner and the other member
    }

    public function test_brevo_key_is_verified_and_stored_encrypted(): void
    {
        Http::fake([
            'api.brevo.com/v3/account' => Http::sequence()->push(['message' => 'Key not found'], 401)->push(['email' => 'me@example.com']),
            'api.brevo.com/v3/contacts*' => Http::response([], 201),
        ]);
        Http::preventStrayRequests();

        $this->actingAs($this->owner)->post(route('admin.settings.update'), ['brevo_api_key' => 'wrong-key'])->assertSessionHasErrors('brevo_api_key');
        $this->assertNull(AppSetting::get('brevo_api_key'));

        $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'brevo_api_key' => 'xkeysib-good',
            'mail_from_address' => 'hello@mycommunity.com',
            'mail_from_name' => 'My Community',
            'brevo_members_list_id' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame('xkeysib-good', AppSetting::get('brevo_api_key'));
        $this->assertStringNotContainsString('xkeysib-good', AppSetting::find('brevo_api_key')->getRawOriginal('value'));

        AppSetting::applyToConfig();
        $this->assertSame('brevo', config('mail.default'));
        $this->assertSame('hello@mycommunity.com', config('mail.from.address'));
        $this->assertSame('3', (string) config('services.brevo.members_list_id'));

        // Members can't see or change it.
        $member = $this->member();
        $this->actingAs($member)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.settings.test-email'))->assertForbidden();
    }

    public function test_brevo_transport_sends_through_the_api(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<abc@brevo>'], 201)]);
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'xkeysib-test', 'mail.from.address' => 'hello@mycommunity.com', 'mail.from.name' => 'My Community']);

        Mail::raw('Hello there', fn ($m) => $m->to('jane@example.com', 'Jane')->subject('Hi Jane'));

        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'xkeysib-test')
            && $request['sender'] === ['email' => 'hello@mycommunity.com', 'name' => 'My Community']
            && $request['to'] === [['email' => 'jane@example.com', 'name' => 'Jane']]
            && $request['subject'] === 'Hi Jane'
            && str_contains($request['textContent'], 'Hello there'));
    }

    public function test_test_email_button_works_for_admins(): void
    {
        config(['mail.default' => 'array']);

        $this->actingAs($this->owner)->post(route('admin.settings.test-email'))->assertSessionHas('success');
    }
}
