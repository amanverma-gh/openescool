<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Services\Brevo\BrevoTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient((string) config('services.stripe.secret')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'post' => Post::class,
            'comment' => Comment::class,
            'user' => User::class,
            'message' => Message::class,
        ]);

        JsonResource::withoutWrapping();

        // Local Windows PHP often ships without a CA bundle, so HTTPS calls (GIPHY, Brevo) fail
        // with cURL error 60. Verify against the Windows certificate store instead; Linux servers
        // already have a bundle and are unaffected.
        if (PHP_OS_FAMILY === 'Windows' && ! ini_get('curl.cainfo') && defined('CURLSSLOPT_NATIVE_CA')) {
            Http::globalOptions(['curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA]]);
        }

        // Brevo API mailer: needs only the API key an admin saves in Admin settings.
        Mail::extend('brevo', fn () => new BrevoTransport((string) config('services.brevo.key')));
        if (! $this->app->runningUnitTests()) {
            AppSetting::applyToConfig();
        }

        // Posts and comments: generous for real people, tight enough to stop spam bursts.
        RateLimiter::for('content', fn (Request $request) => [
            Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
            Limit::perHour(120)->by($request->user()?->id ?: $request->ip()),
        ]);

        // Chat: fast enough for a real conversation, too slow for a bot blasting DMs.
        RateLimiter::for('messages', fn (Request $request) => [
            Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()),
            Limit::perDay(300)->by($request->user()?->id ?: $request->ip()),
        ]);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
