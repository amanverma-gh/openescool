import { CourseForm } from '@/components/classroom/course-form';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import CommunityLayout from '@/layouts/community-layout';
import { cn, plural } from '@/lib/utils';
import { type Community, type CourseCard } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Lock, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Classroom({ community, courses }: { community: Community; courses: CourseCard[] }) {
    const [creating, setCreating] = useState(false);

    return (
        <CommunityLayout community={community}>
            <Head title={`Classroom · ${community.name}`} />

            <div className="mb-5 flex items-center justify-between">
                <h1 className="text-xl font-bold">Classroom</h1>
                {community.can.manage && (
                    <Button onClick={() => setCreating(true)}>
                        <Plus /> New course
                    </Button>
                )}
            </div>

            {courses.length === 0 ? (
                <div className="bg-card text-muted-foreground rounded-xl border p-12 text-center">
                    <BookOpen className="mx-auto mb-3 size-8 opacity-50" />
                    <p>No courses yet.</p>
                    {community.can.manage && <p className="text-sm">Create your first course to start teaching your members.</p>}
                </div>
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {courses.map((course) => (
                        <CourseTile key={course.id} community={community} course={course} />
                    ))}
                </div>
            )}

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New course</DialogTitle>
                    </DialogHeader>
                    <CourseForm community={community} onDone={() => setCreating(false)} />
                </DialogContent>
            </Dialog>
        </CommunityLayout>
    );
}

function CourseTile({ community, course }: { community: Community; course: CourseCard }) {
    const locked = !course.unlocked;

    const content = (
        <>
            <div className="relative aspect-video overflow-hidden">
                {course.cover ? (
                    <img src={course.cover} alt="" className={cn('size-full object-cover', locked && 'opacity-40 grayscale')} />
                ) : (
                    <div
                        className={cn('from-brand/70 to-brand flex size-full items-center justify-center bg-gradient-to-br', locked && 'opacity-40')}
                    >
                        <BookOpen className="size-10 text-white/80" />
                    </div>
                )}
                {locked && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-sm font-medium">
                        <Lock className="size-6" />
                        {course.access === 'level' ? `Unlock at Level ${course.unlock_level}` : 'Members only'}
                    </div>
                )}
                {!course.is_published && (
                    <span className="absolute top-2 left-2 rounded-md bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white">Draft</span>
                )}
            </div>
            <div className="flex flex-1 flex-col p-4">
                <h2 className="font-semibold">{course.title}</h2>
                {course.description && <p className="text-muted-foreground mt-1 line-clamp-3 text-sm">{course.description}</p>}
                <div className="mt-auto pt-4">
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div className="bg-brand h-full rounded-full transition-all" style={{ width: `${course.progress}%` }} />
                    </div>
                    <div className="text-muted-foreground mt-1.5 flex justify-between text-xs">
                        <span>{plural(course.lessons_count ?? 0, 'lesson')}</span>
                        <span>{course.progress}% complete</span>
                    </div>
                </div>
            </div>
        </>
    );

    const className = 'bg-card flex flex-col overflow-hidden rounded-xl border shadow-xs transition-shadow';

    return locked ? (
        <div className={cn(className, 'cursor-not-allowed')}>{content}</div>
    ) : (
        <Link href={route('classroom.show', [community.slug, course.id])} className={cn(className, 'hover:shadow-md')} prefetch>
            {content}
        </Link>
    );
}
