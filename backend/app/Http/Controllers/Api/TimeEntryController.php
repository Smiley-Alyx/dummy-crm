<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = TimeEntry::query()->orderByDesc('entry_date')->orderByDesc('id');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->string('to'));
        }

        return $query->paginate($perPage);
    }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = TimeEntry::query();

        if (!empty($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        }

        if (!empty($data['from'])) {
            $query->whereDate('entry_date', '>=', $data['from']);
        }

        if (!empty($data['to'])) {
            $query->whereDate('entry_date', '<=', $data['to']);
        }

        $byDay = $query
            ->select([
                'entry_date',
                DB::raw('sum(minutes) as minutes'),
            ])
            ->groupBy('entry_date')
            ->orderBy('entry_date')
            ->get();

        $totalMinutes = (int) $byDay->sum('minutes');

        return response()->json([
            'total_minutes' => $totalMinutes,
            'by_day' => $byDay,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'entry_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = TimeEntry::create($data);

        return response()->json($entry, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TimeEntry $timeEntry)
    {
        return $timeEntry;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeEntry $timeEntry)
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'entry_date' => ['sometimes', 'required', 'date'],
            'minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $timeEntry->fill($data)->save();

        return $timeEntry;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeEntry $timeEntry)
    {
        $timeEntry->delete();

        return response()->noContent();
    }
}
