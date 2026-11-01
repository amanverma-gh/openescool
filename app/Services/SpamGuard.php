<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ModerationAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Detects spam, and puts suspected spammers "under review": their recent content
 * is hidden and they can't post or message until a platform admin decides.
 */
class SpamGuard
{
    /**
     * @return string|null why the text looks like spam, or null if it's fine
     */
    public function check(User $user, string $text): ?string
    {
        if ($user->is_admin) {
            return null;
        }

        $lower = Str::lower($text);

        foreach (config('spam.keywords') as $keyword) {
            if ($keyword !== '' && str_contains($lower, Str::lower($keyword))) {
                return "Blocked phrase: \"{$keyword}\"";
            }
        }

        $links = preg_match_all('~(https?://|www\.)\S+~i', $text);

        if ($user->isNew() && $links > (int) config('spam.new_account_max_links')) {
            return 'Posted links from a brand-new account';
        }

        if ($links > (int) config('spam.max_links')) {
            return "Posted {$links} links at once";
        }

        return null;
    }

    /**
     * Message-specific checks on top of check(): copy-paste blasts to many people.
     */
    public function checkMessage(User $sender, string $body, int $conversationId): ?string
    {
        if ($reason = $this->check($sender, $body)) {
            return $reason;
        }

        $otherRecipients = Message::where('user_id', $sender->id)
            ->where('fingerprint', Message::fingerprint($body))
            ->where('created_at', '>=', now()->subMinutes(30))
            ->where('conversation_id', '!=', $conversationId)
            ->distinct()
            ->count('conversation_id');

        return $otherRecipients + 1 >= (int) config('spam.duplicate_message_recipients')
            ? 'Sent the same message to '.($otherRecipients + 1).' people'
            : null;
    }

    public function restrict(User $user, string $reason): void
    {
        if ($user->is_admin || $user->isRestricted()) {
            return;
        }

        DB::transaction(function () use ($user, $reason) {
            $user->forceFill(['restricted_at' => now(), 'restricted_reason' => Str::limit($reason, 250)])->save();
            $this->setContentSpam($user, true, now()->subDays(7));
        });

        Notification::send(User::where('is_admin', true)->get(), new ModerationAlert($user, $reason));
    }

    /**
     * "Not spam": lift the restriction and bring their content back.
     */
    public function release(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['restricted_at' => null, 'restricted_reason' => null])->save();
            $this->setContentSpam($user, false);
            $this->resolveReports($user);
        });
    }

    /**
     * Permanent block: signed out, can't sign in, everything they wrote stays hidden.
     */
    public function ban(User $user): void
    {
        abort_if($user->is_admin, 422, 'Admins cannot be banned.');

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'banned_at' => now(),
                'restricted_at' => $user->restricted_at ?? now(),
                'restricted_reason' => $user->restricted_reason ?? 'Banned by an admin',
                'remember_token' => Str::random(60),
            ])->save();
            $this->setContentSpam($user, true);
            $this->resolveReports($user);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    public function deleteAccount(User $user): void
    {
        abort_if($user->is_admin, 422, 'Admins cannot be deleted here.');
        abort_if(Community::where('owner_id', $user->id)->exists(), 422, 'This user owns a community. Transfer or delete it first.');

        $communities = Community::whereIn('id', $user->memberships()->select('community_id'))->get();
        $files = Post::withoutGlobalScopes()->where('user_id', $user->id)->pluck('attachments')->filter()->flatten()
            ->concat(Comment::withoutGlobalScopes()->where('user_id', $user->id)->pluck('attachment'))
            ->all();

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->notifications()->delete();
            $user->delete(); // posts, comments, messages, likes and memberships cascade
        });

        Post::deleteFiles($files);
        $communities->each->refreshMembersCount();
    }

    /**
     * A member flags a post, comment or message. Enough distinct reporters puts the author under review.
     */
    public function report(User $reporter, Post|Comment|Message $item, string $reason = 'spam'): void
    {
        $author = User::find($item->user_id);

        if (! $author || $author->is($reporter)) {
            return;
        }

        Report::firstOrCreate(
            ['reporter_id' => $reporter->id, 'reportable_type' => $item->getMorphClass(), 'reportable_id' => $item->getKey()],
            ['reported_user_id' => $author->id, 'reason' => $reason],
        );

        $reporters = Report::where('reported_user_id', $author->id)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct()
            ->count('reporter_id');

        if ($reporters >= (int) config('spam.reports_to_restrict')) {
            $this->restrict($author, "Reported as spam by {$reporters} members");
        }
    }

    protected function setContentSpam(User $user, bool $spam, $since = null): void
    {
        foreach ([Post::class, Comment::class, Message::class] as $model) {
            /** @var class-string<Model> $model */
            $model::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
                ->update(['is_spam' => $spam]);
        }
    }

    protected function resolveReports(User $user): void
    {
        Report::where('reported_user_id', $user->id)->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
