<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Notifications\LevelUp;
use App\Notifications\Mentioned;
use App\Notifications\NewComment;
use App\Notifications\PostBroadcast;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * Renders real emails (other tests fake notifications, which skips toMail()),
 * so a broken template or missing import fails here instead of in the queue.
 */
class NotificationEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_emails_render(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['is_admin' => true])->save();
        $community = new Community(['name' => 'Test', 'slug' => 'test', 'privacy' => Community::PRIVACY_PUBLIC]);
        $community->owner()->associate($owner)->save();
        app(MembershipService::class)->addOwner($community, $owner);

        $member = User::factory()->create();
        app(MembershipService::class)->join($community, $member);

        $post = Post::forceCreate(['community_id' => $community->id, 'user_id' => $owner->id, 'title' => 'Hello', 'body' => "Line one\n\nLine two"]);
        $comment = $post->comments()->create(['user_id' => $member->id, 'body' => 'Nice @'.$owner->username]);

        $notifications = [
            new NewComment($comment, false),
            new Mentioned($member, $post, $comment),
            new PostBroadcast($post),
            new LevelUp($community, 2),
        ];

        foreach ($notifications as $notification) {
            $mail = $notification->toMail($member);
            $this->assertInstanceOf(MailMessage::class, $mail);
            $html = (string) $mail->render();
            $this->assertStringContainsString('email/unsubscribe', $html, $notification::class.' has an unsubscribe link');
        }
    }
}
