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
            ['name' => 'Иванов Иван (Frontend)', 'email' => 'ivanov.front@example.test'],
            ['name' => 'Петров Пётр (Backend)', 'email' => 'petrov.back@example.test'],
            ['name' => 'Сидорова Анна (QA)', 'email' => 'sidorova.qa@example.test'],
            ['name' => 'Кузнецов Сергей (Fullstack)', 'email' => 'kuznetsov.full@example.test'],
        ];

        $users = collect($devs)->map(function (array $d) {
            return User::firstOrCreate(
                ['email' => $d['email']],
                ['name' => $d['name'], 'password' => 'password']
            );
        })->values();

        $project = Project::firstOrCreate(
            ['name' => 'ООО «Рога и Копыта»'],
            [
                'description' => 'Демо-данные для UI (тестовый проект)',
                'status' => 'active',
                'starts_on' => null,
                'ends_on' => null,
            ]
        );

        $today = CarbonImmutable::today();

        if (!$project->starts_on) {
            $project->starts_on = $today->subDays(14)->toDateString();
        }
        if (!$project->ends_on) {
            $project->ends_on = $today->addDays(30)->toDateString();
        }
        $project->save();

        $ivanov = $users->firstWhere('email', 'ivanov.front@example.test');
        $petrov = $users->firstWhere('email', 'petrov.back@example.test');
        $sidorova = $users->firstWhere('email', 'sidorova.qa@example.test');
        $kuznetsov = $users->firstWhere('email', 'kuznetsov.full@example.test');

        $fixedShipment = Shipment::firstOrCreate(
            ['project_id' => $project->id, 'title' => 'Отгрузка ООО Рога и Копыта — 100500 рублей'],
            [
                'description' => 'Ключевая отгрузка для демо',
                'planned_start_date' => $today->subDays(10)->toDateString(),
                'planned_due_date' => $today->addDays(20)->toDateString(),
            ]
        );

        if (!$fixedShipment->tasks()->exists()) {
            $fixedTasks = [
                [
                    'title' => 'Сделать чекаут',
                    'estimate_hours' => 16,
                    'stage' => Task::STAGE_IN_PROGRESS,
                    'start_date' => $today->subDays(5)->toDateString(),
                    'order' => 1,
                    'assignees' => [
                        ['user' => $ivanov, 'cap' => 4],
                        ['user' => $petrov, 'cap' => 3],
                    ],
                ],
                [
                    'title' => 'Сделать личный кабинет',
                    'estimate_hours' => 24,
                    'stage' => Task::STAGE_PLANNED,
                    'start_date' => $today->addDays(1)->toDateString(),
                    'order' => 2,
                    'assignees' => [
                        ['user' => $ivanov, 'cap' => 4],
                    ],
                ],
                [
                    'title' => 'API: заказы и статусы',
                    'estimate_hours' => 12,
                    'stage' => Task::STAGE_DEV_DONE,
                    'start_date' => $today->subDays(8)->toDateString(),
                    'order' => 3,
                    'assignees' => [
                        ['user' => $petrov, 'cap' => 5],
                    ],
                ],
                [
                    'title' => 'QA: регресс по чекауту',
                    'estimate_hours' => 8,
                    'stage' => Task::STAGE_PLANNED,
                    'start_date' => $today->addDays(4)->toDateString(),
                    'order' => 4,
                    'assignees' => [
                        ['user' => $sidorova, 'cap' => 4],
                    ],
                ],
            ];

            foreach ($fixedTasks as $ft) {
                $task = Task::create([
                    'project_id' => $project->id,
                    'shipment_id' => $fixedShipment->id,
                    'title' => $ft['title'],
                    'acceptance_criteria' => null,
                    'estimate_hours' => $ft['estimate_hours'],
                    'start_date' => $ft['start_date'],
                    'due_date' => null,
                    'stage' => $ft['stage'],
                    'order' => $ft['order'],
                    'stage_changed_at' => now(),
                ]);

                foreach ($ft['assignees'] as $a) {
                    if (!$a['user']) {
                        continue;
                    }
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $a['user']->id,
                        'capacity_hours_per_day' => $a['cap'],
                    ]);
                }

                foreach ([0, 1, 2, 3, 4, 5] as $d) {
                    $workDate = $today->subDays($d);
                    if (!$workDate->isWeekday()) {
                        continue;
                    }

                    foreach ($ft['assignees'] as $a) {
                        if (!$a['user']) {
                            continue;
                        }
                        if ($task->stage === Task::STAGE_PLANNED) {
                            continue;
                        }

                        TaskWorkLog::create([
                            'task_id' => $task->id,
                            'user_id' => $a['user']->id,
                            'work_date' => $workDate->toDateString(),
                            'minutes' => random_int(30, 180),
                            'comment' => null,
                        ]);
                    }
                }
            }
        }

        $moreShipments = [
            [
                'title' => 'Отгрузка: Интеграция оплаты и фискализация',
                'planned_start' => $today->subDays(6)->toDateString(),
                'planned_due' => $today->addDays(12)->toDateString(),
                'tasks' => [
                    [
                        'title' => 'Интеграция платёжного провайдера',
                        'estimate_hours' => 20,
                        'stage' => Task::STAGE_IN_PROGRESS,
                        'start_date' => $today->subDays(4)->toDateString(),
                        'order' => 1,
                        'assignees' => [
                            ['user' => $kuznetsov, 'cap' => 6],
                            ['user' => $petrov, 'cap' => 2],
                        ],
                    ],
                    [
                        'title' => 'Фронт: страница оплаты',
                        'estimate_hours' => 12,
                        'stage' => Task::STAGE_IN_PROGRESS,
                        'start_date' => $today->subDays(2)->toDateString(),
                        'order' => 2,
                        'assignees' => [
                            ['user' => $ivanov, 'cap' => 5],
                        ],
                    ],
                    [
                        'title' => 'Логи и аудит платежей',
                        'estimate_hours' => 10,
                        'stage' => Task::STAGE_PLANNED,
                        'start_date' => $today->addDays(3)->toDateString(),
                        'order' => 3,
                        'assignees' => [
                            ['user' => $petrov, 'cap' => 4],
                        ],
                    ],
                    [
                        'title' => 'QA: сценарии оплаты',
                        'estimate_hours' => 6,
                        'stage' => Task::STAGE_PLANNED,
                        'start_date' => $today->addDays(5)->toDateString(),
                        'order' => 4,
                        'assignees' => [
                            ['user' => $sidorova, 'cap' => 3],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Отгрузка: Админка и права доступа',
                'planned_start' => $today->subDays(12)->toDateString(),
                'planned_due' => $today->addDays(6)->toDateString(),
                'tasks' => [
                    [
                        'title' => 'Роли пользователей (admin/manager/operator)',
                        'estimate_hours' => 14,
                        'stage' => Task::STAGE_DEV_DONE,
                        'start_date' => $today->subDays(10)->toDateString(),
                        'order' => 1,
                        'assignees' => [
                            ['user' => $petrov, 'cap' => 5],
                        ],
                    ],
                    [
                        'title' => 'UI: таблица пользователей и фильтры',
                        'estimate_hours' => 10,
                        'stage' => Task::STAGE_QA_DONE,
                        'start_date' => $today->subDays(9)->toDateString(),
                        'order' => 2,
                        'assignees' => [
                            ['user' => $ivanov, 'cap' => 4],
                        ],
                    ],
                    [
                        'title' => 'Права доступа на API',
                        'estimate_hours' => 8,
                        'stage' => Task::STAGE_IN_PROGRESS,
                        'start_date' => $today->subDays(3)->toDateString(),
                        'order' => 3,
                        'assignees' => [
                            ['user' => $kuznetsov, 'cap' => 4],
                        ],
                    ],
                    [
                        'title' => 'QA: чек-лист доступа',
                        'estimate_hours' => 6,
                        'stage' => Task::STAGE_PLANNED,
                        'start_date' => $today->addDays(2)->toDateString(),
                        'order' => 4,
                        'assignees' => [
                            ['user' => $sidorova, 'cap' => 4],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($moreShipments as $ms) {
            $shipment = Shipment::firstOrCreate(
                ['project_id' => $project->id, 'title' => $ms['title']],
                [
                    'description' => null,
                    'planned_start_date' => $ms['planned_start'],
                    'planned_due_date' => $ms['planned_due'],
                ]
            );

            if ($shipment->tasks()->exists()) {
                continue;
            }

            foreach ($ms['tasks'] as $idx => $t) {
                $task = Task::create([
                    'project_id' => $project->id,
                    'shipment_id' => $shipment->id,
                    'title' => $t['title'],
                    'acceptance_criteria' => null,
                    'estimate_hours' => $t['estimate_hours'],
                    'start_date' => $t['start_date'],
                    'due_date' => null,
                    'stage' => $t['stage'],
                    'order' => $t['order'] ?? ($idx + 1),
                    'stage_changed_at' => now(),
                ]);

                foreach ($t['assignees'] as $a) {
                    if (!$a['user']) {
                        continue;
                    }
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $a['user']->id,
                        'capacity_hours_per_day' => $a['cap'],
                    ]);
                }

                $logHorizon = 9;
                for ($d = 0; $d <= $logHorizon; $d++) {
                    $workDate = $today->subDays($d);
                    if (!$workDate->isWeekday()) {
                        continue;
                    }

                    if ($task->stage === Task::STAGE_PLANNED) {
                        continue;
                    }

                    foreach ($t['assignees'] as $a) {
                        if (!$a['user']) {
                            continue;
                        }
                        if (random_int(0, 100) < 35) {
                            continue;
                        }

                        TaskWorkLog::create([
                            'task_id' => $task->id,
                            'user_id' => $a['user']->id,
                            'work_date' => $workDate->toDateString(),
                            'minutes' => random_int(30, 240),
                            'comment' => null,
                        ]);
                    }
                }
            }
        }

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
