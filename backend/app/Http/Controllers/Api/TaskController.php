<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskReschedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = Task::query()->orderBy('shipment_id')->orderBy('order')->orderBy('id');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('shipment_id')) {
            $query->where('shipment_id', $request->integer('shipment_id'));
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->string('stage'));
        }

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'title' => ['required', 'string', 'max:255'],
            'acceptance_criteria' => ['nullable', 'string'],
            'estimate_hours' => ['required', 'numeric', 'min:0.25'],
            'start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'stage' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $task = Task::create($data);

        return response()->json($task, 201);
    }

    public function show(Task $task)
    {
        return $task->load(['assignments', 'workLogs', 'reschedules']);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'shipment_id' => ['sometimes', 'required', 'integer', 'exists:shipments,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'acceptance_criteria' => ['sometimes', 'nullable', 'string'],
            'estimate_hours' => ['sometimes', 'required', 'numeric', 'min:0.25'],
            'start_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'stage' => ['sometimes', 'nullable', 'string', 'max:50'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $stageBefore = $task->stage;

        $task->fill($data)->save();

        if (array_key_exists('stage', $data) && $data['stage'] !== $stageBefore) {
            $task->stage_changed_at = CarbonImmutable::now();
            $task->save();
        }

        return $task;
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->noContent();
    }

    public function reschedule(Request $request, Task $task)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string'],
        ]);

        $reschedule = TaskReschedule::create([
            'task_id' => $task->id,
            'from_start_date' => $task->start_date,
            'to_start_date' => $data['start_date'],
            'from_due_date' => $task->due_date,
            'to_due_date' => $data['due_date'] ?? null,
            'reason' => $data['reason'],
            'created_by' => null,
        ]);

        $task->start_date = $data['start_date'];
        $task->due_date = $data['due_date'] ?? null;
        $task->save();

        return response()->json([
            'task' => $task,
            'reschedule' => $reschedule,
        ]);
    }
}
