import { RichText } from '@/components/community/rich-text';
import { CommunityIcon } from '@/components/community/top-bar';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CommunityLayout from '@/layouts/community-layout';
import { formatCount } from '@/lib/utils';
import { type Community, type PublicUser, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Globe, LoaderCircle, Lock } from 'lucide-react';
import { useState } from 'react';

interface AboutProps {
    community: Community & { about: string | null; requires_approval: boolean; join_questions: string[]; created_at: string };
    admins: (PublicUser & { role: string })[];
    stats: { members: number; posts: number; online: number };
}

export default function About({ community, admins, stats }: AboutProps) {
    const { auth } = usePage<SharedData>().props;
    const [asking, setAsking] = useState(false);
    const form = useForm({ answers: community.join_questions.map(() => '') });

    const join = () => {
        if (!auth.user) return router.visit(route('register'));
        if (community.join_questions.length > 0) return setAsking(true);
        form.post(route('communities.join', community.slug));
    };

    const leave = () => {
        if (confirm(`Leave ${community.name}?`)) router.delete(route('communities.leave', community.slug));
    };

    const status = community.membership?.status;

    return (
        <CommunityLayout community={community}>
            <Head title={`About ${community.name}`} />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="bg-card overflow-hidden rounded-xl border shadow-xs">
                    {community.cover ? (
                        <img src={community.cover} alt="" className="aspect-video w-full object-cover" />
                    ) : (
                        <div className="from-brand/70 to-brand aspect-[3/1] w-full bg-gradient-to-br" />
                    )}
                    <div className="p-6">
                        <h1 className="text-2xl font-bold">{community.name}</h1>
                        <div className="text-muted-foreground mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            <span className="inline-flex items-center gap-1">
                                {community.privacy === 'public' ? <Globe className="size-4" /> : <Lock className="size-4" />}
                                {community.privacy === 'public' ? 'Public group' : 'Private group'}
                            </span>
                            <span>{formatCount(stats.members)} members</span>
                            <span>{formatCount(stats.posts)} posts</span>
                            {admins[0] && <span>By {admins[0].name}</span>}
                        </div>
                        <div className="mt-6">
                            <RichText text={community.about || community.tagline || 'No description yet.'} className="leading-relaxed" />
                        </div>
                    </div>
                </div>

                <aside className="space-y-4">
                    <div className="bg-card space-y-4 rounded-xl border p-5 shadow-xs">
                        <div className="flex items-center gap-2">
                            <CommunityIcon community={community} className="size-10" />
                            <div>
                                <div className="font-semibold">{community.name}</div>
                                {community.tagline && <div className="text-muted-foreground line-clamp-2 text-xs">{community.tagline}</div>}
                            </div>
                        </div>
                        <div className="grid grid-cols-3 border-y py-3 text-center">
                            <Stat value={stats.members} label="Members" />
                            <Stat value={stats.online} label="Online" />
                            <Stat value={admins.length} label="Admins" />
                        </div>

                        {!status && (
                            <Button className="w-full" size="lg" onClick={join} disabled={form.processing}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                {community.requires_approval ? 'Request to join' : 'Join group'}
                            </Button>
                        )}
                        {status === 'pending' && (
                            <p className="bg-muted rounded-md px-3 py-2 text-center text-sm">Your request is pending approval.</p>
                        )}
                        {status === 'banned' && <p className="bg-muted rounded-md px-3 py-2 text-center text-sm">You can't join this community.</p>}
                        {status === 'active' && (
                            <>
                                <Button className="w-full" asChild>
                                    <Link href={route('communities.feed', community.slug)}>Go to community</Link>
                                </Button>
                                {community.membership?.role !== 'owner' && (
                                    <button onClick={leave} className="text-muted-foreground w-full text-xs hover:text-red-600">
                                        Leave community
                                    </button>
                                )}
                            </>
                        )}
                    </div>

                    {admins.length > 0 && (
                        <div className="bg-card rounded-xl border p-5 shadow-xs">
                            <h3 className="mb-3 text-sm font-semibold">Admins</h3>
                            <div className="space-y-3">
                                {admins.map((a) => (
                                    <div key={a.id} className="flex items-center gap-3 text-sm">
                                        <UserAvatar user={a} size="sm" />
                                        <Link href={route('users.show', a.username)} className="font-medium hover:underline">
                                            {a.name}
                                        </Link>
                                        <span className="text-muted-foreground ml-auto text-xs capitalize">{a.role}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </aside>
            </div>

            <Dialog open={asking} onOpenChange={setAsking}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Join {community.name}</DialogTitle>
                        <DialogDescription>Answer a few questions so the admins can approve you.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('communities.join', community.slug), { onSuccess: () => setAsking(false) });
                        }}
                        className="space-y-4"
                    >
                        {community.join_questions.map((question, i) => (
                            <div key={i} className="grid gap-2">
                                <Label>{question}</Label>
                                <Textarea
                                    value={form.data.answers[i]}
                                    onChange={(e) =>
                                        form.setData(
                                            'answers',
                                            form.data.answers.map((a, j) => (j === i ? e.target.value : a)),
                                        )
                                    }
                                    maxLength={500}
                                />
                            </div>
                        ))}
                        <Button className="w-full" disabled={form.processing}>
                            Submit request
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </CommunityLayout>
    );
}

function Stat({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <div className="font-semibold">{formatCount(value)}</div>
            <div className="text-muted-foreground text-xs">{label}</div>
        </div>
    );
}
