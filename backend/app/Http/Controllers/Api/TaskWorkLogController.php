<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskWorkLog;
use Illuminate\Http\Request;

class TaskWorkLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min(200, $perPage));

        $query = TaskWorkLog::query()->orderByDesc('work_date')->orderByDesc('id');

        if ($request->filled('task_id')) {
            $query->where('task_id', $request->integer('task_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('work_date', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('work_date', '<=', $request->string('to'));
        }

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $log = TaskWorkLog::create($data);

        return response()->json($log, 201);
    }

    public function show(TaskWorkLog $taskWorkLog)
    {
        return $taskWorkLog;
    }

    public function update(Request $request, TaskWorkLog $taskWorkLog)
    {
        $data = $request->validate([
            'work_date' => ['sometimes', 'required', 'date'],
            'minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $taskWorkLog->fill($data)->save();

        return $taskWorkLog;
    }

    public function destroy(TaskWorkLog $taskWorkLog)
    {
        $taskWorkLog->delete();

        return response()->noContent();
    }
}
