<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        ]);

        JsonResource::withoutWrapping();

        // Posts and comments: generous for real people, tight enough to stop spam bursts.
        RateLimiter::for('content', fn (Request $request) => [
            Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
            Limit::perHour(120)->by($request->user()?->id ?: $request->ip()),
        ]);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
