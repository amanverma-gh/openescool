<?php

namespace Database\Seeders;

use App\Models\Community;
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
    }
}
