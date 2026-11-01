<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin editing of modules and lessons, plus members marking lessons complete.
 */
class LessonController extends Controller
{
    public function storeModule(Request $request, Community $community, Course $course): RedirectResponse
    {
        $this->authorizeCourse($community, $course);

        $course->modules()->create($request->validate(['title' => ['required', 'string', 'max:120']]) + [
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back();
    }

    public function updateModule(Request $request, Community $community, CourseModule $module): RedirectResponse
    {
        $this->authorizeCourse($community, $module->course);

        $module->update($request->validate(['title' => ['required', 'string', 'max:120']]));

        return back();
    }

    public function destroyModule(Community $community, CourseModule $module): RedirectResponse
    {
        $this->authorizeCourse($community, $module->course);
        abort_if($module->course->modules()->count() === 1, 422, 'A course needs at least one module.');

        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    public function store(Request $request, Community $community, CourseModule $module): RedirectResponse
    {
        $course = $module->course;
        $this->authorizeCourse($community, $course);

        $lesson = $module->lessons()->create($this->validated($request) + [
            'course_id' => $course->id,
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return to_route('classroom.show', [$community, $course, 'lesson' => $lesson->id]);
    }

    public function update(Request $request, Community $community, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($community, $lesson->course);

        $lesson->update($this->validated($request));

        return back()->with('success', 'Lesson saved.');
    }

    public function destroy(Community $community, Lesson $lesson): RedirectResponse
    {
        $course = $lesson->course;
        $this->authorizeCourse($community, $course);

        $lesson->delete();

        return to_route('classroom.show', [$community, $course])->with('success', 'Lesson deleted.');
    }

    /**
     * Move a module or lesson one step up or down among its siblings.
     */
    public function move(Request $request, Community $community, string $type, int $id): RedirectResponse
    {
        $item = match ($type) {
            'module' => CourseModule::findOrFail($id),
            'lesson' => Lesson::findOrFail($id),
            default => abort(404),
        };
        $this->authorizeCourse($community, $item->course);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $siblings = $item instanceof Lesson
            ? Lesson::where('course_module_id', $item->course_module_id)->orderBy('position')->orderBy('id')->get()
            : CourseModule::where('course_id', $item->course_id)->orderBy('position')->orderBy('id')->get();

        $index = $siblings->search(fn (Model $s) => $s->is($item));
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if (isset($siblings[$swap])) {
            [$siblings[$index], $siblings[$swap]] = [$siblings[$swap], $siblings[$index]];
            DB::transaction(fn () => $siblings->values()->each(fn (Model $s, int $i) => $s->update(['position' => $i])));
        }

        return back();
    }

    public function complete(Request $request, Community $community, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->course->community_id === $community->id, 404);
        abort_unless($lesson->course->isUnlockedFor($community->membershipFor($request->user())), 403);

        $completion = $lesson->completions()->where('user_id', $request->user()->id)->first();

        if ($completion) {
            $completion->delete();
        } else {
            $lesson->completions()->create(['user_id' => $request->user()->id]);
        }

        return back();
    }

    protected function authorizeCourse(Community $community, Course $course): void
    {
        $this->authorize('manage', $community);
        abort_unless($course->community_id === $community->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'video_url' => ['nullable', 'url:https', 'max:500'],
            'resources' => ['nullable', 'array', 'max:10'],
            'resources.*.label' => ['required', 'string', 'max:100'],
            'resources.*.url' => ['required', 'url:http,https', 'max:500'],
            'is_published' => ['boolean'],
        ]);

        $data['resources'] = array_values($data['resources'] ?? []);

        return $data;
    }
}
