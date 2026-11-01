<?php

namespace App\Http\Controllers;

use App\Models\CommunityMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Services\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-to-one chats. People can message each other only when they share a
 * community (so members can always reach their admins, strangers can't cold-DM).
 */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function show(Request $request, User $user): Response
    {
        abort_if($user->is($request->user()), 404);

        return $this->render($request, $user);
    }

    public function store(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        if ($problem = $this->cannotMessage($me, $user)) {
            return back()->with('error', $problem);
        }

        $existing = Conversation::findBetween($me, $user);

        if (! $existing && $this->newChatsToday($me) >= $this->newChatLimit($me)) {
            return back()->with('error', "You've started the maximum number of new chats for today. Try again tomorrow.");
        }

        $conversation = $existing ?? Conversation::between($me, $user);
        $reason = $spam->checkMessage($me, $data['body'], $conversation->id);

        $wasRead = ! $conversation->isUnreadFor($user);

        DB::transaction(function () use ($conversation, $me, $data, $reason) {
            $conversation->messages()->create([
                'user_id' => $me->id,
                'body' => $data['body'],
                'fingerprint' => Message::fingerprint($data['body']),
                'is_spam' => $reason !== null,
            ]);

            // Held messages never reach the recipient, so they don't count as new.
            if (! $reason) {
                $conversation->forceFill(['last_message_at' => now(), $conversation->readColumn($me) => now()])->save();
            }
        });

        if ($reason) {
            $spam->restrict($me, $reason);

            return back()->with('error', 'Your message is being reviewed by an admin and was not delivered.');
        }

        // One email per unread streak, and only if they're not on the site right now.
        if ($wasRead && $user->wantsEmail('messages') && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            $user->notify(new NewMessage($me, $data['body']));
        }

        return back();
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422);
        $request->user()->blockedUsers()->syncWithoutDetaching([$user->id => ['created_at' => now()]]);

        return back()->with('success', "You blocked {$user->name}. They can't message you any more.");
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        $request->user()->blockedUsers()->detach($user->id);

        return back()->with('success', "You unblocked {$user->name}.");
    }

    protected function render(Request $request, ?User $other): Response
    {
        $me = $request->user();
        $search = trim((string) $request->query('q'));

        $conversations = Conversation::for($me)
            ->whereNotNull('last_message_at')
            ->with(['userA', 'userB'])
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        $lastMessages = Message::whereIn('id', Message::selectRaw('max(id)')
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where(fn ($q) => $q->where('is_spam', false)->orWhere('user_id', $me->id))
            ->groupBy('conversation_id'))
            ->get()
            ->keyBy('conversation_id');

        $active = $other ? Conversation::findBetween($me, $other) : null;

        if ($active) {
            $active->markRead($me);
        }

        return Inertia::render('chat/index', [
            // Hide chats where everything was held as spam (e.g. a bot that got caught).
            'conversations' => $conversations->filter(fn (Conversation $c) => isset($lastMessages[$c->id]))->values()->map(fn (Conversation $c) => [
                'user' => $c->otherUser($me)->toPublicArray(),
                'last' => $lastMessages[$c->id]?->only(['body', 'user_id']) ?? null,
                'last_at' => $c->last_message_at,
                'unread' => $c->isUnreadFor($me) && ! $c->is($active),
            ]),
            // Start a new chat: people you share a community with.
            'people' => $search !== '' ? $this->searchPeople($me, $search) : [],
            'search' => $search,
            'active' => $other ? [
                'user' => $other->toPublicArray() + ['last_seen_at' => $other->last_seen_at],
                'messages' => $active
                    ? $active->messages()
                        ->where(fn ($q) => $q->where('is_spam', false)->orWhere('user_id', $me->id))
                        ->latest('id')->limit(100)->get()->reverse()->values()
                        ->map(fn (Message $m) => $m->only(['id', 'user_id', 'body', 'created_at', 'is_spam']))
                    : [],
                'blockedByMe' => $me->blockedUsers()->whereKey($other->id)->exists(),
                'cannotMessage' => $this->cannotMessage($me, $other),
            ] : null,
        ]);
    }

    /**
     * @return string|null why $from can't message $to, or null if they can
     */
    protected function cannotMessage(User $from, User $to): ?string
    {
        return match (true) {
            $from->is($to) => "You can't message yourself.",
            $from->isRestricted() => 'Your account is under review, so messaging is paused until an admin checks it.',
            $to->isBanned() => 'This account is no longer active.',
            $from->isBlockedWith($to) => "You can't message this person.",
            ! $from->canChatWith($to) => 'Chat is for talking with the community team. Members can’t message each other.',
            default => null,
        };
    }

    protected function newChatsToday(User $user): int
    {
        return Conversation::where('created_at', '>=', now()->subDay())
            ->whereIn('id', Message::where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->select('conversation_id'))
            ->count();
    }

    protected function newChatLimit(User $user): int
    {
        if ($user->is_admin) {
            return PHP_INT_MAX;
        }

        [$newAccount, $established] = config('spam.new_conversations_per_day');

        return $user->isNew() ? $newAccount : $established;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function searchPeople(User $me, string $search): array
    {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $mine = fn () => $me->memberships()->where('status', CommunityMember::STATUS_ACTIVE);
        $myCommunities = $mine()->select('community_id');
        $myTeamCommunities = $mine()->whereIn('role', CommunityMember::STAFF_ROLES)->select('community_id');

        // Same rule as canChatWith(): the team of my communities, or the members of communities I run.
        return User::query()
            ->whereKeyNot($me->id)
            ->whereNull('banned_at')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('username', 'like', $like))
            ->when(! $me->is_admin, fn ($q) => $q->where(fn ($q) => $q
                ->where('is_admin', true)
                ->orWhereHas('memberships', fn ($m) => $m->where('status', CommunityMember::STATUS_ACTIVE)
                    ->whereIn('role', CommunityMember::STAFF_ROLES)->whereIn('community_id', $myCommunities))
                ->orWhereHas('memberships', fn ($m) => $m->where('status', CommunityMember::STATUS_ACTIVE)
                    ->whereIn('community_id', $myTeamCommunities))))
            ->limit(8)
            ->get()
            ->map->toPublicArray()
            ->all();
    }
}
