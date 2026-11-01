<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $member = $community->membershipFor($request->user());
        $canManage = (bool) $member?->canManage();

        $courses = $community->courses()
            ->when(! $canManage, fn ($q) => $q->where('is_published', true))
            ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->get();

        $completed = $this->completedCounts($request, $courses->modelKeys());

        return Inertia::render('classroom/index', [
            'community' => $this->communityProps($community),
            'courses' => $courses->map(fn (Course $course) => $course->only([
                'id', 'title', 'description', 'cover', 'access', 'unlock_level', 'is_published', 'lessons_count',
            ]) + [
                'unlocked' => $course->isUnlockedFor($member),
                'progress' => $course->lessons_count
                    ? (int) round(100 * ($completed[$course->id] ?? 0) / $course->lessons_count)
                    : 0,
            ]),
        ]);
    }

    public function show(Request $request, Community $community, Course $course): Response|RedirectResponse
    {
        $this->ensureCanView($community);
        abort_unless($course->community_id === $community->id, 404);

        $member = $community->membershipFor($request->user());
        $canManage = (bool) $member?->canManage();

        if (! $course->isUnlockedFor($member)) {
            $reason = $course->access === Course::ACCESS_LEVEL && $member?->isActive()
                ? 'Reach '.$community->levelLabel((int) $course->unlock_level).' to unlock this course.'
                : 'Join the community to access this course.';

            return to_route('classroom.index', $community)->with('error', $reason);
        }

        $modules = $course->modules()
            ->with(['lessons' => fn ($q) => $q->when(! $canManage, fn ($q) => $q->where('is_published', true))])
            ->get();
        $lessons = $modules->flatMap->lessons->values();

        $done = LessonCompletion::query()
            ->where('user_id', $request->user()?->id ?? 0)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('lesson_id')
            ->flip();

        $current = $lessons->firstWhere('id', (int) $request->query('lesson')) ?? $lessons->first();
        $index = $current ? $lessons->search(fn (Lesson $l) => $l->is($current)) : false;
        $published = $lessons->where('is_published', true);

        return Inertia::render('classroom/show', [
            'community' => $this->communityProps($community),
            'course' => $course->only(['id', 'title', 'description', 'cover', 'access', 'unlock_level', 'is_published']) + [
                'progress' => $published->count() ? (int) round(100 * $published->filter(fn ($l) => isset($done[$l->id]))->count() / $published->count()) : 0,
            ],
            'modules' => $modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'is_published' => $l->is_published,
                    'completed' => isset($done[$l->id]),
                ]),
            ]),
            'lesson' => $current ? $current->only(['id', 'title', 'body', 'video_url', 'is_published', 'course_module_id']) + [
                // Uploaded files get a download link that checks access; `path` lets the editor keep them.
                'resources' => collect($current->resources ?? [])->map(fn (array $r, int $i) => isset($r['path'])
                    ? ['label' => $r['label'], 'url' => route('lessons.file', [$community, $current, $i]), 'path' => $r['path'], 'name' => $r['name'] ?? null, 'size' => $r['size'] ?? null]
                    : $r)->all(),
                'video_file_url' => $current->video_path ? route('lessons.video', [$community, $current]) : null,
                'completed' => isset($done[$current->id]),
                'previous_id' => $index > 0 ? $lessons[$index - 1]->id : null,
                'next_id' => $lessons[$index + 1]->id ?? null,
            ] : null,
            'canEdit' => $canManage,
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $course = $community->courses()->create($this->validated($request) + [
            'position' => (int) $community->courses()->max('position') + 1,
        ]);
        $course->modules()->create(['title' => 'Getting started', 'position' => 0]);

        return to_route('classroom.show', [$community, $course])->with('success', 'Course created. Add your first lesson.');
    }

    public function update(Request $request, Community $community, Course $course): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($course->community_id === $community->id, 404);

        $data = $this->validated($request);

        if ($request->hasFile('cover')) {
            if ($course->cover_path) {
                Storage::disk('public')->delete($course->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store("communities/{$community->id}/courses", 'public');
        }

        $course->update($data);

        return back()->with('success', 'Course saved.');
    }

    public function destroy(Community $community, Course $course): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($course->community_id === $community->id, 404);

        if ($course->cover_path) {
            Storage::disk('public')->delete($course->cover_path);
        }
        Storage::disk('local')->delete($course->lessons->flatMap->filePaths()->all());
        $course->delete();

        return to_route('classroom.index', $community)->with('success', 'Course deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'access' => ['required', Rule::in([Course::ACCESS_OPEN, Course::ACCESS_LEVEL])],
            'unlock_level' => ['nullable', 'required_if:access,level', 'integer', 'between:2,9'],
            'is_published' => ['boolean'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ]);

        unset($data['cover']);
        if ($data['access'] === Course::ACCESS_OPEN) {
            $data['unlock_level'] = null;
        }

        return $data;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, int> course id => completed published lessons
     */
    protected function completedCounts(Request $request, array $courseIds): array
    {
        if (! $request->user() || $courseIds === []) {
            return [];
        }

        return Lesson::query()
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->whereHas('completions', fn ($q) => $q->where('user_id', $request->user()->id))
            ->selectRaw('course_id, count(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id')
            ->all();
    }
}
