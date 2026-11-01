<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonUploadsAndTeamTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected User $member;

    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->owner = User::factory()->create();
        $this->community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $this->community->owner()->associate($this->owner)->save();
        $memberships = app(MembershipService::class);
        $memberships->addOwner($this->community, $this->owner);
        $this->member = User::factory()->create(['created_at' => now()->subMonth()]);
        $memberships->join($this->community, $this->member);

        $course = $this->community->courses()->create(['title' => 'Course', 'is_published' => true]);
        $module = $course->modules()->create(['title' => 'Module']);
        $this->lesson = $module->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson 1', 'is_published' => true]);
    }

    protected function saveLesson(array $fields, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('lessons.update', [$this->community, $this->lesson]), [
            '_method' => 'patch', 'title' => 'Lesson 1', 'is_published' => true, ...$fields,
        ]);
    }

    public function test_admin_uploads_resource_files_and_members_download_them(): void
    {
        $this->saveLesson(['resources' => [
            ['label' => 'Workbook', 'file' => UploadedFile::fake()->create('workbook.pdf', 300, 'application/pdf')],
            ['label' => 'Site', 'url' => 'https://example.com'],
        ]])->assertSessionHasNoErrors();

        $resources = $this->lesson->fresh()->resources;
        $this->assertSame('workbook.pdf', $resources[0]['name']);
        Storage::disk('local')->assertExists($resources[0]['path']);
        $this->assertSame('https://example.com', $resources[1]['url']);

        $this->actingAs($this->member)->get(route('lessons.file', [$this->community, $this->lesson, 0]))
            ->assertOk()->assertDownload('workbook.pdf');

        // Visitors who haven't joined can't download.
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('lessons.file', [$this->community, $this->lesson, 0]))->assertForbidden();

        // Saving again with the path keeps the file; dropping it deletes it.
        $path = $resources[0]['path'];
        $this->saveLesson(['resources' => [['label' => 'Workbook v2', 'path' => $path]]])->assertSessionHasNoErrors();
        $this->assertSame('Workbook v2', $this->lesson->fresh()->resources[0]['label']);
        Storage::disk('local')->assertExists($path);

        $this->saveLesson(['resources' => []])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_resource_and_video_limits_come_from_admin_settings(): void
    {
        // Default is 1 MB.
        $this->saveLesson(['resources' => [['label' => 'Big', 'file' => UploadedFile::fake()->create('big.pdf', 1100, 'application/pdf')]]])
            ->assertSessionHasErrors('resources.0.file');
        $this->saveLesson(['video_file' => UploadedFile::fake()->create('intro.mp4', 1100, 'video/mp4')])->assertSessionHasErrors('video_file');

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->post(route('admin.settings.update'), ['max_resource_mb' => 5, 'max_video_mb' => 20])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.settings.update'), ['max_video_mb' => 500])->assertSessionHasErrors('max_video_mb');

        $this->saveLesson(['video_file' => UploadedFile::fake()->create('intro.mp4', 1100, 'video/mp4')])->assertSessionHasNoErrors();
        $video = $this->lesson->fresh()->video_path;
        Storage::disk('local')->assertExists($video);

        $this->actingAs($this->member)->get(route('classroom.show', [$this->community, $this->lesson->course]))
            ->assertInertia(fn (Assert $page) => $page->where('lesson.video_file_url', route('lessons.video', [$this->community, $this->lesson])));

        // Only allowed file types.
        $this->saveLesson(['resources' => [['label' => 'Script', 'file' => UploadedFile::fake()->create('run.exe', 10)]]])
            ->assertSessionHasErrors('resources.0.file');

        // Removing the video deletes the file, and so does deleting the lesson.
        $this->saveLesson(['remove_video' => true])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($video);

        $this->saveLesson(['resources' => [['label' => 'Notes', 'file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')]]]);
        $path = $this->lesson->fresh()->resources[0]['path'];
        $this->actingAs($this->owner)->delete(route('lessons.destroy', [$this->community, $this->lesson]))->assertRedirect();
        $this->assertNull(Lesson::find($this->lesson->id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_members_cannot_keep_files_from_other_lessons(): void
    {
        $other = Lesson::create(['course_id' => $this->lesson->course_id, 'course_module_id' => $this->lesson->course_module_id, 'title' => 'Other',
            'resources' => [['label' => 'Secret', 'path' => 'lessons/99/secret.pdf', 'name' => 'secret.pdf']]]);

        $this->saveLesson(['resources' => [['label' => 'Steal', 'path' => $other->resources[0]['path']]]])->assertSessionHasErrors('resources.0.file');
    }

    public function test_team_can_always_add_photos(): void
    {
        $this->assertFalse((bool) $this->community->fresh()->allow_images);
        $moderator = User::factory()->create(['created_at' => now()->subMonth()]);
        app(MembershipService::class)->join($this->community, $moderator);
        $this->community->membershipFor($moderator)->update(['role' => CommunityMember::ROLE_MODERATOR]);

        $png = UploadedFile::fake()->createWithContent('a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $post = fn (User $user) => $this->actingAs($user)->post(route('posts.store', $this->community), ['title' => 'Hi', 'body' => 'Hello', 'attachments' => [$png]]);

        $post($this->member)->assertSessionHasErrors('attachments');
        $post($moderator)->assertSessionHasNoErrors();
        $this->assertCount(1, Post::firstOrFail()->attachments);

        $this->actingAs($moderator)->get(route('communities.feed', $this->community))
            ->assertInertia(fn (Assert $page) => $page->where('community.media.images', true)->where('community.media.comments', true));
        $this->actingAs($this->member)->get(route('communities.feed', $this->community))
            ->assertInertia(fn (Assert $page) => $page->where('community.media.images', false));
    }

    public function test_owner_adds_a_moderator_from_the_team_section(): void
    {
        $this->actingAs($this->owner)->getJson(route('members.search', [$this->community, 'q' => $this->member->username]))
            ->assertOk()->assertJsonPath('0.username', $this->member->username);
        $this->actingAs($this->member)->getJson(route('members.search', [$this->community, 'q' => 'a']))->assertForbidden();

        $membership = $this->community->membershipFor($this->member);
        $this->actingAs($this->owner)->patch(route('members.update', [$this->community, $membership]), ['role' => 'moderator'])
            ->assertSessionHasNoErrors();
        $this->assertSame('moderator', $membership->fresh()->role);

        $this->actingAs($this->owner)->get(route('communities.settings', $this->community))
            ->assertInertia(fn (Assert $page) => $page->has('team', 2)->where('team.1.role', 'moderator')->where('canManageAdmins', true));

        // Moderators can't change roles.
        $another = User::factory()->create();
        app(MembershipService::class)->join($this->community, $another);
        $this->actingAs($this->member)->patch(route('members.update', [$this->community, $this->community->membershipFor($another)]), ['role' => 'moderator'])
            ->assertForbidden();
    }

    public function test_gif_switch_only_shows_with_a_giphy_key(): void
    {
        $this->actingAs($this->owner)->get(route('communities.settings', $this->community))
            ->assertInertia(fn (Assert $page) => $page->where('giphyReady', false));

        AppSetting::put('giphy_api_key', 'abc123');
        $this->actingAs($this->owner)->get(route('communities.settings', $this->community))
            ->assertInertia(fn (Assert $page) => $page->where('giphyReady', true));
    }
}
