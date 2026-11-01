<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\Event;
use App\Models\PointEvent;
use App\Models\Post;
use App\Models\User;
use App\Services\LikeService;
use App\Services\MembershipService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local demo data: an admin, a community, members and some activity.
     * Log in as admin@example.com / password.
     */
    public function run(MembershipService $memberships, LikeService $likes): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $community = new Community([
            'name' => 'Creator Academy',
            'slug' => 'creator-academy',
            'tagline' => 'Learn to build and grow an online business together.',
            'about' => "Welcome to Creator Academy!\n\nInside you'll find weekly calls, a step-by-step classroom and a community of people building alongside you.",
            'privacy' => Community::PRIVACY_PUBLIC,
        ]);
        $community->owner()->associate($admin)->save();

        foreach ([['General discussion', '💬'], ['Wins', '🏆'], ['Introductions', '👋'], ['Announcements', '📣', true]] as $i => $row) {
            $community->categories()->create([
                'name' => $row[0], 'slug' => str($row[0])->slug(), 'emoji' => $row[1], 'position' => $i, 'admin_only' => $row[2] ?? false,
            ]);
        }

        $memberships->addOwner($community, $admin);

        $members = User::factory(12)->create();
        foreach ($members as $member) {
            $memberships->join($community, $member);
        }

        $categories = $community->categories;
        $authors = $members->push($admin);

        Post::unguarded(fn () => $community->posts()->create([
            'user_id' => $admin->id,
            'category_id' => $categories->firstWhere('slug', 'announcements')->id,
            'title' => 'Start here: welcome to the community 👋',
            'body' => "Introduce yourself in the Introductions category and tell us what you're working on.\n\nCheck out this quick tour: https://www.youtube.com/watch?v=dQw4w9WgXcQ",
            'is_pinned' => true,
        ]));

        foreach (range(1, 18) as $i) {
            $post = $community->posts()->create([
                'user_id' => $authors->random()->id,
                'category_id' => $categories->where('admin_only', false)->random()->id,
                'title' => rtrim(fake()->sentence(rand(4, 9)), '.'),
                'body' => fake()->paragraphs(rand(1, 3), true),
                'last_activity_at' => now()->subHours($i * 5),
            ]);

            foreach (range(0, rand(0, 4)) as $_) {
                $post->comments()->create(['user_id' => $authors->random()->id, 'body' => fake()->sentence(rand(6, 18))]);
                $post->increment('comments_count');
            }

            foreach ($authors->random(rand(0, 6)) as $liker) {
                if ($liker->isNot($post->author)) {
                    $likes->toggle($liker, $post);
                }
            }
        }

        // Spread point history over the last 45 days so 7/30-day boards differ.
        PointEvent::query()->each(fn (PointEvent $e) => $e->forceFill(['created_at' => now()->subDays(rand(0, 45))])->save());

        $this->seedClassroom($community);
        $this->seedEvents($community, $admin);

        $reviews = [
            [5, 'The weekly calls alone are worth it. I shipped my first product within a month of joining, and people here actually reply when you get stuck.'],
            [5, 'Clear, structured lessons and a community that keeps you accountable. Best decision I made this year.'],
            [4, 'Lots of practical content. I wish there were more beginner lessons, but the admins are adding new ones every week.'],
            [5, 'I came for the course and stayed for the people. Every win gets celebrated here.'],
        ];
        $community->members()->where('role', CommunityMember::ROLE_MEMBER)->with('user')->limit(count($reviews))->get()
            ->each(fn (CommunityMember $m, int $i) => $community->reviews()->create([
                'user_id' => $m->user_id, 'rating' => $reviews[$i][0], 'body' => $reviews[$i][1],
            ]));

        // Ready-made plans; the community stays free until the owner switches it to paid.
        $community->plans()->create(['name' => 'Monthly', 'interval' => 'month', 'amount' => 4900, 'currency' => 'USD']);
        $community->plans()->create(['name' => 'India', 'interval' => 'month', 'amount' => 99900, 'currency' => 'INR']);
    }

    protected function seedClassroom(Community $community): void
    {
        $course = $community->courses()->create([
            'title' => 'Start Here: Community Onboarding',
            'description' => 'Everything you need to get the most out of this community in your first week.',
            'is_published' => true,
            'position' => 0,
        ]);

        $outline = [
            'Welcome' => ['Welcome to the community', 'How this community works', 'Introduce yourself'],
            'Your first week' => ['Set your 30-day goal', 'Join your first live call'],
        ];

        foreach (array_keys($outline) as $m => $moduleTitle) {
            $module = $course->modules()->create(['title' => $moduleTitle, 'position' => $m]);
            foreach ($outline[$moduleTitle] as $l => $lessonTitle) {
                $module->lessons()->create([
                    'course_id' => $course->id,
                    'title' => $lessonTitle,
                    'body' => fake()->paragraphs(3, true),
                    'video_url' => $l === 0 ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null,
                    'resources' => $l === 0 ? [['label' => 'Community guidelines', 'url' => 'https://example.com/guidelines']] : [],
                    'position' => $l,
                ]);
            }
        }

        $community->courses()->create([
            'title' => 'Advanced Growth Playbook',
            'description' => 'Unlocks at level 3. Deeper strategies for members who are actively contributing.',
            'access' => Course::ACCESS_LEVEL,
            'unlock_level' => 3,
            'is_published' => true,
            'position' => 1,
        ])->modules()->create(['title' => 'Module 1', 'position' => 0]);
    }

    protected function seedEvents(Community $community, User $host): void
    {
        $weekly = new Event([
            'title' => 'Weekly Q&A call',
            'description' => 'Bring your questions. We go through wins, blockers and hot seats.',
            'starts_at' => now()->next('Thursday')->setTime(17, 0),
            'duration_minutes' => 60,
            'recurrence' => Event::RECURRENCE_WEEKLY,
            'location' => 'https://zoom.us/j/1234567890',
        ]);
        $workshop = new Event([
            'title' => 'Live workshop: Build your offer',
            'starts_at' => now()->addDays(10)->setTime(15, 30),
            'duration_minutes' => 90,
            'location' => 'https://meet.google.com/abc-defg-hij',
        ]);

        foreach ([$weekly, $workshop] as $event) {
            $event->community()->associate($community);
            $event->host()->associate($host);
            $event->save();
        }
    }
}
