<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->owner = User::factory()->create();
        $this->owner->forceFill(['is_admin' => true])->save();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->owner)->save();
        app(MembershipService::class)->addOwner($this->community, $this->owner);

        $this->member = User::factory()->create(['created_at' => now()->subMonth()]);
        app(MembershipService::class)->join($this->community, $this->member);
    }

    /**
     * A real 1×1 PNG (no GD needed), optionally padded to a given size.
     */
    protected function image(string $name = 'a.png', int $padKb = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png.str_repeat("\0", $padKb * 1024));
    }

    protected function postWith(array $fields)
    {
        return $this->actingAs($this->member)->post(route('posts.store', $this->community), ['title' => 'Look', 'body' => 'My setup', ...$fields]);
    }

    protected function makePost(): Post
    {
        return Post::forceCreate(['community_id' => $this->community->id, 'user_id' => $this->owner->id, 'title' => 'Hi', 'body' => 'Hello']);
    }

    public function test_everything_is_off_by_default(): void
    {
        $this->community->refresh();
        $this->assertFalse($this->community->allow_images);
        $this->assertFalse($this->community->allow_gifs);
        $this->assertFalse($this->community->allow_comment_media);

        $this->postWith(['attachments' => [$this->image()]])->assertSessionHasErrors('attachments');
        $this->postWith(['gif_id' => 'abc123XYZ'])->assertSessionHasErrors('gif_id');
        $this->assertSame(0, Post::count());
    }

    public function test_photos_are_stored_when_allowed(): void
    {
        $this->community->update(['allow_images' => true]);

        $this->postWith(['attachments' => [$this->image('a.png'), $this->image('b.png')]])->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertCount(2, $post->attachments);
        Storage::disk('public')->assertExists($post->attachments);
    }

    public function test_platform_admin_sets_how_many_items_and_how_big(): void
    {
        $this->community->update(['allow_images' => true]);
        $this->actingAs($this->owner)->post(route('admin.settings.update'), ['max_images' => 1, 'max_upload_mb' => 0.5])->assertSessionHasNoErrors();
        $this->assertSame(1, AppSetting::maxImages());
        $this->assertSame(512, AppSetting::maxUploadKb());

        $this->postWith(['attachments' => [$this->image('a.png'), $this->image('b.png')]])->assertSessionHasErrors('attachments');
        $this->postWith(['attachments' => [$this->image('big.png', 600)]])->assertSessionHasErrors('attachments.0');
        $this->postWith(['attachments' => [$this->image('ok.png', 400)]])->assertSessionHasNoErrors();
        $this->assertSame(1, Post::count());
    }

    public function test_photo_size_limit_cannot_go_above_one_mb(): void
    {
        $this->assertSame(4, AppSetting::maxImages());
        $this->assertSame(1024, AppSetting::maxUploadKb()); // default 1 MB

        $this->actingAs($this->owner)->post(route('admin.settings.update'), ['max_images' => 5, 'max_upload_mb' => 1.5])
            ->assertSessionHasErrors(['max_images', 'max_upload_mb']);
        $this->assertSame(1024, AppSetting::maxUploadKb());

        $this->community->update(['allow_images' => true]);
        $this->postWith(['attachments' => [$this->image('big.png', 1100)]])->assertSessionHasErrors('attachments.0');

        // Only platform admins can change the limits.
        $this->actingAs($this->member)->post(route('admin.settings.update'), ['max_upload_mb' => 0.2])->assertForbidden();
    }

    public function test_community_settings_save_media_switches(): void
    {
        $this->actingAs($this->owner)->post(route('communities.settings.update', $this->community), [
            'name' => 'Test', 'privacy' => 'public',
            'allow_images' => true, 'allow_gifs' => true, 'allow_comment_media' => true,
        ])->assertSessionHasNoErrors();

        $this->community->refresh();
        $this->assertTrue($this->community->allow_images);
        $this->assertTrue($this->community->allow_comment_media);
    }

    public function test_gif_files_are_no_longer_uploaded_but_giphy_gifs_work(): void
    {
        $this->community->update(['allow_images' => true, 'allow_gifs' => true]);
        $gif = UploadedFile::fake()->createWithContent('fun.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $this->postWith(['attachments' => [$gif]])->assertSessionHasErrors('attachments.0');
        $this->postWith(['gif_id' => '../../etc'])->assertSessionHasErrors('gif_id');

        $this->postWith(['gif_id' => 'xT9IgG50Fb7Mi0prBC'])->assertSessionHasNoErrors();
        $this->assertSame([[
            'path' => 'giphy:xT9IgG50Fb7Mi0prBC',
            'url' => 'https://media.giphy.com/media/xT9IgG50Fb7Mi0prBC/giphy.gif',
            'gif' => true,
        ]], Post::firstOrFail()->attachmentList());
    }

    public function test_non_images_are_rejected(): void
    {
        $this->community->update(['allow_images' => true]);

        $this->postWith(['attachments' => [UploadedFile::fake()->createWithContent('shell.png', '<?php echo 1;')]])->assertSessionHasErrors('attachments.0');
    }

    public function test_editing_can_remove_and_add_attachments(): void
    {
        $this->community->update(['allow_images' => true]);
        $this->postWith(['attachments' => [$this->image('a.png'), $this->image('b.png')]]);
        $post = Post::firstOrFail();
        [$keep, $drop] = $post->attachments;

        $this->actingAs($this->member)->post(route('posts.update', [$this->community, $post]), [
            '_method' => 'patch',
            'title' => 'Look', 'body' => 'Updated',
            'keep_attachments' => [$keep],
            'attachments' => [$this->image('c.png')],
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertCount(2, $post->attachments);
        $this->assertSame($keep, $post->attachments[0]);
        Storage::disk('public')->assertMissing($drop);
    }

    public function test_comments_take_one_photo_or_gif_only_when_allowed(): void
    {
        $post = $this->makePost();
        $this->community->update(['allow_images' => true, 'allow_gifs' => true]);

        // Posts allow media, but comments are a separate switch.
        $this->actingAs($this->member)->post(route('comments.store', [$this->community, $post]), ['body' => 'Nice', 'image' => $this->image()])
            ->assertSessionHasErrors('image');

        $this->community->update(['allow_comment_media' => true]);

        $this->actingAs($this->member)->post(route('comments.store', [$this->community, $post]), [
            'body' => 'Both', 'image' => $this->image(), 'gif_id' => 'xT9IgG50Fb7Mi0prBC',
        ])->assertSessionHasErrors('image');

        $this->actingAs($this->member)->post(route('comments.store', [$this->community, $post]), ['body' => 'Photo', 'image' => $this->image()])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->member)->post(route('comments.store', [$this->community, $post]), ['body' => 'Gif', 'gif_id' => 'xT9IgG50Fb7Mi0prBC'])
            ->assertSessionHasNoErrors();

        [$photo, $gif] = Comment::oldest('id')->get();
        Storage::disk('public')->assertExists($photo->attachment);
        $this->assertSame('giphy:xT9IgG50Fb7Mi0prBC', $gif->attachment);

        // Removing it on edit deletes the file.
        $this->actingAs($this->member)->patch(route('comments.update', [$this->community, $photo]), ['body' => 'Photo', 'remove_attachment' => true]);
        $this->assertNull($photo->fresh()->attachment);
        Storage::disk('public')->assertMissing($photo->attachment);
    }

    public function test_giphy_key_is_set_in_the_app_and_stored_encrypted(): void
    {
        Http::fake(['api.giphy.com/*' => Http::response(['data' => []])]);

        $this->actingAs($this->member)->post(route('admin.settings.update'), ['giphy_api_key' => 'abc123'])->assertForbidden();

        $this->actingAs($this->owner)->post(route('admin.settings.update'), ['giphy_api_key' => 'SecretKey123'])->assertSessionHasNoErrors();

        $this->assertSame('SecretKey123', AppSetting::get('giphy_api_key'));
        $this->assertStringNotContainsString('SecretKey123', AppSetting::find('giphy_api_key')->getRawOriginal('value'));
    }

    public function test_gif_search_is_proxied_with_pg13_rating(): void
    {
        $this->actingAs($this->member)->getJson(route('giphy.search', ['q' => 'cat']))->assertStatus(503);

        AppSetting::put('giphy_api_key', 'SecretKey123');
        Http::fake(['api.giphy.com/*' => Http::response(['data' => [
            ['id' => 'abcde12345', 'title' => 'Cat', 'images' => ['fixed_width_downsampled' => ['url' => 'https://media.giphy.com/x.gif']]],
        ]])]);

        $this->actingAs($this->member)->getJson(route('giphy.search', ['q' => 'cat']))
            ->assertOk()
            ->assertJsonPath('data.0.id', 'abcde12345')
            ->assertDontSee('SecretKey123');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'rating=pg-13') && str_contains($request->url(), 'q=cat'));
    }
}
