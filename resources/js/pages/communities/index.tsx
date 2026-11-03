import { CommunityIcon } from '@/components/community/top-bar';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCount } from '@/lib/utils';
import { type CommunityLink, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Lock, Plus, Star, Users } from 'lucide-react';

type Card = CommunityLink & {
    tagline: string | null;
    cover: string | null;
    privacy: string;
    members_count: number;
    rating: { average: number; count: number } | null;
};

interface Hero {
    headline: string;
    subtext: string;
    button: string;
    communities_title: string;
}

export default function Index({ mine, discover, canCreate, hero }: { mine: Card[]; discover: Card[]; canCreate: boolean; hero: Hero }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title="Communities" />

            {!auth.user && (
                <section className="py-10 text-center">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-5xl">{hero.headline}</h1>
                    <p className="text-muted-foreground mx-auto mt-4 max-w-xl text-lg">{hero.subtext}</p>
                    <div className="mt-6 flex justify-center gap-3">
                        <Button size="lg" asChild>
                            <Link href={route('register')}>{hero.button}</Link>
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href={route('login')}>Log in</Link>
                        </Button>
                    </div>
                </section>
            )}

            {mine.length > 0 && <Section title="Your communities" communities={mine} />}

            {discover.length > 0 && <Section title={hero.communities_title} communities={discover} />}

            {mine.length === 0 && discover.length === 0 && (
                <div className="bg-card text-muted-foreground rounded-xl border p-10 text-center">
                    <p>No communities yet.</p>
                    {canCreate && (
                        <Button className="mt-4" asChild>
                            <Link href={route('communities.create')}>
                                <Plus /> Create your first community
                            </Link>
                        </Button>
                    )}
                </div>
            )}

            {canCreate && (mine.length > 0 || discover.length > 0) && (
                <div className="mt-8 text-center">
                    <Button variant="outline" asChild>
                        <Link href={route('communities.create')}>
                            <Plus /> Create a community
                        </Link>
                    </Button>
                </div>
            )}
        </AppLayout>
    );
}

function Section({ title, communities }: { title: string; communities: Card[] }) {
    return (
        <section className="mb-10">
            <h2 className="mb-4 text-lg font-semibold">{title}</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {communities.map((c) => (
                    <Link
                        key={c.id}
                        href={route('communities.feed', c.slug)}
                        className="bg-card group overflow-hidden rounded-xl border shadow-xs transition-shadow hover:shadow-md"
                    >
                        {c.cover ? (
                            <img src={c.cover} alt="" className="aspect-[2/1] w-full object-cover" />
                        ) : (
                            <div className="from-brand/70 to-brand aspect-[2/1] w-full bg-gradient-to-br" />
                        )}
                        <div className="p-4">
                            <div className="mb-2 flex items-center gap-2">
                                <CommunityIcon community={c} className="size-8" />
                                <h3 className="truncate font-semibold group-hover:underline">{c.name}</h3>
                            </div>
                            {c.tagline && <p className="text-muted-foreground line-clamp-2 text-sm">{c.tagline}</p>}
                            <div className="text-muted-foreground mt-3 flex items-center gap-3 text-xs">
                                <span className="inline-flex items-center gap-1">
                                    <Users className="size-3.5" /> {formatCount(c.members_count)} members
                                </span>
                                {c.rating && (
                                    <span className="inline-flex items-center gap-1">
                                        <Star className="size-3.5 fill-amber-400 text-amber-400" /> {c.rating.average.toFixed(1)} ({c.rating.count})
                                    </span>
                                )}
                                {c.privacy === 'private' && (
                                    <span className="inline-flex items-center gap-1">
                                        <Lock className="size-3.5" /> Private
                                    </span>
                                )}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}
