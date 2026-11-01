<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    protected $fillable = ['course_id', 'course_module_id', 'title', 'body', 'video_url', 'video_path', 'resources', 'is_published', 'position'];

    protected function casts(): array
    {
        return [
            'resources' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    /**
     * Uploaded files (resources and video) on the private disk.
     *
     * @return list<string>
     */
    public function filePaths(): array
    {
        return array_values(array_filter([
            $this->video_path,
            ...array_map(fn (array $r) => $r['path'] ?? null, $this->resources ?? []),
        ]));
    }

    protected static function booted(): void
    {
        static::deleted(fn (Lesson $lesson) => Storage::disk('local')->delete($lesson->filePaths()));
    }
}
