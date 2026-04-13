<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Shipment;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskWorkLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $devs = [
            ['name' => 'Dev 1', 'email' => 'dev1@example.test'],
            ['name' => 'Dev 2', 'email' => 'dev2@example.test'],
            ['name' => 'Dev 3', 'email' => 'dev3@example.test'],
            ['name' => 'Dev 4', 'email' => 'dev4@example.test'],
        ];

        $users = collect($devs)->map(function (array $d) {
            return User::firstOrCreate(
                ['email' => $d['email']],
                ['name' => $d['name'], 'password' => 'password']
            );
        })->values();

        $project = Project::firstOrCreate(
            ['name' => 'Demo Project'],
            [
                'description' => 'Demo data for UI',
                'status' => 'active',
                'starts_on' => null,
                'ends_on' => null,
            ]
        );

        $today = CarbonImmutable::today();

        for ($s = 1; $s <= 5; $s++) {
            $shipment = Shipment::firstOrCreate(
                ['project_id' => $project->id, 'title' => 'Demo Shipment ' . $s],
                [
                    'description' => null,
                    'planned_start_date' => $today->subDays(7)->toDateString(),
                    'planned_due_date' => $today->addDays(14)->toDateString(),
                ]
            );

            if ($shipment->tasks()->exists()) {
                continue;
            }

            $tasksCount = random_int(3, 6);

            for ($i = 1; $i <= $tasksCount; $i++) {
                $estimates = [4, 6, 8, 10, 12, 16];
                $estimate = $estimates[array_rand($estimates)];

                $start = $today->subDays(random_int(2, 10))->addDays(random_int(0, 10));
                $stage = [
                    Task::STAGE_PLANNED,
                    Task::STAGE_IN_PROGRESS,
                    Task::STAGE_DEV_DONE,
                    Task::STAGE_QA_DONE,
                    Task::STAGE_PROD_DONE,
                ][random_int(0, 4)];

                $task = Task::create([
                    'project_id' => $project->id,
                    'shipment_id' => $shipment->id,
                    'title' => 'Task ' . $s . '.' . $i . ' — ' . Str::ucfirst(Str::random(6)),
                    'acceptance_criteria' => null,
                    'estimate_hours' => $estimate,
                    'start_date' => $start->toDateString(),
                    'due_date' => null,
                    'stage' => $stage,
                    'order' => $i,
                    'stage_changed_at' => now(),
                ]);

                $assignees = $users->shuffle()->take(random_int(1, 2));

                foreach ($assignees as $u) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $u->id,
                        'capacity_hours_per_day' => random_int(2, 6),
                    ]);
                }

                $logDays = random_int(0, 6);
                for ($d = 0; $d < $logDays; $d++) {
                    $workDate = $today->subDays($d);
                    if (!$workDate->isWeekday()) {
                        continue;
                    }

                    foreach ($assignees as $u) {
                        if (random_int(0, 100) < 45) {
                            continue;
                        }

                        TaskWorkLog::create([
                            'task_id' => $task->id,
                            'user_id' => $u->id,
                            'work_date' => $workDate->toDateString(),
                            'minutes' => random_int(30, 240),
                            'comment' => null,
                        ]);
                    }
                }
            }
        }
    }
}
