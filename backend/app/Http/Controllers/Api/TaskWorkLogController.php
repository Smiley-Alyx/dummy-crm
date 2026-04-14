<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskWorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function report(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $q = TaskWorkLog::query()
            ->select([
                'tasks.project_id as project_id',
                'shipments.id as shipment_id',
                'shipments.title as shipment_title',
                'tasks.id as task_id',
                'tasks.title as task_title',
                DB::raw('sum(task_work_logs.minutes) as minutes'),
            ])
            ->join('tasks', 'tasks.id', '=', 'task_work_logs.task_id')
            ->leftJoin('shipments', 'shipments.id', '=', 'tasks.shipment_id')
            ->where('task_work_logs.user_id', $data['user_id'])
            ->groupBy('tasks.project_id', 'shipments.id', 'shipments.title', 'tasks.id', 'tasks.title')
            ->orderBy('tasks.project_id')
            ->orderBy('shipments.id')
            ->orderBy('tasks.id');

        if (!empty($data['project_id'])) {
            $q->where('tasks.project_id', $data['project_id']);
        }
        if (!empty($data['from'])) {
            $q->whereDate('task_work_logs.work_date', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $q->whereDate('task_work_logs.work_date', '<=', $data['to']);
        }

        $rows = $q->get();

        $projects = DB::table('projects')
            ->select(['id', 'name'])
            ->when(!empty($data['project_id']), fn ($sub) => $sub->where('id', $data['project_id']))
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $outProjects = [];
        $totalMinutes = 0;

        foreach ($rows as $r) {
            $projectId = (int) $r->project_id;
            $shipmentId = $r->shipment_id !== null ? (int) $r->shipment_id : null;
            $taskId = (int) $r->task_id;
            $minutes = (int) $r->minutes;

            $totalMinutes += $minutes;

            if (!isset($outProjects[$projectId])) {
                $outProjects[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => (string) ($projects[$projectId]->name ?? ('#' . $projectId)),
                    'minutes' => 0,
                    'shipments' => [],
                ];
            }
            $outProjects[$projectId]['minutes'] += $minutes;

            $shipmentKey = $shipmentId === null ? 'null' : (string) $shipmentId;
            if (!isset($outProjects[$projectId]['shipments'][$shipmentKey])) {
                $outProjects[$projectId]['shipments'][$shipmentKey] = [
                    'shipment_id' => $shipmentId,
                    'shipment_title' => $shipmentId === null ? 'Без отгрузки' : (string) $r->shipment_title,
                    'minutes' => 0,
                    'tasks' => [],
                ];
            }
            $outProjects[$projectId]['shipments'][$shipmentKey]['minutes'] += $minutes;

            $outProjects[$projectId]['shipments'][$shipmentKey]['tasks'][] = [
                'task_id' => $taskId,
                'task_title' => (string) $r->task_title,
                'minutes' => $minutes,
            ];
        }

        $projectsList = array_values(array_map(function (array $p) {
            $p['shipments'] = array_values($p['shipments']);
            return $p;
        }, $outProjects));

        return response()->json([
            'user_id' => (int) $data['user_id'],
            'project_id' => !empty($data['project_id']) ? (int) $data['project_id'] : null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'total_minutes' => $totalMinutes,
            'projects' => $projectsList,
        ]);
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
