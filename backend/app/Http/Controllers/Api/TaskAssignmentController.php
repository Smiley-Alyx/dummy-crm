<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min(200, $perPage));

        $query = TaskAssignment::query()->orderByDesc('id');

        if ($request->filled('task_id')) {
            $query->where('task_id', $request->integer('task_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'capacity_hours_per_day' => ['required', 'numeric', 'min:0.25', 'max:24'],
        ]);

        $assignment = TaskAssignment::create($data);

        return response()->json($assignment, 201);
    }

    public function show(TaskAssignment $taskAssignment)
    {
        return $taskAssignment;
    }

    public function update(Request $request, TaskAssignment $taskAssignment)
    {
        $data = $request->validate([
            'capacity_hours_per_day' => ['sometimes', 'required', 'numeric', 'min:0.25', 'max:24'],
        ]);

        $taskAssignment->fill($data)->save();

        return $taskAssignment;
    }

    public function destroy(TaskAssignment $taskAssignment)
    {
        $taskAssignment->delete();

        return response()->noContent();
    }
}
