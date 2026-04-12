<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\TimeEntryController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::apiResource('projects', ProjectController::class);
Route::apiResource('notes', NoteController::class);
Route::get('time-entries/summary', [TimeEntryController::class, 'summary']);
Route::apiResource('time-entries', TimeEntryController::class);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
