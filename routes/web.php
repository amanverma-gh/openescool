<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunitySettingsController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CommunityController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
});

Route::get('u/{user:username}', [UserProfileController::class, 'show'])->name('users.show');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [CommunityController::class, 'dashboard'])->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('communities/create', [CommunityController::class, 'create'])->name('communities.create');
    Route::post('communities', [CommunityController::class, 'store'])->name('communities.store');
});

Route::prefix('c/{community}')->group(function () {
    Route::get('/', [FeedController::class, 'index'])->name('communities.feed');
    Route::get('about', [CommunityController::class, 'show'])->name('communities.about');
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');

    Route::middleware('auth')->group(function () {
        Route::post('join', [MembershipController::class, 'store'])->name('communities.join');
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

        Route::get('settings', [CommunitySettingsController::class, 'edit'])->name('communities.settings');
        Route::post('settings', [CommunitySettingsController::class, 'update'])->name('communities.settings.update');
        Route::post('categories', [CommunitySettingsController::class, 'storeCategory'])->name('categories.store');
        Route::patch('categories/{category}', [CommunitySettingsController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [CommunitySettingsController::class, 'destroyCategory'])->name('categories.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
