<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskAssignmentController;
use App\Http\Controllers\Api\TaskWorkLogController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::apiResource('projects', ProjectController::class);
Route::apiResource('notes', NoteController::class);
Route::get('time-entries/summary', [TimeEntryController::class, 'summary']);
Route::apiResource('time-entries', TimeEntryController::class);

Route::apiResource('departments', DepartmentController::class);

Route::get('shipments/{shipment}/gantt', [ShipmentController::class, 'gantt']);
Route::apiResource('shipments', ShipmentController::class);

Route::post('tasks/{task}/reschedule', [TaskController::class, 'reschedule']);
Route::apiResource('tasks', TaskController::class);

Route::apiResource('task-assignments', TaskAssignmentController::class);
Route::apiResource('task-work-logs', TaskWorkLogController::class);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
