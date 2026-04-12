<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::apiResource('projects', ProjectController::class);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
