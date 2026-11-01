<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photos and GIPHY GIFs on posts and comments. Each attachment is stored as a
 * string: a public-disk path for an uploaded photo, or "giphy:<id>" for a GIF
 * (shown straight from GIPHY's CDN, so it costs us no storage).
 */
class MediaAttachments
{
    public const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public const GIPHY_PREFIX = 'giphy:';

    /**
     * Validate and save new uploads plus an optional GIF, on top of $existing.
     *
     * @param  list<string>  $existing
     * @param  string  $fileField  request field: one file, or an array of files
     * @return list<string>
     */
    public function collect(Request $request, Community $community, array $existing, int $max, string $fileField, string $folder): array
    {
        $raw = $request->file($fileField);
        $files = is_array($raw) ? array_values(array_filter($raw)) : ($raw ? [$raw] : []);
        $gif = $request->input('gif_id');

        if ($files && ! $community->allow_images) {
            throw ValidationException::withMessages([$fileField => 'This community does not allow photos.']);
        }
        if (filled($gif) && ! $community->allow_gifs) {
            throw ValidationException::withMessages(['gif_id' => 'This community does not allow GIFs.']);
        }

        $count = count($existing) + count($files) + (filled($gif) ? 1 : 0);
        if ($count > $max) {
            throw ValidationException::withMessages([$fileField => $max === 1 ? 'You can attach one photo or GIF.' : "You can attach up to {$max} items."]);
        }

        $kb = AppSetting::maxUploadKb();
        $request->validate([
            'gif_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{5,40}$/'],
            ...collect($files)->keys()->mapWithKeys(fn ($i) => [
                is_array($raw) ? "{$fileField}.{$i}" : $fileField => [
                    'file', 'image', 'mimes:'.implode(',', self::IMAGE_TYPES), "max:{$kb}",
                    // The bytes must actually decode as an image, whatever the name says.
                    fn (string $attribute, UploadedFile $file, $fail) => @getimagesize($file->getRealPath()) ?: $fail('That file is not a valid image.'),
                ],
            ])->all(),
        ], [
            '*.max' => 'Each photo must be under '.self::formatSize($kb).'.',
            '*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'gif_id.regex' => 'Pick a GIF from the list.',
        ]);

        foreach ($files as $file) {
            $existing[] = $file->store($folder, 'public');
        }
        if (filled($gif)) {
            $existing[] = self::GIPHY_PREFIX.$gif;
        }

        return $existing;
    }

    /**
     * @return array{path: string, url: string, gif: bool}
     */
    public static function present(string $entry): array
    {
        if (str_starts_with($entry, self::GIPHY_PREFIX)) {
            $id = substr($entry, strlen(self::GIPHY_PREFIX));

            return ['path' => $entry, 'url' => "https://media.giphy.com/media/{$id}/giphy.gif", 'gif' => true];
        }

        return [
            'path' => $entry,
            'url' => Storage::disk('public')->url($entry),
            'gif' => str_ends_with(strtolower($entry), '.gif'), // older uploaded GIFs
        ];
    }

    /**
     * Delete uploaded files (GIPHY entries have nothing to delete).
     *
     * @param  list<string|null>  $entries
     */
    public static function deleteFiles(array $entries): void
    {
        $paths = array_values(array_filter($entries, fn ($e) => $e && ! str_starts_with($e, self::GIPHY_PREFIX)));

        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    public static function formatSize(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : "{$kb} KB";
    }
}
