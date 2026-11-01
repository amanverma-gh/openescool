import { RatingSummary, StarPicker, Stars } from '@/components/about/stars';
import { UserAvatar } from '@/components/community/user-avatar';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { type PublicUser } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useState } from 'react';

export interface ReviewItem {
    id: number;
    rating: number;
    body: string;
    is_hidden: boolean;
    created_at: string;
    tenure: string;
    user: PublicUser;
}

interface ReviewsProps {
    slug: string;
    name: string;
    rating: { average: number | null; count: number };
    reviews: ReviewItem[];
    canReview: boolean;
    canManage: boolean;
    myReview: { rating: number; body: string } | null;
}

const PAGE = 5;

function reviewDate(value: string): string {
    const date = new Date(value);
    const days = (Date.now() - date.getTime()) / 86_400_000;
    if (days < 1) return 'Today';
    if (days < 30) return `${Math.floor(days)}d ago`;
    return date.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
}

export function Reviews({ slug, name, rating, reviews, canReview, canManage, myReview }: ReviewsProps) {
    const [shown, setShown] = useState(PAGE);
    const [writing, setWriting] = useState(false);
    const form = useForm({ rating: myReview?.rating ?? 0, body: myReview?.body ?? '' });

    if (reviews.length === 0 && !canReview) return null;

    return (
        <section id="reviews" className="space-y-4 border-t pt-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                {rating.count > 0 ? (
                    <RatingSummary average={rating.average} count={rating.count} className="text-base font-medium" />
                ) : (
                    <h2 className="font-semibold">Reviews</h2>
                )}
                {canReview && (
                    <Button variant="outline" size="sm" onClick={() => setWriting(true)}>
                        {myReview ? 'Edit your review' : 'Write a review'}
                    </Button>
                )}
            </div>

            {reviews.length === 0 && (
                <p className="text-muted-foreground text-sm">No reviews yet. Be the first to share how {name} has helped you.</p>
            )}

            <div className="space-y-3">
                {reviews.slice(0, shown).map((review) => (
                    <ReviewCard key={review.id} review={review} slug={slug} canManage={canManage} />
                ))}
            </div>

            {reviews.length > shown && (
                <Button variant="ghost" className="w-full" onClick={() => setShown((n) => n + PAGE * 2)}>
                    See more
                </Button>
            )}

            <Dialog open={writing} onOpenChange={setWriting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{myReview ? 'Edit your review' : `Review ${name}`}</DialogTitle>
                        <DialogDescription>Your review is public and shows how long you've been a member.</DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('reviews.store', slug), { preserveScroll: true, onSuccess: () => setWriting(false) });
                        }}
                    >
                        <div className="space-y-1">
                            <StarPicker value={form.data.rating} onChange={(value) => form.setData('rating', value)} />
                            <InputError message={form.errors.rating} />
                        </div>
                        <div className="space-y-1">
                            <Textarea
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                                rows={5}
                                maxLength={2000}
                                placeholder="What has this community done for you?"
                            />
                            <InputError message={form.errors.body} />
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            {myReview ? (
                                <button
                                    type="button"
                                    className="text-muted-foreground text-sm hover:text-red-600"
                                    onClick={() =>
                                        confirm('Delete your review?') &&
                                        router.delete(route('reviews.destroy', slug), { preserveScroll: true, onSuccess: () => setWriting(false) })
                                    }
                                >
                                    Delete review
                                </button>
                            ) : (
                                <span />
                            )}
                            <Button disabled={form.processing || form.data.rating === 0}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                {myReview ? 'Save' : 'Post review'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}

function ReviewCard({ review, slug, canManage }: { review: ReviewItem; slug: string; canManage: boolean }) {
    const [expanded, setExpanded] = useState(false);
    const long = review.body.length > 320;

    return (
        <article className={cn('rounded-lg border p-4', review.is_hidden && 'border-dashed opacity-60')}>
            <div className="flex items-center gap-3">
                <UserAvatar user={review.user} size="sm" />
                <div className="min-w-0 flex-1">
                    <Link href={route('users.show', review.user.username)} className="text-sm font-medium hover:underline">
                        {review.user.name}
                    </Link>
                    <Stars value={review.rating} className="mt-0.5 flex [&_svg]:size-3.5" />
                </div>
                {canManage && (
                    <Button
                        variant="ghost"
                        size="icon"
                        title={review.is_hidden ? 'Show this review' : 'Hide this review'}
                        onClick={() => router.post(route('reviews.hide', [slug, review.id]), {}, { preserveScroll: true })}
                    >
                        {review.is_hidden ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                    </Button>
                )}
            </div>
            <p className={cn('mt-3 text-sm leading-relaxed whitespace-pre-line', !expanded && long && 'line-clamp-4')}>{review.body}</p>
            {long && (
                <button className="text-muted-foreground mt-1 text-sm hover:underline" onClick={() => setExpanded(!expanded)}>
                    {expanded ? 'See less' : '... See more'}
                </button>
            )}
            <div className="text-muted-foreground mt-2 text-xs">
                {reviewDate(review.created_at)} · {review.tenure}
                {review.is_hidden && ' · Hidden from visitors'}
            </div>
        </article>
    );
}
