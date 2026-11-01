import { CourseForm } from '@/components/classroom/course-form';
import { LessonEditor } from '@/components/classroom/lesson-editor';
import { findVideoEmbed, RichText, VideoEmbed } from '@/components/community/rich-text';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import CommunityLayout from '@/layouts/community-layout';
import { cn } from '@/lib/utils';
import { type Community, type CourseCard, type CourseModule, type Lesson } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, Circle, ExternalLink, MoreHorizontal, Pencil, Plus, Settings } from 'lucide-react';
import { useState } from 'react';

interface ShowProps {
    community: Community;
    course: CourseCard;
    modules: CourseModule[];
    lesson: Lesson | null;
    canEdit: boolean;
}

export default function Show({ community, course, modules, lesson, canEdit }: ShowProps) {
    const [editing, setEditing] = useState(false);
    const [addingTo, setAddingTo] = useState<number | null>(null);
    const [courseSettings, setCourseSettings] = useState(false);

    const lessonUrl = (id: number) => route('classroom.show', { community: community.slug, course: course.id, lesson: id });
    const video = lesson?.video_url ? findVideoEmbed(lesson.video_url) : null;

    const move = (type: 'module' | 'lesson', id: number, direction: 'up' | 'down') =>
        router.post(route('classroom.move', [community.slug, type, id]), { direction }, { preserveScroll: true });

    const toggleComplete = () => lesson && router.post(route('lessons.complete', [community.slug, lesson.id]), {}, { preserveScroll: true });

    return (
        <CommunityLayout community={community}>
            <Head title={lesson ? `${lesson.title} · ${course.title}` : course.title} />

            <Link
                href={route('classroom.index', community.slug)}
                className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1 text-sm"
            >
                <ArrowLeft className="size-4" /> All courses
            </Link>

            <div className="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside className="bg-card h-fit overflow-hidden rounded-xl border shadow-xs lg:sticky lg:top-20">
                    <div className="border-b p-4">
                        <div className="flex items-start gap-2">
                            <h1 className="flex-1 font-semibold">{course.title}</h1>
                            {canEdit && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="-mt-1 -mr-2 size-8"
                                    onClick={() => setCourseSettings(true)}
                                    aria-label="Course settings"
                                >
                                    <Settings className="size-4" />
                                </Button>
                            )}
                        </div>
                        {!course.is_published && <span className="text-xs font-semibold text-amber-600">Draft: only admins can see this</span>}
                        <div className="bg-muted mt-3 h-2 overflow-hidden rounded-full">
                            <div className="bg-brand h-full rounded-full transition-all" style={{ width: `${course.progress}%` }} />
                        </div>
                        <div className="text-muted-foreground mt-1 text-xs">{course.progress}% complete</div>
                    </div>

                    <nav className="max-h-[70vh] overflow-y-auto p-2">
                        {modules.map((module, mi) => (
                            <div key={module.id} className="mb-2">
                                <div className="group flex items-center gap-1 px-2 py-1.5">
                                    <span className="flex-1 text-sm font-semibold">{module.title}</span>
                                    {canEdit && (
                                        <ModuleMenu
                                            community={community}
                                            module={module}
                                            first={mi === 0}
                                            last={mi === modules.length - 1}
                                            onMove={(d) => move('module', module.id, d)}
                                            onAddLesson={() => setAddingTo(module.id)}
                                        />
                                    )}
                                </div>
                                {module.lessons.map((item, li) => (
                                    <div key={item.id} className="group flex items-center">
                                        <Link
                                            href={lessonUrl(item.id)}
                                            preserveScroll
                                            className={cn(
                                                'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-2 text-sm',
                                                item.id === lesson?.id ? 'bg-brand/10 text-brand font-medium' : 'hover:bg-accent',
                                            )}
                                        >
                                            {item.completed ? (
                                                <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                            ) : (
                                                <Circle className="text-muted-foreground size-4 shrink-0" />
                                            )}
                                            <span className="truncate">{item.title}</span>
                                            {!item.is_published && (
                                                <span className="ml-auto shrink-0 text-[10px] font-semibold text-amber-600 uppercase">Draft</span>
                                            )}
                                        </Link>
                                        {canEdit && (
                                            <div className="hidden shrink-0 group-hover:flex">
                                                <button
                                                    disabled={li === 0}
                                                    onClick={() => move('lesson', item.id, 'up')}
                                                    className="text-muted-foreground hover:text-foreground px-1 disabled:opacity-30"
                                                    aria-label="Move lesson up"
                                                >
                                                    ↑
                                                </button>
                                                <button
                                                    disabled={li === module.lessons.length - 1}
                                                    onClick={() => move('lesson', item.id, 'down')}
                                                    className="text-muted-foreground hover:text-foreground px-1 disabled:opacity-30"
                                                    aria-label="Move lesson down"
                                                >
                                                    ↓
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {canEdit && module.lessons.length === 0 && (
                                    <button
                                        onClick={() => setAddingTo(module.id)}
                                        className="text-muted-foreground hover:text-foreground px-2 py-1 text-xs"
                                    >
                                        + Add the first lesson
                                    </button>
                                )}
                            </div>
                        ))}
                        {canEdit && <AddModule community={community} courseId={course.id} />}
                    </nav>
                </aside>

                <section className="bg-card min-w-0 rounded-xl border p-6 shadow-xs">
                    {!lesson ? (
                        <div className="text-muted-foreground py-16 text-center">
                            <p>This course has no lessons yet.</p>
                            {canEdit && modules[0] && (
                                <Button className="mt-4" onClick={() => setAddingTo(modules[0].id)}>
                                    <Plus /> Add a lesson
                                </Button>
                            )}
                        </div>
                    ) : editing ? (
                        <LessonEditor community={community} lesson={lesson} onDone={() => setEditing(false)} />
                    ) : (
                        <>
                            <div className="mb-4 flex items-start gap-3">
                                <h2 className="flex-1 text-2xl font-bold tracking-tight">{lesson.title}</h2>
                                {canEdit && (
                                    <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                                        <Pencil /> Edit
                                    </Button>
                                )}
                            </div>

                            {video ? (
                                <div className="mb-6">
                                    <VideoEmbed src={video} title={lesson.title} />
                                </div>
                            ) : (
                                lesson.video_url && (
                                    <a
                                        href={lesson.video_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-brand mb-6 inline-flex items-center gap-1 text-sm hover:underline"
                                    >
                                        Watch the video <ExternalLink className="size-3.5" />
                                    </a>
                                )
                            )}

                            {lesson.body && <RichText text={lesson.body} className="text-[15px] leading-relaxed" />}

                            {lesson.resources && lesson.resources.length > 0 && (
                                <div className="mt-6 rounded-lg border p-4">
                                    <h3 className="mb-2 text-sm font-semibold">Resources</h3>
                                    <ul className="space-y-1.5">
                                        {lesson.resources.map((r, i) => (
                                            <li key={i}>
                                                <a
                                                    href={r.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer nofollow"
                                                    className="text-brand inline-flex items-center gap-1.5 text-sm hover:underline"
                                                >
                                                    <ExternalLink className="size-3.5" /> {r.label}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="mt-8 flex flex-wrap items-center gap-2 border-t pt-5">
                                {community.membership?.status === 'active' && (
                                    <Button variant={lesson.completed ? 'outline' : 'default'} onClick={toggleComplete}>
                                        <CheckCircle2 className={cn(lesson.completed && 'text-emerald-500')} />
                                        {lesson.completed ? 'Completed' : 'Mark as complete'}
                                    </Button>
                                )}
                                <div className="ml-auto flex gap-2">
                                    {lesson.previous_id && (
                                        <Button variant="ghost" asChild>
                                            <Link href={lessonUrl(lesson.previous_id)}>
                                                <ChevronLeft /> Previous
                                            </Link>
                                        </Button>
                                    )}
                                    {lesson.next_id && (
                                        <Button variant="outline" asChild>
                                            <Link href={lessonUrl(lesson.next_id)}>
                                                Next <ChevronRight />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </section>
            </div>

            <Dialog open={addingTo !== null} onOpenChange={(open) => !open && setAddingTo(null)}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>New lesson</DialogTitle>
                    </DialogHeader>
                    {addingTo !== null && <LessonEditor community={community} moduleId={addingTo} onDone={() => setAddingTo(null)} />}
                </DialogContent>
            </Dialog>

            <Dialog open={courseSettings} onOpenChange={setCourseSettings}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Course settings</DialogTitle>
                    </DialogHeader>
                    <CourseForm community={community} course={course} onDone={() => setCourseSettings(false)} />
                    <button
                        onClick={() =>
                            confirm("Delete this course, its lessons and everyone's progress?") &&
                            router.delete(route('courses.destroy', [community.slug, course.id]))
                        }
                        className="text-left text-sm text-red-600 hover:underline"
                    >
                        Delete course
                    </button>
                </DialogContent>
            </Dialog>
        </CommunityLayout>
    );
}

function ModuleMenu({
    community,
    module,
    first,
    last,
    onMove,
    onAddLesson,
}: {
    community: Community;
    module: CourseModule;
    first: boolean;
    last: boolean;
    onMove: (direction: 'up' | 'down') => void;
    onAddLesson: () => void;
}) {
    const rename = () => {
        const title = prompt('Module name', module.title);
        if (title?.trim()) router.patch(route('modules.update', [community.slug, module.id]), { title }, { preserveScroll: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-7" aria-label={`${module.title} options`}>
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onAddLesson}>Add lesson</DropdownMenuItem>
                <DropdownMenuItem onSelect={rename}>Rename module</DropdownMenuItem>
                {!first && <DropdownMenuItem onSelect={() => onMove('up')}>Move up</DropdownMenuItem>}
                {!last && <DropdownMenuItem onSelect={() => onMove('down')}>Move down</DropdownMenuItem>}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    className="text-red-600"
                    onSelect={() =>
                        confirm(`Delete "${module.title}" and all its lessons?`) &&
                        router.delete(route('modules.destroy', [community.slug, module.id]), { preserveScroll: true })
                    }
                >
                    Delete module
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function AddModule({ community, courseId }: { community: Community; courseId: number }) {
    const add = () => {
        const title = prompt('New module name');
        if (title?.trim()) router.post(route('modules.store', [community.slug, courseId]), { title }, { preserveScroll: true });
    };

    return (
        <button
            onClick={add}
            className="text-muted-foreground hover:text-foreground hover:bg-accent mt-1 flex w-full items-center gap-2 rounded-md px-2 py-2 text-sm"
        >
            <Plus className="size-4" /> Add module
        </button>
    );
}
