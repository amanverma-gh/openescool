<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->admin)->save();
        app(MembershipService::class)->addOwner($this->community, $this->admin);
        $this->community->refresh();
    }

    public function test_first_person_to_sign_up_becomes_admin(): void
    {
        // Nobody is an admin yet, as on a fresh install.
        $this->admin->forceFill(['is_admin' => false])->save();

        $register = fn (string $email) => $this->post(route('register'), [
            'name' => 'Person', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123',
            'form_token' => Crypt::encryptString((string) now()->subMinute()->timestamp),
        ]);

        $register('first@example.com');
        $this->assertTrue(User::where('email', 'first@example.com')->value('is_admin'));

        auth()->logout();
        $register('second@example.com');
        $this->assertFalse((bool) User::where('email', 'second@example.com')->value('is_admin'));
    }

    public function test_admin_sets_site_name_and_logo(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.site'), [
            'site_name' => 'Koko', 'site_logo' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Koko', AppSetting::get('site_name'));
        Storage::disk('public')->assertExists(AppSetting::get('site_logo'));

        AppSetting::applyToConfig();
        $this->get(route('discover'))->assertInertia(fn (Assert $page) => $page->where('name', 'Koko')->where('logo', AppSetting::logoUrl()));

        $old = AppSetting::get('site_logo');
        $this->actingAs($this->admin)->post(route('admin.settings.site'), ['remove_logo' => true])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $this->assertNull(AppSetting::logoUrl());

        $member = User::factory()->create();
        $this->actingAs($member)->post(route('admin.settings.site'), ['site_name' => 'Hack'])->assertForbidden();
    }

    public function test_home_page_can_open_a_default_community(): void
    {
        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('communities/index'));

        $this->actingAs($this->admin)->post(route('admin.settings.site'), ['home_mode' => 'community'])
            ->assertSessionHasErrors('home_community_id');
        $this->actingAs($this->admin)->post(route('admin.settings.site'), ['home_mode' => 'community', 'home_community_id' => $this->community->id])
            ->assertSessionHasNoErrors();

        auth()->logout();
        $this->get(route('home'))->assertRedirect(route('communities.about', $this->community));
        $this->actingAs($this->admin)->get(route('home'))->assertRedirect(route('communities.feed', $this->community));
        // Discover stays reachable.
        $this->get(route('discover'))->assertOk();
    }

    public function test_discover_page_text_and_communities_are_customizable(): void
    {
        $other = new Community(['name' => 'Other', 'slug' => 'other', 'privacy' => Community::PRIVACY_PUBLIC]);
        $other->owner()->associate($this->admin)->save();

        $this->actingAs($this->admin)->post(route('admin.settings.site'), [
            'home_headline' => 'Welcome to Koko', 'home_button' => 'Join free', 'home_communities_title' => 'Our groups',
            'home_community_ids' => [$other->id],
        ])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('hero.headline', 'Welcome to Koko')
            ->where('hero.button', 'Join free')
            ->where('hero.communities_title', 'Our groups')
            ->has('discover', 1)
            ->where('discover.0.slug', 'other'));

        $this->actingAs($this->admin)->post(route('admin.settings.site'), ['home_show_communities' => false, 'home_headline' => '']);
        auth()->logout();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('discover', 0)->where('hero.headline', 'Learn and grow, together.'));
    }

    public function test_seo_settings_control_robots_and_sitemap(): void
    {
        $post = Post::forceCreate(['community_id' => $this->community->id, 'user_id' => $this->admin->id, 'title' => 'Hello', 'body' => 'Hi']);

        $this->get(route('communities.about', $this->community))->assertSee('content="index, follow"', false);
        $this->get(route('posts.show', [$this->community, $post]))->assertSee('content="index, follow"', false);
        $this->get(route('sitemap'))->assertSee(route('posts.show', [$this->community, $post]), false);
        $this->get(route('login'))->assertSee('content="noindex, nofollow"', false);

        // Community keeps its pages indexed but not its posts.
        $this->actingAs($this->admin)->post(route('communities.settings.update', $this->community), [
            'name' => 'Test', 'privacy' => 'public', 'seo_index' => true, 'seo_index_posts' => false,
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('posts.show', [$this->community, $post]))->assertSee('content="noindex, nofollow"', false);
        $this->get(route('communities.about', $this->community))->assertSee('content="index, follow"', false);
        $this->get(route('sitemap'))->assertDontSee(route('posts.show', [$this->community, $post]), false)
            ->assertSee(route('communities.about', $this->community), false);

        // Whole site off.
        AppSetting::put('seo_index', '0');
        $this->get(route('communities.about', $this->community))->assertSee('content="noindex, nofollow"', false);
        $this->get(route('robots'))->assertSee('Disallow: /');
        $this->get(route('sitemap'))->assertDontSee(route('communities.about', $this->community), false);
    }

    public function test_admin_sees_available_update_and_one_click_needs_a_backup_confirmation(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.1.0', 'body' => 'New things', 'html_url' => 'https://github.com/x/y/releases/v1.1.0'])]);
        $this->actingAs($this->admin)->get(route('admin.settings'))->assertInertia(fn (Assert $page) => $page
            ->where('update.current', config('openescool.version'))
            ->where('update.latest', '1.1.0')
            ->where('update.available', true)
            ->where('helpUrl', 'https://openskoool.userjot.com/'));

        $this->actingAs($this->admin)->post(route('admin.update.run'), [])->assertSessionHasErrors('backup_confirmed');
        $this->actingAs($this->admin)->getJson(route('admin.update.status'))->assertOk()->assertJsonStructure(['running', 'ok', 'step', 'log']);

        $member = User::factory()->create();
        $this->actingAs($member)->post(route('admin.update.run'), ['backup_confirmed' => true])->assertForbidden();
        $this->actingAs($member)->getJson(route('admin.update.status'))->assertForbidden();
    }

    public function test_update_check_handles_github_being_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 404)]);
        $status = app(UpdateChecker::class)->status(fresh: true);

        $this->assertFalse($status['checked']);
        $this->assertFalse($status['available']);
    }
}
