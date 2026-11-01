<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $owner = User::factory()->create();
        $owner->forceFill(['is_admin' => true])->save();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($owner)->save();
        app(MembershipService::class)->addOwner($this->community, $owner);

        $this->member = User::factory()->create(['created_at' => now()->subMonth()]);
        app(MembershipService::class)->join($this->community, $this->member);
    }

    /**
     * A real 1×1 image (no GD needed), optionally padded past the size limit.
     */
    protected function image(string $name, int $padKb = 0): UploadedFile
    {
        $bytes = str_ends_with($name, '.gif')
            ? base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
            : base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $bytes.str_repeat("\0", $padKb * 1024));
    }

    protected function postWith(array $files)
    {
        return $this->actingAs($this->member)->post(route('posts.store', $this->community), [
            'title' => 'Look', 'body' => 'My setup', 'attachments' => $files,
        ]);
    }

    public function test_uploads_are_refused_unless_the_owner_allows_them(): void
    {
        $this->postWith([$this->image('a.png')])->assertSessionHasErrors('attachments');
        $this->assertSame(0, Post::count());
    }

    public function test_photos_are_stored_when_allowed(): void
    {
        $this->community->update(['allow_images' => true]);

        $this->postWith([$this->image('a.png'), $this->image('b.png')])->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertCount(2, $post->attachments);
        Storage::disk('public')->assertExists($post->attachments);
    }

    public function test_gifs_need_their_own_switch(): void
    {
        $this->community->update(['allow_images' => true]);
        $this->postWith([$this->image('fun.gif')])->assertSessionHasErrors('attachments.0');

        $this->community->update(['allow_gifs' => true]);
        $this->postWith([$this->image('fun.gif')])->assertSessionHasNoErrors();
        $this->assertTrue(Post::firstOrFail()->attachmentList()[0]['gif']);
    }

    public function test_files_over_1mb_and_more_than_4_are_rejected(): void
    {
        $this->community->update(['allow_images' => true]);

        $this->postWith([$this->image('big.png', 1100)])->assertSessionHasErrors('attachments.0');
        $this->postWith(array_map(fn ($i) => $this->image("{$i}.png"), range(1, 5)))->assertSessionHasErrors('attachments');
        $this->assertSame(0, Post::count());
    }

    public function test_non_images_are_rejected(): void
    {
        $this->community->update(['allow_images' => true]);

        $this->postWith([UploadedFile::fake()->createWithContent('shell.png', '<?php echo 1;')])->assertSessionHasErrors('attachments.0');
    }

    public function test_editing_can_remove_and_add_attachments(): void
    {
        $this->community->update(['allow_images' => true]);
        $this->postWith([$this->image('a.png'), $this->image('b.png')]);
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
}
