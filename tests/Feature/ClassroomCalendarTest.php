<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Course;
use App\Models\Event;
use App\Models\PointEvent;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Services\LikeService;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassroomCalendarTest extends TestCase
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

    protected function makeCourse(array $attributes = []): Course
    {
        $course = $this->community->courses()->create(array_merge(['title' => 'Course', 'is_published' => true], $attributes));
        $module = $course->modules()->create(['title' => 'Module']);
        $module->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson 1']);
        $module->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson 2']);

        return $course;
    }

    public function test_admin_builds_a_course_and_member_tracks_progress(): void
    {
        $this->actingAs($this->owner)->post(route('courses.store', $this->community), [
            'title' => 'Masterclass', 'access' => 'open', 'is_published' => true,
        ])->assertSessionHasNoErrors();

        $course = $this->community->courses()->firstOrFail();
        $module = $course->modules()->firstOrFail();

        $this->actingAs($this->owner)->post(route('lessons.store', [$this->community, $module]), [
            'title' => 'Intro', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'resources' => [['label' => 'Slides', 'url' => 'https://example.com/slides.pdf']],
            'is_published' => true,
        ])->assertSessionHasNoErrors();
        $lesson = $course->lessons()->firstOrFail();

        // Members cannot edit.
        $this->actingAs($this->member)->patch(route('lessons.update', [$this->community, $lesson]), ['title' => 'x'])->assertForbidden();

        $this->actingAs($this->member)->post(route('lessons.complete', [$this->community, $lesson]))->assertRedirect();

        $this->actingAs($this->member)->get(route('classroom.index', $this->community))
            ->assertInertia(fn (Assert $page) => $page->where('courses.0.progress', 100)->where('courses.0.unlocked', true));
    }

    public function test_level_locked_and_draft_courses_are_protected(): void
    {
        $locked = $this->makeCourse(['access' => Course::ACCESS_LEVEL, 'unlock_level' => 3]);
        $draft = $this->makeCourse(['title' => 'Draft', 'is_published' => false]);

        $this->actingAs($this->member)->get(route('classroom.show', [$this->community, $locked]))
            ->assertRedirect(route('classroom.index', $this->community));
        $this->actingAs($this->member)->post(route('lessons.complete', [$this->community, $locked->lessons()->first()]))->assertForbidden();

        // Drafts are hidden from members, visible to admins.
        $this->actingAs($this->member)->get(route('classroom.index', $this->community))
            ->assertInertia(fn (Assert $page) => $page->has('courses', 1)->where('courses.0.unlocked', false));
        $this->actingAs($this->owner)->get(route('classroom.show', [$this->community, $draft]))->assertOk();

        // Reaching level 3 (20 points) unlocks it.
        $this->community->members()->where('user_id', $this->member->id)->update(['points' => 20]);
        $this->community->forgetMembership($this->member);
        $this->actingAs($this->member)->get(route('classroom.show', [$this->community, $locked]))->assertOk();
    }

    public function test_guests_cannot_open_courses(): void
    {
        $course = $this->makeCourse();

        $this->get(route('classroom.index', $this->community))->assertOk();
        $this->get(route('classroom.show', [$this->community, $course]))->assertRedirect(route('classroom.index', $this->community));
    }

    public function test_lessons_can_be_reordered(): void
    {
        $course = $this->makeCourse();
        [$first, $second] = $course->lessons()->orderBy('id')->get()->all();

        $this->actingAs($this->owner)->post(route('classroom.move', [$this->community, 'lesson', $second->id]), ['direction' => 'up']);

        $this->assertSame(
            [$second->id, $first->id],
            $course->lessons()->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_admin_schedules_weekly_event_in_local_timezone(): void
    {
        $this->actingAs($this->owner)->post(route('events.store', $this->community), [
            'title' => 'Weekly call',
            'date' => '2026-03-05',
            'time' => '19:00',
            'timezone' => 'Asia/Calcutta', // legacy alias some browsers still send
            'duration_minutes' => 60,
            'recurrence' => 'weekly',
            'location' => 'https://zoom.us/j/1',
        ])->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertSame('2026-03-05 13:30:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($this->member)->get(route('calendar.index', [$this->community, 'month' => '2026-03']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar/index')
                ->has('occurrences', 5) // Mar 5, 12, 19, 26 plus Apr 2 in the padding
                ->where('occurrences.0.location', 'https://zoom.us/j/1'));

        // Non-members (here: a guest) don't see the meeting link.
        $this->app['auth']->forgetGuards();
        $this->get(route('calendar.index', [$this->community, 'month' => '2026-03']))
            ->assertInertia(fn (Assert $page) => $page->where('occurrences.0.location', null));

        $this->actingAs($this->member)->post(route('events.store', $this->community), [])->assertForbidden();
    }

    public function test_weekly_events_keep_local_time_across_daylight_saving(): void
    {
        $event = new Event([
            'title' => 'NY call',
            'starts_at' => CarbonImmutable::parse('2026-03-05 19:00', 'America/New_York')->utc(),
            'timezone' => 'America/New_York',
            'recurrence' => Event::RECURRENCE_WEEKLY,
        ]);

        $times = $event->occurrencesBetween(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-20'))
            ->map(fn ($at) => $at->setTimezone('America/New_York')->format('Y-m-d H:i'))
            ->all();

        // DST starts on March 8 2026; the call stays at 7pm local.
        $this->assertSame(['2026-03-05 19:00', '2026-03-12 19:00', '2026-03-19 19:00'], $times);
    }

    public function test_ics_download_and_reminders_are_sent_once(): void
    {
        Notification::fake();
        $event = new Event(['title' => 'Soon', 'starts_at' => now()->addMinutes(30), 'location' => 'https://zoom.us/j/2']);
        $event->community()->associate($this->community);
        $event->host()->associate($this->owner);
        $event->save();

        $this->actingAs($this->member)->get(route('events.ics', [$this->community, $event]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Soon', false);

        $this->artisan('app:send-event-reminders')->assertSuccessful();
        $this->artisan('app:send-event-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->member, EventReminder::class, 1);
        Notification::assertSentToTimes($this->owner, EventReminder::class, 1);
    }

    public function test_leaderboard_windows_use_point_history(): void
    {
        $post = $this->community->posts()->create(['user_id' => $this->member->id, 'title' => 'Hi', 'body' => 'Hi']);
        app(LikeService::class)->toggle($this->owner, $post);

        // An old point should only count toward all-time.
        PointEvent::create(['community_id' => $this->community->id, 'user_id' => $this->owner->id, 'points' => 5])
            ->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($this->member)->get(route('leaderboards.index', $this->community))
            ->assertInertia(fn (Assert $page) => $page
                ->component('leaderboards/index')
                ->has('boards.week', 1)
                ->where('boards.week.0.id', $this->member->id)
                ->has('boards.month', 2)
                ->where('boards.month.0.id', $this->owner->id)
                ->where('me.points', 1));
    }
}
