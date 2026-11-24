<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ResetController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/login', [AuthController::class, 'login']);

// Public event portal, ticket & registration
Route::get('/public/data', [PublicController::class, 'getPublicData']);
Route::post('/public/register', [PublicController::class, 'register']);
Route::get('/public/invitations/{code}', [PublicController::class, 'getInvitation']);
Route::post('/public/rsvp/{code}', [PublicController::class, 'submitRsvp']);
Route::post('/public/schools/{code}/members', [PublicController::class, 'updateSchoolMembers']);
Route::post('/public/check-in', [CheckInController::class, 'checkIn']);
Route::post('/public/invitations/{code}/check-in', [CheckInController::class, 'checkIn']);

// Messages (Ucapan & Doa) — public read + write
Route::get('/messages', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);

// Public Settings & Stats
Route::get('/settings', [SettingController::class, 'show']);
Route::get('/stats', [StatsController::class, 'index']);

// Scanner verification & Check-in
Route::get('/check-in/verify/{identifier}', [CheckInController::class, 'verify']);
Route::post('/check-in', [CheckInController::class, 'checkIn']);

// Public invitation list / search & management
Route::get('/invitations', [InvitationController::class, 'index']);
Route::get('/invitations/{id}', [InvitationController::class, 'show']);
Route::post('/invitations', [InvitationController::class, 'store']);
Route::put('/invitations/{id}', [InvitationController::class, 'update']);
Route::delete('/invitations/{id}', [InvitationController::class, 'destroy']);
Route::post('/invitations/import', [InvitationController::class, 'import']);

// Settings & Reports management
Route::put('/settings', [SettingController::class, 'update']);
Route::get('/reports/export', [StatsController::class, 'export']);

// Message moderation & Reset
Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
Route::post('/reset', [ResetController::class, 'reset']);

// Activities
Route::get('/activities', [ActivityController::class, 'index']);
Route::post('/activities', [ActivityController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Admin & Protected / Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth - current user & logout
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
