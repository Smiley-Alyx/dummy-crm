<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Shipment;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        return Project::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $project = Project::create($data);

        return response()->json($project, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return $project;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $project->fill($data)->save();

        return $project;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }

    public function ganttSheet(Request $request, Project $project)
    {
        $today = CarbonImmutable::today();

        $shipments = Shipment::query()
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignments.user', 'workLogs'])
            ->orderBy('shipment_id')
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

            $assignees = [];
            foreach ($task->assignments as $a) {
                $assignees[] = [
                    'user_id' => (int) $a->user_id,
                    'name' => (string) ($a->user?->name ?? ('#'.$a->user_id)),
                    'capacity_hours_per_day' => (float) $a->capacity_hours_per_day,
                ];
            }

            $data[] = [
                'id' => (int) $task->id,
                'project_id' => (int) $task->project_id,
                'shipment_id' => $task->shipment_id ? (int) $task->shipment_id : null,
                'order' => (int) $task->order,
                'title' => $task->title,
                'start_date' => $startDate->toDateString(),
                'effective_due_date' => $effectiveDueDate?->toDateString(),
                'control_date' => $controlDate?->toDateString(),
                'stage' => $task->stage,
                'capacity_hours_per_day' => $capacityPerDay,
                'remaining_hours' => round($remainingHours, 2),
                'assignees' => $assignees,
                'color' => $color,
                'risk' => $risk,
            ];
        }

        return response()->json([
            'project' => $project,
            'today' => $today->toDateString(),
            'shipments' => $shipments,
            'tasks' => $data,
        ]);
    }

    public function burndown(Request $request, Project $project)
    {
        $today = CarbonImmutable::today();

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['workLogs'])
            ->orderBy('shipment_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $totalEstimate = (float) $tasks->sum('estimate_hours');

        $minStart = $project->starts_on ? CarbonImmutable::parse($project->starts_on) : null;
        $maxDue = $project->ends_on ? CarbonImmutable::parse($project->ends_on) : null;

        foreach ($tasks as $task) {
            $startDate = CarbonImmutable::parse($task->start_date);
            $minStart = $minStart ? ($startDate->lt($minStart) ? $startDate : $minStart) : $startDate;

            $due = $task->due_date ? CarbonImmutable::parse($task->due_date) : null;
            if ($due) {
                $maxDue = $maxDue ? ($due->gt($maxDue) ? $due : $maxDue) : $due;
            }
        }

        $minStart = $minStart ?? $today;
        $maxDue = $maxDue ?? $today;

        $calendarDays = [];
        $d = $minStart;
        while ($d->lte($maxDue)) {
            if (self::isWorkday($d)) {
                $calendarDays[] = $d;
            }
            $d = $d->addDay();
        }

        if (count($calendarDays) === 0) {
            $calendarDays = [$today];
        }

        $actualByDateMinutes = [];
        foreach ($tasks as $task) {
            foreach ($task->workLogs as $log) {
                $k = CarbonImmutable::parse($log->work_date)->toDateString();
                $actualByDateMinutes[$k] = ($actualByDateMinutes[$k] ?? 0) + (int) $log->minutes;
            }
        }

        ksort($actualByDateMinutes);

        $burndownDays = max(1, count($calendarDays));
        $spentCumulative = 0.0;

        $points = [];
        for ($i = 0; $i < $burndownDays; $i++) {
            $plannedRemaining = $burndownDays > 0
                ? max(0.0, $totalEstimate - ($totalEstimate / $burndownDays) * $i)
                : $totalEstimate;

            $dateKey = isset($calendarDays[$i]) ? $calendarDays[$i]->toDateString() : null;
            if ($dateKey && isset($actualByDateMinutes[$dateKey])) {
                $spentCumulative += ($actualByDateMinutes[$dateKey] / 60.0);
            }
            $actualRemaining = max(0.0, $totalEstimate - $spentCumulative);

            $points[] = [
                'i' => $i,
                'date' => $dateKey,
                'planned_remaining_hours' => round($plannedRemaining, 2),
                'actual_remaining_hours' => round($actualRemaining, 2),
                'spent_cumulative_hours' => round($spentCumulative, 2),
            ];
        }

        return response()->json([
            'project' => $project,
            'today' => $today->toDateString(),
            'total_estimate_hours' => round($totalEstimate, 2),
            'points' => $points,
        ]);
    }

    public function ganttExport(Request $request, Project $project)
    {
        $today = CarbonImmutable::today();

        $shipments = Shipment::query()
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignments.user', 'workLogs'])
            ->orderBy('shipment_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $computed = [];
        $minStart = $project->starts_on ? CarbonImmutable::parse($project->starts_on) : null;
        $maxDue = $project->ends_on ? CarbonImmutable::parse($project->ends_on) : null;

        foreach ($tasks as $task) {
            $startDate = CarbonImmutable::parse($task->start_date);
            $capacityPerDay = (float) $task->assignments->sum('capacity_hours_per_day');
            $estimateHours = (float) $task->estimate_hours;

            $plannedEnd = null;
            $controlDate = null;
            $durationDays = null;

            if ($capacityPerDay > 0.0) {
                $durationDays = (int) ceil($estimateHours / $capacityPerDay);
                $durationDays = max(1, $durationDays);
                $plannedEnd = self::addWorkdays($startDate, $durationDays - 1);
                $controlDate = self::nextWorkday($plannedEnd);
            }

            $manualDueDate = $task->due_date ? CarbonImmutable::parse($task->due_date) : null;
            $effectiveDue = $manualDueDate ?? $plannedEnd;

            $spentMinutes = (int) $task->workLogs->sum('minutes');
            $spentHours = $spentMinutes / 60.0;
            $remainingHours = max(0.0, $estimateHours - $spentHours);

            $color = 'white';

            if ($today->lt($startDate)) {
                $color = 'white';
            } elseif ($capacityPerDay <= 0.0 || $effectiveDue === null) {
                $color = 'yellow';
            } else {
                if ($today->gt($effectiveDue) && $task->stage !== Task::STAGE_PROD_DONE) {
                    $color = 'red';
                } else {
                    $workdaysLeft = self::countWorkdaysInclusive($today, $effectiveDue);
                    $maxPossible = $workdaysLeft * $capacityPerDay;
                    if ($remainingHours > $maxPossible) {
                        $color = 'yellow';
                    } else {
                        $color = 'green';
                    }
                }
            }

            $minStart = $minStart ? ($startDate->lt($minStart) ? $startDate : $minStart) : $startDate;
            if ($effectiveDue) {
                $maxDue = $maxDue ? ($effectiveDue->gt($maxDue) ? $effectiveDue : $maxDue) : $effectiveDue;
            }

            $computed[] = [
                'task' => $task,
                'start_date' => $startDate,
                'planned_end_date' => $plannedEnd,
                'control_date' => $controlDate,
                'effective_due_date' => $effectiveDue,
                'capacity_hours_per_day' => $capacityPerDay,
                'estimate_hours' => $estimateHours,
                'spent_minutes' => $spentMinutes,
                'remaining_hours' => $remainingHours,
                'color' => $color,
            ];
        }

        $minStart = $minStart ?? $today;
        $maxDue = $maxDue ?? $today;

        $calendarDays = [];
        $d = $minStart;
        while ($d->lte($maxDue)) {
            if (self::isWorkday($d)) {
                $calendarDays[] = $d;
            }
            $d = $d->addDay();
        }
        if (count($calendarDays) === 0) {
            $calendarDays = [$today];
        }

        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Гант');

        $sheet1->setCellValue('A1', 'Задача');
        $sheet1->setCellValue('B1', 'Исполнитель');
        $sheet1->setCellValue('C1', 'Начало');
        $sheet1->setCellValue('D1', 'Осталось (час/день)');
        $sheet1->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet1->getStyle('A1:D1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getRowDimension(1)->setRowHeight(20);

        $startCol = 5;
        foreach ($calendarDays as $i => $day) {
            $col = Coordinate::stringFromColumnIndex($startCol + $i);
            $sheet1->setCellValue($col . '1', $day->format('d.m'));
            $sheet1->getStyle($col . '1')->getAlignment()->setTextRotation(90);
            $sheet1->getColumnDimension($col)->setWidth(3);
        }

        $sheet1->getColumnDimension('A')->setWidth(54);
        $sheet1->getColumnDimension('B')->setWidth(32);
        $sheet1->getColumnDimension('C')->setWidth(12);
        $sheet1->getColumnDimension('D')->setWidth(20);

        $row = 2;
        $curShipmentId = null;

        foreach ($computed as $item) {
            /** @var Task $task */
            $task = $item['task'];
            $shipmentId = $task->shipment_id;

            if ($shipmentId !== $curShipmentId) {
                $curShipmentId = $shipmentId;
                $shipmentTitle = $shipmentId && $shipments->has($shipmentId)
                    ? (string) $shipments->get($shipmentId)->title
                    : 'Без отгрузки';

                $lastCol = Coordinate::stringFromColumnIndex($startCol + count($calendarDays) - 1);
                $sheet1->mergeCells('A' . $row . ':' . $lastCol . $row);
                $sheet1->setCellValue('A' . $row, $shipmentTitle);
                $sheet1->getStyle('A' . $row)->getFont()->setBold(true);
                $sheet1->getRowDimension($row)->setRowHeight(20);
                $sheet1->getStyle('A' . $row . ':' . $lastCol . $row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F3F4');

                $row++;
            }

            $assignees = $task->assignments
                ->map(fn ($a) => $a->user ? ($a->user->name . ' (' . $a->capacity_hours_per_day . ')') : (string) $a->user_id)
                ->implode(', ');

            $capacity = (float) $item['capacity_hours_per_day'];
            $remaining = (float) $item['remaining_hours'];

            $sheet1->setCellValue('A' . $row, $task->title);
            $sheet1->setCellValue('B' . $row, $assignees);
            $sheet1->setCellValue('C' . $row, CarbonImmutable::parse($item['start_date'])->format('d.m.Y'));
            $sheet1->setCellValue('D' . $row, number_format($remaining, 2, '.', '') . '/' . number_format($capacity, 2, '.', ''));

            $sheet1->getStyle('A' . $row . ':D' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet1->getRowDimension($row)->setRowHeight(18);

            $startDate = $item['start_date'];
            $due = $item['effective_due_date'];
            $control = $item['control_date'];

            foreach ($calendarDays as $i => $day) {
                $col = Coordinate::stringFromColumnIndex($startCol + $i);
                $cell = $col . $row;

                $fillColor = 'FFFFFF';

                if ($day->lt($startDate)) {
                    $fillColor = 'FFFFFF';
                } elseif ($due && $day->lte($due)) {
                    $fillColor = match ($item['color']) {
                        'green' => 'C6EFCE',
                        'yellow' => 'FFEB9C',
                        'red' => 'FFC7CE',
                        default => 'FFFFFF',
                    };
                } else {
                    $fillColor = 'FFFFFF';
                }

                $sheet1->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fillColor);

                if ($control && $day->equalTo($control)) {
                    $sheet1->getStyle($cell)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
                }
            }

            $row++;
        }

        $fileName = 'project_' . $project->id . '_gantt.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
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
        $added = 0;

        while ($added < $days) {
            $d = $d->addDay();
            if (self::isWorkday($d)) {
                $added++;
            }
        }

        return $d;
    }

    private static function countWorkdaysInclusive(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $count = 0;
        $d = $start;
        while ($d->lte($end)) {
            if (self::isWorkday($d)) {
                $count++;
            }
            $d = $d->addDay();
        }

        return $count;
    }
}
