<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GIF picker search, proxied so the GIPHY key never reaches the browser.
 */
class GiphyController extends Controller
{
    public const RATING = 'pg-13';

    public function search(Request $request): JsonResponse
    {
        $key = AppSetting::get('giphy_api_key');
        abort_unless($key, 503, 'GIF search is not set up yet.');

        $q = mb_substr(trim((string) $request->query('q')), 0, 50);
        $endpoint = $q === '' ? 'trending' : 'search';

        $gifs = Cache::remember('giphy:'.md5($endpoint.'|'.mb_strtolower($q)), now()->addMinutes(10), function () use ($key, $q, $endpoint) {
            $response = Http::timeout(5)->get("https://api.giphy.com/v1/gifs/{$endpoint}", array_filter([
                'api_key' => $key,
                'q' => $q ?: null,
                'limit' => 24,
                'rating' => self::RATING,
                'bundle' => 'messaging_non_clips',
            ]));

            abort_unless($response->successful(), 502, 'GIF search is unavailable right now.');

            return collect($response->json('data', []))
                ->map(fn (array $gif) => [
                    'id' => $gif['id'],
                    'preview' => $gif['images']['fixed_width_downsampled']['url'] ?? $gif['images']['fixed_width']['url'] ?? null,
                    'title' => $gif['title'] ?? '',
                ])
                ->filter(fn ($gif) => $gif['preview'] && preg_match('/^[A-Za-z0-9]{5,40}$/', $gif['id']))
                ->values()
                ->all();
        });

        return response()->json(['data' => $gifs]);
    }
}
