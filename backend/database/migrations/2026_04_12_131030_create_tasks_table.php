<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('acceptance_criteria')->nullable();
            $table->decimal('estimate_hours', 8, 2);
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->string('stage')->default('planned');
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'shipment_id', 'start_date']);
            $table->index(['shipment_id', 'order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
