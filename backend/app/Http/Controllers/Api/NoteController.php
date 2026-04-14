<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = Note::query()
            ->with(['project', 'task.shipment'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('id');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('task_id')) {
            $query->where('task_id', $request->integer('task_id'));
        }

        return $query->paginate($perPage);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['task_id']) && empty($data['project_id'])) {
            $data['project_id'] = (int) \App\Models\Task::query()->whereKey($data['task_id'])->value('project_id');
        }

        $note = Note::create($data)->load(['project', 'task.shipment']);

        return response()->json($note, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note)
    {
        return $note->load(['project', 'task.shipment']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'task_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'is_pinned' => ['sometimes', 'nullable', 'boolean'],
        ]);

        if (array_key_exists('task_id', $data) && !empty($data['task_id']) && !array_key_exists('project_id', $data)) {
            $data['project_id'] = (int) \App\Models\Task::query()->whereKey($data['task_id'])->value('project_id');
        }

        $note->fill($data)->save();

        return $note->load(['project', 'task.shipment']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        $note->delete();

        return response()->noContent();
    }
}
