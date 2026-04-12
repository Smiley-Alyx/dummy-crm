<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Shipment;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentGanttApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gantt_endpoint_returns_tasks_with_calculated_fields(): void
    {
        $project = Project::create([
            'name' => 'Test Project',
            'description' => null,
            'status' => 'active',
            'starts_on' => null,
            'ends_on' => null,
        ]);

        $shipment = Shipment::create([
            'project_id' => $project->id,
            'title' => 'Shipment 1',
            'description' => null,
            'planned_start_date' => null,
            'planned_due_date' => null,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'shipment_id' => $shipment->id,
            'title' => 'Task A',
            'acceptance_criteria' => null,
            'estimate_hours' => 16,
            'start_date' => now()->toDateString(),
            'due_date' => null,
            'stage' => Task::STAGE_IN_PROGRESS,
            'order' => 1,
        ]);

        $user = User::factory()->create();

        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'capacity_hours_per_day' => 4,
        ]);

        $res = $this->getJson("/api/shipments/{$shipment->id}/gantt");

        $res->assertOk();
        $res->assertJsonStructure([
            'shipment' => ['id', 'project_id', 'title'],
            'today',
            'tasks' => [
                ['id', 'title', 'start_date', 'due_date', 'planned_end_date', 'control_date', 'color', 'stage', 'capacity_hours_per_day', 'remaining_hours'],
            ],
        ]);

        $this->assertSame($task->id, $res->json('tasks.0.id'));
        $this->assertSame(4, (int) $res->json('tasks.0.capacity_hours_per_day'));
    }
}
