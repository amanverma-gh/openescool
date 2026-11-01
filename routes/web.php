<?php

use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityMediaController;
use App\Http\Controllers\CommunitySettingsController;
use App\Http\Controllers\EmailPreferencesController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GiphyController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CommunityController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
});

Route::get('u/{user:username}', [UserProfileController::class, 'show'])->name('users.show');

Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('email/unsubscribe/{user}/{type}', [EmailPreferencesController::class, 'confirm'])->name('email.unsubscribe');
    Route::post('email/unsubscribe/{user}/{type}', [EmailPreferencesController::class, 'unsubscribe']);
});

// Signature-verified; CSRF is skipped for webhooks/* in bootstrap/app.php.
Route::post('webhooks/stripe', [WebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('webhooks/razorpay', [WebhookController::class, 'razorpay'])->name('webhooks.razorpay');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [CommunityController::class, 'dashboard'])->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('chat', [MessageController::class, 'index'])->name('chat.index');
    Route::get('chat/{user:username}', [MessageController::class, 'show'])->name('chat.show');
    Route::post('chat/{user:username}', [MessageController::class, 'store'])->middleware('throttle:messages')->name('chat.store');
    Route::post('chat/{user:username}/block', [MessageController::class, 'block'])->name('chat.block');
    Route::delete('chat/{user:username}/block', [MessageController::class, 'unblock'])->name('chat.unblock');

    Route::post('report/{type}/{id}', [ModerationController::class, 'report'])
        ->whereIn('type', ['post', 'comment', 'message'])->whereNumber('id')
        ->middleware('throttle:20,60')->name('report');

    Route::get('giphy/search', [GiphyController::class, 'search'])->middleware('throttle:60,1')->name('giphy.search');

    Route::get('admin/settings', [AppSettingsController::class, 'edit'])->name('admin.settings');
    Route::post('admin/settings', [AppSettingsController::class, 'update'])->name('admin.settings.update');

    Route::prefix('admin/moderation')->name('moderation.')->controller(ModerationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{user}/restrict', 'restrict')->name('restrict');
        Route::post('{user}/release', 'release')->name('release');
        Route::post('{user}/ban', 'ban')->name('ban');
        Route::post('{user}/unban', 'unban')->name('unban');
        Route::delete('{user}', 'destroy')->name('destroy');
    });

    Route::get('communities/create', [CommunityController::class, 'create'])->name('communities.create');
    Route::post('communities', [CommunityController::class, 'store'])->name('communities.store');
});

Route::prefix('c/{community}')->group(function () {
    Route::get('/', [FeedController::class, 'index'])->name('communities.feed');
    Route::get('about', [CommunityController::class, 'show'])->name('communities.about');
    Route::get('search', [FeedController::class, 'search'])->middleware('throttle:60,1')->name('communities.search');
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('classroom', [ClassroomController::class, 'index'])->name('classroom.index');
    Route::get('classroom/{course}', [ClassroomController::class, 'show'])->name('classroom.show');
    Route::get('calendar', [EventController::class, 'index'])->name('calendar.index');
    Route::get('calendar/{event}.ics', [EventController::class, 'ics'])->name('events.ics');
    Route::get('leaderboards', [LeaderboardController::class, 'index'])->name('leaderboards.index');

    Route::middleware('auth')->group(function () {
        Route::post('join', [MembershipController::class, 'store'])->name('communities.join');
        Route::get('checkout', [CheckoutController::class, 'show'])->name('billing.checkout');
        Route::post('checkout/{plan}', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('billing.checkout.store');
        Route::get('checkout/stripe/success', [CheckoutController::class, 'stripeSuccess'])->name('billing.stripe.success');
        Route::post('checkout/razorpay/confirm', [CheckoutController::class, 'razorpayConfirm'])->name('billing.razorpay.confirm');

        Route::get('revenue', [PricingController::class, 'revenue'])->name('communities.revenue');
        Route::post('access', [PricingController::class, 'updateAccess'])->name('communities.access');
        Route::post('plans', [PricingController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PricingController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [PricingController::class, 'destroy'])->name('plans.destroy');
        Route::delete('leave', [MembershipController::class, 'destroy'])->name('communities.leave');

        Route::middleware('throttle:content')->group(function () {
            Route::post('posts', [PostController::class, 'store'])->name('posts.store');
            Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
        });
        Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/pin', [PostController::class, 'pin'])->name('posts.pin');
        Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('posts/{post}/like', [LikeController::class, 'post'])->name('posts.like');
        Route::post('comments/{comment}/like', [LikeController::class, 'comment'])->name('comments.like');

        Route::post('members/{member}/approve', [MemberController::class, 'approve'])->name('members.approve');
        Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::post('classroom', [ClassroomController::class, 'store'])->name('courses.store');
        Route::post('classroom/{course}', [ClassroomController::class, 'update'])->name('courses.update');
        Route::delete('classroom/{course}', [ClassroomController::class, 'destroy'])->name('courses.destroy');
        Route::post('classroom/{course}/modules', [LessonController::class, 'storeModule'])->name('modules.store');
        Route::patch('modules/{module}', [LessonController::class, 'updateModule'])->name('modules.update');
        Route::delete('modules/{module}', [LessonController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('modules/{module}/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
        Route::post('move/{type}/{id}', [LessonController::class, 'move'])->whereIn('type', ['module', 'lesson'])->whereNumber('id')->name('classroom.move');

        Route::post('calendar', [EventController::class, 'store'])->name('events.store');
        Route::patch('calendar/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('calendar/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:content')->name('reviews.store');
        Route::delete('reviews', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('reviews/{review}/hide', [ReviewController::class, 'toggleHidden'])->name('reviews.hide');

        Route::post('media', [CommunityMediaController::class, 'store'])->name('media.store');
        Route::delete('media/{media}', [CommunityMediaController::class, 'destroy'])->name('media.destroy');
        Route::post('media/{media}/move', [CommunityMediaController::class, 'move'])->name('media.move');

        Route::get('settings', [CommunitySettingsController::class, 'edit'])->name('communities.settings');
        Route::post('settings', [CommunitySettingsController::class, 'update'])->name('communities.settings.update');
        Route::post('categories', [CommunitySettingsController::class, 'storeCategory'])->name('categories.store');
        Route::patch('categories/{category}', [CommunitySettingsController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [CommunitySettingsController::class, 'destroyCategory'])->name('categories.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
