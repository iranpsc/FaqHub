<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('/redirect', [AuthController::class, 'redirect'])->name('auth.redirect');
        // Route::get('/callback', [AuthController::class, 'callback'])->name('auth.callback');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Question recommendation and popular routes (must be before resource routes)
Route::prefix('questions')->group(function () {
    Route::get('/recommended', [DashboardController::class, 'recommendedQuestions']);
    Route::get('/popular', [DashboardController::class, 'popularQuestions']);
    Route::get('/search', [QuestionController::class, 'search']);
});

// Category popular route (must be before resource routes)
Route::get('categories/popular', [CategoryController::class, 'popular']);

Route::post('questions/{question}/vote', [QuestionController::class, 'vote']);
Route::post('questions/{question}/publish', [QuestionController::class, 'publish']);
Route::post('questions/{question}/pin', [QuestionController::class, 'pin']);
Route::delete('questions/{question}/pin', [QuestionController::class, 'unpin']);
Route::post('questions/{question}/feature', [QuestionController::class, 'feature']);
Route::delete('questions/{question}/feature', [QuestionController::class, 'unfeature']);

Route::get('questions', [QuestionController::class, 'index']);
Route::post('questions', [QuestionController::class, 'store']);
Route::get('questions/{question:slug}', [QuestionController::class, 'show']);
Route::put('questions/{question}', [QuestionController::class, 'update']);
Route::delete('questions/{question}', [QuestionController::class, 'destroy']);

Route::get('tags/{tag:slug}/questions', [TagController::class, 'questions']);
Route::apiResource('tags', TagController::class)->only(['index']);

Route::apiResource('questions.answers', AnswerController::class)->shallow()->parameters(['questions' => 'question:id']);

Route::post('answers/{answer}/publish', [AnswerController::class, 'publish']);
Route::post('answers/{answer}/toggle-correctness', [AnswerController::class, 'toggleCorrectness']);
Route::apiResource('questions.comments', CommentController::class)->shallow()->only(['index', 'store']);
Route::apiResource('answers.comments', CommentController::class)->shallow()->only(['index', 'store']);
Route::apiResource('comments', CommentController::class)->shallow()->only(['update', 'destroy']);
Route::post('comments/{comment}/vote', [CommentController::class, 'vote']);
Route::post('comments/{comment}/publish', [CommentController::class, 'publish']);
Route::post('answers/{answer}/vote', [AnswerController::class, 'vote']);

Route::get('categories/{category:slug}/questions', [CategoryController::class, 'questions']);
Route::apiResource('categories', CategoryController::class)->scoped(['category' => 'slug']);

// Authors routes
Route::apiResource('authors', AuthorController::class)
    ->only(['index', 'show'])
    ->scoped(['author' => 'username']);
Route::get('authors/{user:username}/questions', [AuthorController::class, 'questions']);

// User profile routes
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/stats', [UserController::class, 'stats']);
    Route::get('/activity', [UserController::class, 'activity']);
    Route::post('/update-image', [UserController::class, 'updateImage']);
    Route::post('/settings', [UserController::class, 'updateSettings']);
});

// File upload routes
Route::middleware('auth.optional')->prefix('upload')->group(function () {
    Route::post('/tinymce-image', [FileUploadController::class, 'uploadTinyMCEImage']);
    Route::post('/quill-image', [FileUploadController::class, 'uploadQuillImage']);
    Route::post('/file', [FileUploadController::class, 'uploadFile']);
    Route::delete('/file', [FileUploadController::class, 'deleteFile']);
});

// Dashboard routes
Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/active-users', [DashboardController::class, 'activeUsers']);
    Route::get('/activity', [DashboardController::class, 'activity']);
});
