<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Shipment;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_endpoint_returns_xlsx_content_type(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet is not installed');
        }

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

        Task::create([
            'project_id' => $project->id,
            'shipment_id' => $shipment->id,
            'title' => 'Task A',
            'acceptance_criteria' => null,
            'estimate_hours' => 8,
            'start_date' => now()->toDateString(),
            'due_date' => null,
            'stage' => Task::STAGE_PLANNED,
            'order' => 1,
        ]);

        $res = $this->get("/api/shipments/{$shipment->id}/export");

        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
