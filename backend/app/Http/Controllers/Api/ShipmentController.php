<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = Shipment::query()->orderByDesc('id');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_due_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
        ]);

        $shipment = Shipment::create($data);

        return response()->json($shipment, 201);
    }

    public function show(Shipment $shipment)
    {
        return $shipment;
    }

    public function update(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'planned_start_date' => ['sometimes', 'nullable', 'date'],
            'planned_due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:planned_start_date'],
        ]);

        $shipment->fill($data)->save();

        return $shipment;
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return response()->noContent();
    }

    public function gantt(Request $request, Shipment $shipment)
    {
        $today = CarbonImmutable::today();

        $tasks = Task::query()
            ->where('shipment_id', $shipment->id)
            ->with(['assignments', 'workLogs'])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $data = [];

        foreach ($tasks as $task) {
            $startDate = CarbonImmutable::parse($task->start_date);

            $capacityPerDay = (float) $task->assignments->sum('capacity_hours_per_day');
            $spentMinutes = (int) $task->workLogs->sum('minutes');
            $spentHours = $spentMinutes / 60.0;
            $estimateHours = (float) $task->estimate_hours;
            $remainingHours = max(0.0, $estimateHours - $spentHours);

            $durationDays = null;
            $plannedEnd = null;
            $controlDate = null;

            if ($capacityPerDay > 0.0) {
                $durationDays = (int) ceil($estimateHours / $capacityPerDay);
                $durationDays = max(1, $durationDays);

                $plannedEnd = self::addWorkdays($startDate, $durationDays - 1);
                $controlDate = self::nextWorkday($plannedEnd);
            }

            $manualDueDate = $task->due_date ? CarbonImmutable::parse($task->due_date) : null;
            $effectiveDueDate = $manualDueDate ?? $plannedEnd;

            $color = 'white';
            $risk = null;

            if ($today->lt($startDate)) {
                $color = 'white';
            } elseif ($capacityPerDay <= 0.0 || $effectiveDueDate === null) {
                $color = 'yellow';
                $risk = 'no_capacity';
            } else {
                if ($today->gt($effectiveDueDate) && $task->stage !== Task::STAGE_PROD_DONE) {
                    $color = 'red';
                    $risk = 'overdue';
                } else {
                    $workdaysLeft = self::countWorkdaysInclusive($today, $effectiveDueDate);
                    $maxPossible = $workdaysLeft * $capacityPerDay;

                    if ($remainingHours > $maxPossible) {
                        $color = 'yellow';
                        $risk = 'at_risk';
                    } else {
                        $color = 'green';
                        $risk = 'on_track';
                    }
                }
            }

            $data[] = [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'shipment_id' => $task->shipment_id,
                'title' => $task->title,
                'acceptance_criteria' => $task->acceptance_criteria,
                'estimate_hours' => $estimateHours,
                'start_date' => $startDate->toDateString(),
                'due_date' => $manualDueDate?->toDateString(),
                'stage' => $task->stage,
                'order' => (int) $task->order,

                'capacity_hours_per_day' => $capacityPerDay,
                'spent_minutes' => $spentMinutes,
                'remaining_hours' => $remainingHours,

                'duration_workdays' => $durationDays,
                'planned_end_date' => $plannedEnd?->toDateString(),
                'control_date' => $controlDate?->toDateString(),
                'effective_due_date' => $effectiveDueDate?->toDateString(),

                'color' => $color,
                'risk' => $risk,
            ];
        }

        return response()->json([
            'shipment' => $shipment,
            'today' => $today->toDateString(),
            'tasks' => $data,
        ]);
    }

    private static function isWorkday(CarbonImmutable $date): bool
    {
        return $date->isWeekday();
    }

    private static function nextWorkday(CarbonImmutable $date): CarbonImmutable
    {
        $d = $date->addDay();
        while (!self::isWorkday($d)) {
            $d = $d->addDay();
        }
        return $d;
    }

    private static function addWorkdays(CarbonImmutable $start, int $days): CarbonImmutable
    {
        $d = $start;
        $remaining = $days;

        while ($remaining > 0) {
            $d = $d->addDay();
            if (self::isWorkday($d)) {
                $remaining--;
            }
        }

        return $d;
    }

    private static function countWorkdaysInclusive(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }

        $d = $from;
        $count = 0;

        while ($d->lte($to)) {
            if (self::isWorkday($d)) {
                $count++;
            }
            $d = $d->addDay();
        }

        return $count;
    }
}
