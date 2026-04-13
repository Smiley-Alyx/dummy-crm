<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function export(Request $request, Shipment $shipment)
    {
        $today = CarbonImmutable::today();

        $tasks = Task::query()
            ->where('shipment_id', $shipment->id)
            ->with(['assignments.user', 'workLogs'])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $computed = [];
        $minStart = null;
        $maxDue = null;

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
            $risk = null;

            if ($today->lt($startDate)) {
                $color = 'white';
            } elseif ($capacityPerDay <= 0.0 || $effectiveDue === null) {
                $color = 'yellow';
                $risk = 'no_capacity';
            } else {
                if ($today->gt($effectiveDue) && $task->stage !== Task::STAGE_PROD_DONE) {
                    $color = 'red';
                    $risk = 'overdue';
                } else {
                    $workdaysLeft = self::countWorkdaysInclusive($today, $effectiveDue);
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
                'risk' => $risk,
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

        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Лист1');

        $sheet1->setCellValue('A1', 'Отгрузки');
        $sheet1->setCellValue('B1', 'Исполнитель');
        $sheet1->setCellValue('C1', 'Осталось (час/день)');

        $sheet1->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet1->getStyle('A1:C1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getRowDimension(1)->setRowHeight(20);

        $startCol = 4;
        foreach ($calendarDays as $i => $day) {
            $col = Coordinate::stringFromColumnIndex($startCol + $i);
            $sheet1->setCellValue($col . '1', $day->format('d.m'));
            $sheet1->getStyle($col . '1')->getAlignment()->setTextRotation(90);
            $sheet1->getColumnDimension($col)->setWidth(3);
        }

        $sheet1->getColumnDimension('A')->setWidth(54);
        $sheet1->getColumnDimension('B')->setWidth(32);
        $sheet1->getColumnDimension('C')->setWidth(18);

        $row = 2;
        foreach ($computed as $item) {
            /** @var Task $task */
            $task = $item['task'];

            $assignees = $task->assignments
                ->map(fn ($a) => $a->user ? ($a->user->name . ' (' . $a->capacity_hours_per_day . ')') : (string) $a->user_id)
                ->implode(', ');

            $capacity = (float) $item['capacity_hours_per_day'];
            $remaining = (float) $item['remaining_hours'];
            $sheet1->setCellValue('A' . $row, $task->title);
            $sheet1->setCellValue('B' . $row, $assignees);
            $sheet1->setCellValue('C' . $row, number_format($remaining, 2, '.', '') . '/' . number_format($capacity, 2, '.', ''));

            $sheet1->getStyle('A' . $row . ':C' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
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

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Лист2');
        $sheet2->setCellValue('A1', 'Дни');
        $sheet2->setCellValue('B1', 'Количество часов');
        $sheet2->setCellValue('C1', 'Факт осталось');
        $sheet2->getStyle('A1:C1')->getFont()->setBold(true);

        $totalEstimate = (float) collect($computed)->sum('estimate_hours');

        $actualByDate = [];
        foreach ($computed as $item) {
            /** @var Task $task */
            $task = $item['task'];
            foreach ($task->workLogs as $log) {
                $k = CarbonImmutable::parse($log->work_date)->toDateString();
                $actualByDate[$k] = ($actualByDate[$k] ?? 0) + (int) $log->minutes;
            }
        }

        ksort($actualByDate);
        $spentCumulative = 0.0;

        if (count($calendarDays) === 0) {
            $calendarDays = [CarbonImmutable::today()];
        }

        $burndownDays = max(1, count($calendarDays));
        $row2 = 2;
        for ($i = 0; $i < $burndownDays; $i++) {
            $plannedRemaining = max(0.0, $totalEstimate - ($totalEstimate / $burndownDays) * $i);

            $dateKey = isset($calendarDays[$i]) ? $calendarDays[$i]->toDateString() : null;
            if ($dateKey && isset($actualByDate[$dateKey])) {
                $spentCumulative += ($actualByDate[$dateKey] / 60.0);
            }
            $actualRemaining = max(0.0, $totalEstimate - $spentCumulative);

            $sheet2->setCellValue('A' . $row2, $i);
            $sheet2->setCellValue('B' . $row2, round($plannedRemaining, 2));
            $sheet2->setCellValue('C' . $row2, round($actualRemaining, 2));
            $row2++;
        }

        $sheet2->getColumnDimension('A')->setWidth(10);
        $sheet2->getColumnDimension('B')->setWidth(18);
        $sheet2->getColumnDimension('C')->setWidth(18);

        $fileName = 'shipment_' . $shipment->id . '_gantt.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function burndown(Request $request, Shipment $shipment)
    {
        $today = CarbonImmutable::today();

        $tasks = Task::query()
            ->where('shipment_id', $shipment->id)
            ->with(['workLogs'])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $totalEstimate = (float) $tasks->sum('estimate_hours');

        $minStart = null;
        $maxDue = null;

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
            'shipment' => $shipment,
            'today' => $today->toDateString(),
            'total_estimate_hours' => round($totalEstimate, 2),
            'points' => $points,
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
