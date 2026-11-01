<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\MediaAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin editing of modules and lessons, plus members marking lessons complete.
 * Uploaded resources and videos live on the private disk and are only served
 * to people who can open the lesson.
 */
class LessonController extends Controller
{
    public const RESOURCE_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'mp3'];

    public const VIDEO_TYPES = ['mp4', 'webm'];

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

        $module->lessons->each->delete(); // removes their uploaded files too
        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    public function store(Request $request, Community $community, CourseModule $module): RedirectResponse
    {
        $course = $module->course;
        $this->authorizeCourse($community, $course);

        $lesson = $module->lessons()->create($this->validated($request, $community) + [
            'course_id' => $course->id,
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return to_route('classroom.show', [$community, $course, 'lesson' => $lesson->id]);
    }

    public function update(Request $request, Community $community, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($community, $lesson->course);

        $lesson->update($this->validated($request, $community, $lesson));

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

    /**
     * Download an uploaded resource file.
     */
    public function file(Community $community, Lesson $lesson, int $index): StreamedResponse
    {
        $this->authorizeView($community, $lesson);
        $resource = $lesson->resources[$index] ?? null;
        abort_unless(isset($resource['path']) && Storage::disk('local')->exists($resource['path']), 404);

        return Storage::disk('local')->download($resource['path'], $resource['name'] ?? basename($resource['path']));
    }

    /**
     * Stream an uploaded lesson video (supports seeking via range requests).
     */
    public function video(Community $community, Lesson $lesson): BinaryFileResponse
    {
        $this->authorizeView($community, $lesson);
        abort_unless($lesson->video_path && Storage::disk('local')->exists($lesson->video_path), 404);

        return response()->file(Storage::disk('local')->path($lesson->video_path));
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

    /**
     * Same rules as opening the lesson in the classroom.
     */
    protected function authorizeView(Community $community, Lesson $lesson): void
    {
        $this->ensureCanView($community);
        abort_unless($lesson->course->community_id === $community->id, 404);

        $member = $community->membershipFor(request()->user());
        if (! $member?->canManage()) {
            abort_unless($lesson->is_published && $lesson->course->is_published && $lesson->course->isUnlockedFor($member), 403);
        }
    }

    protected function authorizeCourse(Community $community, Course $course): void
    {
        $this->authorize('manage', $community);
        abort_unless($course->community_id === $community->id, 404);
    }

    /**
     * Validate the lesson form. Resources are links or uploaded files; a file
     * the lesson already has is kept by sending back its path.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, Community $community, ?Lesson $lesson = null): array
    {
        $resourceKb = AppSetting::uploadLimitKb('max_resource_kb');
        $videoKb = AppSetting::uploadLimitKb('max_video_kb');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'video_url' => ['nullable', 'url:https', 'max:500'],
            'video_file' => ['nullable', 'file', 'mimes:'.implode(',', self::VIDEO_TYPES), "max:{$videoKb}"],
            'remove_video' => ['boolean'],
            'resources' => ['nullable', 'array', 'max:10'],
            'resources.*.label' => ['required', 'string', 'max:100'],
            'resources.*.url' => ['nullable', 'url:http,https', 'max:500'],
            'resources.*.path' => ['nullable', 'string', 'max:255'],
            'resources.*.file' => ['nullable', 'file', 'mimes:'.implode(',', self::RESOURCE_TYPES), "max:{$resourceKb}"],
            'is_published' => ['boolean'],
        ], [
            'video_file.max' => 'Videos must be under '.MediaAttachments::formatSize($videoKb).'.',
            'video_file.mimes' => 'Videos must be MP4 or WebM.',
            'resources.*.file.max' => 'Each file must be under '.MediaAttachments::formatSize($resourceKb).'.',
            'resources.*.file.mimes' => 'Files must be PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP, an image or MP3.',
        ]);

        $folder = "lessons/{$community->id}";
        $old = $lesson?->resources ?? [];

        $resources = [];
        foreach ($data['resources'] ?? [] as $i => $item) {
            $file = $request->file("resources.{$i}.file");
            if ($file instanceof UploadedFile) {
                $resources[] = [
                    'label' => $item['label'],
                    'path' => $file->store($folder, 'local'),
                    'name' => str($file->getClientOriginalName())->limit(150, '')->toString(),
                    'size' => (int) ceil($file->getSize() / 1024),
                ];
            } elseif (filled($item['path'] ?? null)) {
                // Only a file this lesson already has can be kept.
                $existing = collect($old)->firstWhere('path', $item['path']);
                if (! $existing) {
                    throw ValidationException::withMessages(["resources.{$i}.file" => 'That file is no longer available. Upload it again.']);
                }
                $resources[] = ['label' => $item['label']] + $existing;
            } elseif (filled($item['url'] ?? null)) {
                $resources[] = ['label' => $item['label'], 'url' => $item['url']];
            } else {
                throw ValidationException::withMessages(["resources.{$i}.url" => 'Add a link or upload a file.']);
            }
        }

        // Files dropped from the list are deleted.
        Storage::disk('local')->delete(array_values(array_diff(
            array_filter(array_column($old, 'path')),
            array_filter(array_column($resources, 'path')),
        )));

        $fields = [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'resources' => $resources,
            'is_published' => $data['is_published'] ?? true,
        ];

        if ($request->hasFile('video_file') || ($data['remove_video'] ?? false)) {
            if ($lesson?->video_path) {
                Storage::disk('local')->delete($lesson->video_path);
            }
            $fields['video_path'] = $request->hasFile('video_file') ? $request->file('video_file')->store($folder, 'local') : null;
        }

        return $fields;
    }
}
